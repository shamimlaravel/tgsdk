<?php

namespace Shamimstack\Tgsdk\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Shamimstack\Tgsdk\Models\TelegramFile;

class UploadToTelegramJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(
        public readonly string $fileRecordId,
        public readonly ?int $chunkIndex,
        public readonly string $tempPath,
        public readonly string $channelId,
    ) {}

    public function handle(): void
    {
        $file = TelegramFile::find($this->fileRecordId);
        if (! $file) {
            return;
        }

        if ($file->isPending()) {
            $file->markAsUploading();
        }

        $payload = json_encode([
            'job_id' => Str::ulid()->toString(),
            'file_record_id' => $this->fileRecordId,
            'chunk_index' => $this->chunkIndex,
            'temp_path' => $this->tempPath,
            'channel_id' => $this->channelId,
            'callback_url' => config('telegram-storage.worker_callback_url'),
            'hmac_secret' => config('telegram-storage.worker_callback_secret'),
        ]);

        Redis::connection(config('telegram-storage.redis.connection', 'default'))
            ->rpush(config('telegram-storage.redis.queue_key', 'telegram_upload_queue'), $payload);
    }

    public function failed(\Throwable $exception): void
    {
        $file = TelegramFile::find($this->fileRecordId);
        if ($file) {
            $file->markAsFailed();
        }
        if (file_exists($this->tempPath)) {
            unlink($this->tempPath);
        }
    }
}
