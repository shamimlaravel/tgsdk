"""
Pyrogram upload module.
Handles the actual file upload to Telegram channels via MTProto.
"""

import logging
import os

from pyrogram import Client
from pyrogram.types import Message

logger = logging.getLogger(__name__)


async def upload_file(
    client: Client,
    file_path: str,
    channel_id: str,
    caption: str | None = None,
) -> dict:
    """
    Upload a file to a Telegram channel using Pyrogram.

    Returns a dict with message_id, file_id, and file_unique_id.
    """
    if not os.path.exists(file_path):
        raise FileNotFoundError(f"File not found: {file_path}")

    file_size = os.path.getsize(file_path)
    logger.info(
        f"Uploading {file_path} ({file_size} bytes) to channel {channel_id}"
    )

    try:
        # Use send_document for generic file upload (preserves original file)
        message: Message = await client.send_document(
            chat_id=channel_id,
            document=file_path,
            caption=caption or os.path.basename(file_path),
            force_document=True,
        )

        # Extract file metadata from the sent message
        if message.document:
            result = {
                "message_id": message.id,
                "file_id": message.document.file_id,
                "file_unique_id": message.document.file_unique_id,
            }
        elif message.video:
            result = {
                "message_id": message.id,
                "file_id": message.video.file_id,
                "file_unique_id": message.video.file_unique_id,
            }
        elif message.audio:
            result = {
                "message_id": message.id,
                "file_id": message.audio.file_id,
                "file_unique_id": message.audio.file_unique_id,
            }
        elif message.photo:
            # Use the largest photo size
            photo = message.photo
            result = {
                "message_id": message.id,
                "file_id": photo.file_id,
                "file_unique_id": photo.file_unique_id,
            }
        else:
            result = {
                "message_id": message.id,
                "file_id": None,
                "file_unique_id": None,
            }

        logger.info(
            f"Upload successful: message_id={result['message_id']}, "
            f"file_id={result['file_id']}"
        )
        return result

    except Exception as e:
        logger.error(f"Upload failed for {file_path}: {e}")
        raise
