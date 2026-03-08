"""
HTTP callback module.
Posts upload results back to the Laravel CallbackController.
"""

import hashlib
import hmac
import json
import logging

import httpx

logger = logging.getLogger(__name__)


async def send_callback(
    callback_url: str,
    secret: str,
    file_record_id: str,
    chunk_index: int | None,
    status: str,
    message_id: int | None = None,
    file_id: str | None = None,
    file_unique_id: str | None = None,
    error: str | None = None,
    session_name: str | None = None,
    max_retries: int = 3,
    retry_delay: float = 5.0,
) -> bool:
    """
    Send an upload result callback to the Laravel application.
    Returns True if the callback was acknowledged, False otherwise.
    """
    payload = json.dumps({
        "file_record_id": file_record_id,
        "chunk_index": chunk_index,
        "status": status,
        "message_id": message_id,
        "file_id": file_id,
        "file_unique_id": file_unique_id,
        "error": error,
        "session_name": session_name,
    })

    headers = {"Content-Type": "application/json"}

    if secret:
        signature = hmac.new(
            secret.encode("utf-8"),
            payload.encode("utf-8"),
            hashlib.sha256,
        ).hexdigest()
        headers["X-Signature"] = signature

    for attempt in range(1, max_retries + 1):
        try:
            async with httpx.AsyncClient(timeout=30.0) as client:
                response = await client.post(
                    callback_url,
                    content=payload,
                    headers=headers,
                )

                if response.status_code == 200:
                    logger.info(
                        f"Callback successful for file={file_record_id} chunk={chunk_index}"
                    )
                    return True

                logger.warning(
                    f"Callback returned {response.status_code} for file={file_record_id} "
                    f"(attempt {attempt}/{max_retries})"
                )

        except Exception as e:
            logger.error(
                f"Callback failed for file={file_record_id} "
                f"(attempt {attempt}/{max_retries}): {e}"
            )

        if attempt < max_retries:
            import asyncio
            await asyncio.sleep(retry_delay * attempt)

    logger.error(
        f"Callback exhausted all retries for file={file_record_id} chunk={chunk_index}"
    )
    return False
