<?php

namespace Shamimstack\Tgsdk\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TelegramChunkFailed
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly string $fileRecordId,
        public readonly int $chunkIndex,
        public readonly string $error,
    ) {}
}
