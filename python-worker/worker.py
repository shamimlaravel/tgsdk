"""
Telegram Storage Python Worker — Main entry point.

Consumes upload jobs from Redis queue, uploads files to Telegram
via Pyrogram, and reports results back to Laravel via HTTP callback.
"""

import asyncio
import json
import logging
import signal
import sys

import redis.asyncio as aioredis

from callback import send_callback
from config import WorkerConfig, load_config
from session_pool import SessionPool
from uploader import upload_file

# Configure logging
logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s [%(levelname)s] %(name)s: %(message)s",
    handlers=[logging.StreamHandler(sys.stdout)],
)
logger = logging.getLogger("telegram-worker")


class TelegramUploadWorker:
    """
    Main worker class. Connects to Redis, listens for upload jobs,
    and processes them using the Pyrogram session pool.
    """

    def __init__(self, config: WorkerConfig):
        self.config = config
        self.session_pool = SessionPool(config.telegram)
        self.redis: aioredis.Redis | None = None
        self.running = False
        self.semaphore = asyncio.Semaphore(config.telegram.concurrency)

    async def start(self) -> None:
        """Initialize connections and start processing."""
        logger.info("Starting Telegram Upload Worker...")

        # Connect to Redis
        self.redis = aioredis.Redis(
            host=self.config.redis.host,
            port=self.config.redis.port,
            db=self.config.redis.db,
            password=self.config.redis.password,
            decode_responses=True,
        )

        # Initialize Pyrogram session pool
        await self.session_pool.initialize()

        self.running = True
        logger.info("Worker started. Listening for upload jobs...")

        # Process jobs
        await self._process_loop()

    async def _process_loop(self) -> None:
        """Main loop: block on Redis queue and process jobs."""
        while self.running:
            try:
                # BLPOP blocks until a job is available (timeout: 5 seconds)
                result = await self.redis.blpop(
                    self.config.redis.queue_key,
                    timeout=5,
                )

                if result is None:
                    continue

                _, job_data = result
                job = json.loads(job_data)

                logger.info(
                    f"Received job: file={job['file_record_id']} "
                    f"chunk={job.get('chunk_index')}"
                )

                # Process job with concurrency limit
                asyncio.create_task(self._process_job(job))

            except asyncio.CancelledError:
                break
            except Exception as e:
                logger.error(f"Error in process loop: {e}")
                await asyncio.sleep(1)

    async def _process_job(self, job: dict) -> None:
        """Process a single upload job."""
        async with self.semaphore:
            session = await self.session_pool.acquire()

            try:
                # Upload the file
                result = await upload_file(
                    client=session.client,
                    file_path=job["temp_path"],
                    channel_id=job["channel_id"],
                )

                session.total_uploads += 1

                # Send success callback
                await send_callback(
                    callback_url=job["callback_url"],
                    secret=job.get("hmac_secret", ""),
                    file_record_id=job["file_record_id"],
                    chunk_index=job.get("chunk_index"),
                    status="success",
                    message_id=result["message_id"],
                    file_id=result["file_id"],
                    file_unique_id=result.get("file_unique_id"),
                    session_name=session.config.session_name,
                    max_retries=self.config.max_retries,
                    retry_delay=self.config.retry_delay,
                )

                logger.info(
                    f"Job completed: file={job['file_record_id']} "
                    f"chunk={job.get('chunk_index')} "
                    f"session={session.config.session_name}"
                )

            except Exception as e:
                logger.error(
                    f"Job failed: file={job['file_record_id']} "
                    f"chunk={job.get('chunk_index')}: {e}"
                )

                # Send failure callback
                await send_callback(
                    callback_url=job["callback_url"],
                    secret=job.get("hmac_secret", ""),
                    file_record_id=job["file_record_id"],
                    chunk_index=job.get("chunk_index"),
                    status="failed",
                    error=str(e),
                    session_name=session.config.session_name,
                    max_retries=self.config.max_retries,
                    retry_delay=self.config.retry_delay,
                )

            finally:
                await self.session_pool.release(session)

    async def shutdown(self) -> None:
        """Gracefully shut down the worker."""
        logger.info("Shutting down worker...")
        self.running = False

        await self.session_pool.shutdown()

        if self.redis:
            await self.redis.close()

        logger.info("Worker shut down complete.")


async def main() -> None:
    """Entry point."""
    config = load_config()
    worker = TelegramUploadWorker(config)

    # Handle shutdown signals
    loop = asyncio.get_event_loop()

    def signal_handler():
        asyncio.create_task(worker.shutdown())

    for sig in (signal.SIGINT, signal.SIGTERM):
        try:
            loop.add_signal_handler(sig, signal_handler)
        except NotImplementedError:
            # Windows doesn't support add_signal_handler
            pass

    try:
        await worker.start()
    except KeyboardInterrupt:
        await worker.shutdown()


if __name__ == "__main__":
    asyncio.run(main())
