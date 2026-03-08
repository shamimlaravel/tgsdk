"""
Session pool manager for multiple Pyrogram clients.
Handles session selection strategy and tracks per-session state.
"""

import asyncio
import logging
from dataclasses import dataclass, field

from pyrogram import Client

from config import SessionConfig, TelegramConfig

logger = logging.getLogger(__name__)


@dataclass
class SessionState:
    client: Client
    config: SessionConfig
    is_busy: bool = False
    active_uploads: int = 0
    total_uploads: int = 0


class SessionPool:
    """
    Manages a pool of Pyrogram client sessions for parallel uploads.
    """

    def __init__(self, telegram_config: TelegramConfig):
        self.telegram_config = telegram_config
        self.sessions: list[SessionState] = []
        self.strategy = telegram_config.session_strategy
        self._rr_index = 0
        self._lock = asyncio.Lock()

    async def initialize(self) -> None:
        """Start all configured Pyrogram sessions."""
        # Add the primary session
        primary = SessionConfig(
            api_id=self.telegram_config.api_id,
            api_hash=self.telegram_config.api_hash,
            session_name=self.telegram_config.session_name,
            bot_token=self.telegram_config.bot_token,
            is_premium=False,
        )
        all_sessions = [primary] + self.telegram_config.sessions

        for session_config in all_sessions:
            try:
                if session_config.bot_token:
                    client = Client(
                        session_config.session_name,
                        api_id=session_config.api_id,
                        api_hash=session_config.api_hash,
                        bot_token=session_config.bot_token,
                    )
                else:
                    client = Client(
                        session_config.session_name,
                        api_id=session_config.api_id,
                        api_hash=session_config.api_hash,
                    )

                await client.start()
                self.sessions.append(SessionState(
                    client=client,
                    config=session_config,
                ))
                logger.info(f"Session '{session_config.session_name}' started successfully.")
            except Exception as e:
                logger.error(f"Failed to start session '{session_config.session_name}': {e}")

        if not self.sessions:
            raise RuntimeError("No Pyrogram sessions could be started.")

        logger.info(f"Session pool initialized with {len(self.sessions)} session(s).")

    async def acquire(self) -> SessionState:
        """Acquire a session based on the configured strategy."""
        async with self._lock:
            if self.strategy == "least-busy":
                return self._select_least_busy()
            return self._select_round_robin()

    async def release(self, session: SessionState) -> None:
        """Release a session after use."""
        async with self._lock:
            session.active_uploads -= 1
            if session.active_uploads <= 0:
                session.is_busy = False
                session.active_uploads = 0

    def _select_round_robin(self) -> SessionState:
        """Select the next session in round-robin order."""
        session = self.sessions[self._rr_index % len(self.sessions)]
        self._rr_index += 1
        session.active_uploads += 1
        session.is_busy = True
        return session

    def _select_least_busy(self) -> SessionState:
        """Select the session with the fewest active uploads."""
        session = min(self.sessions, key=lambda s: s.active_uploads)
        session.active_uploads += 1
        session.is_busy = True
        return session

    async def shutdown(self) -> None:
        """Stop all Pyrogram sessions."""
        for session in self.sessions:
            try:
                await session.client.stop()
                logger.info(f"Session '{session.config.session_name}' stopped.")
            except Exception as e:
                logger.warning(f"Error stopping session '{session.config.session_name}': {e}")

    def get_chunk_limit(self, session: SessionState) -> int:
        """Return the upload size limit for the given session."""
        if session.config.is_premium:
            return 3_900_000_000  # ~3.9 GB for premium
        return 1_950_000_000  # ~1.95 GB for regular
