"""
Configuration module for the Telegram Storage Python Worker.
Reads environment variables for Redis, Telegram API, and callback settings.
"""

import os
from dataclasses import dataclass, field


@dataclass
class RedisConfig:
    host: str = os.getenv("REDIS_HOST", "127.0.0.1")
    port: int = int(os.getenv("REDIS_PORT", "6379"))
    db: int = int(os.getenv("REDIS_DB", "0"))
    password: str | None = os.getenv("REDIS_PASSWORD")
    queue_key: str = os.getenv("TELEGRAM_STORAGE_REDIS_QUEUE", "telegram_upload_queue")


@dataclass
class SessionConfig:
    api_id: int
    api_hash: str
    session_name: str
    bot_token: str | None = None
    is_premium: bool = False


@dataclass
class TelegramConfig:
    api_id: int = int(os.getenv("TELEGRAM_API_ID", "0"))
    api_hash: str = os.getenv("TELEGRAM_API_HASH", "")
    session_name: str = os.getenv("TELEGRAM_SESSION_NAME", "telegram_storage")
    bot_token: str | None = os.getenv("TELEGRAM_BOT_TOKEN")
    concurrency: int = int(os.getenv("TELEGRAM_UPLOAD_CONCURRENCY", "3"))
    sessions: list[SessionConfig] = field(default_factory=list)
    session_strategy: str = os.getenv("TELEGRAM_SESSION_STRATEGY", "round-robin")


@dataclass
class CallbackConfig:
    url: str = os.getenv("TELEGRAM_STORAGE_CALLBACK_URL", "http://localhost:8000/telegram-storage/callback")
    secret: str = os.getenv("TELEGRAM_STORAGE_CALLBACK_SECRET", "")


@dataclass
class WorkerConfig:
    redis: RedisConfig = field(default_factory=RedisConfig)
    telegram: TelegramConfig = field(default_factory=TelegramConfig)
    callback: CallbackConfig = field(default_factory=CallbackConfig)
    max_retries: int = int(os.getenv("WORKER_MAX_RETRIES", "3"))
    retry_delay: float = float(os.getenv("WORKER_RETRY_DELAY", "5.0"))


def load_config() -> WorkerConfig:
    """Load the worker configuration from environment variables."""
    config = WorkerConfig()

    # Parse additional sessions from TELEGRAM_SESSIONS env var (JSON array)
    sessions_json = os.getenv("TELEGRAM_SESSIONS", "")
    if sessions_json:
        import json
        try:
            sessions_data = json.loads(sessions_json)
            for s in sessions_data:
                config.telegram.sessions.append(SessionConfig(
                    api_id=s["api_id"],
                    api_hash=s["api_hash"],
                    session_name=s["session_name"],
                    bot_token=s.get("bot_token"),
                    is_premium=s.get("is_premium", False),
                ))
        except (json.JSONDecodeError, KeyError) as e:
            print(f"Warning: Failed to parse TELEGRAM_SESSIONS: {e}")

    return config
