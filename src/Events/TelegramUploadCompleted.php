<?php

namespace Shamimstack\Tgsdk\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TelegramUploadCompleted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly string $fileRecordId,
        public readonly string $path,
        public readonly ?string $fileId = null,
    ) {}
}
