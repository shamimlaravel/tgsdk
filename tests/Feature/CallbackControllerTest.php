<?php

namespace Shamimstack\Tgsdk\Tests\Feature;

use Shamimstack\Tgsdk\Models\TelegramChannel;
use Shamimstack\Tgsdk\Models\TelegramFile;
use Shamimstack\Tgsdk\Tests\TestCase;

class CallbackControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        TelegramChannel::create([
            'channel_identifier' => '-1001234567890',
            'label' => 'Test Channel',
            'is_active' => true,
        ]);
    }

    public function test_callback_returns_404_for_missing_file(): void
    {
        $payload = json_encode([
            'file_record_id' => 'nonexistent-id',
            'chunk_index' => null,
            'status' => 'success',
            'message_id' => 123,
            'file_id' => 'BQACAgIAAxkBAAI',
        ]);

        $response = $this->postJson(
            '/telegram-storage/callback',
            json_decode($payload, true),
            $this->buildCallbackHeaders($payload),
        );

        $response->assertStatus(404);
    }

    public function test_callback_marks_file_as_available_on_success(): void
    {
        $file = TelegramFile::create([
            'disk_path' => 'test/callback.txt',
            'original_name' => 'callback.txt',
            'size' => 100,
            'status' => 'uploading',
            'channel_id' => '-1001234567890',
            'download_token' => 'callback-token',
        ]);

        $payload = json_encode([
            'file_record_id' => $file->id,
            'chunk_index' => null,
            'status' => 'success',
            'message_id' => 456,
            'file_id' => 'BQACAgIAAxkBAAI',
            'file_unique_id' => 'AgADBQAC',
        ]);

        $response = $this->postJson(
            '/telegram-storage/callback',
            json_decode($payload, true),
            $this->buildCallbackHeaders($payload),
        );

        $response->assertStatus(200);

        $file->refresh();
        $this->assertSame('available', $file->status);
        $this->assertSame(456, $file->message_id);
        $this->assertSame('BQACAgIAAxkBAAI', $file->file_id);
    }

    public function test_callback_marks_file_as_failed(): void
    {
        $file = TelegramFile::create([
            'disk_path' => 'test/fail-cb.txt',
            'original_name' => 'fail-cb.txt',
            'size' => 100,
            'status' => 'uploading',
            'channel_id' => '-1001234567890',
            'download_token' => 'fail-cb-token',
        ]);

        $payload = json_encode([
            'file_record_id' => $file->id,
            'chunk_index' => null,
            'status' => 'failed',
            'error' => 'Rate limited by Telegram',
        ]);

        $response = $this->postJson(
            '/telegram-storage/callback',
            json_decode($payload, true),
            $this->buildCallbackHeaders($payload),
        );

        $response->assertStatus(200);

        $file->refresh();
        $this->assertSame('failed', $file->status);
    }

    public function test_callback_updates_channel_counters_on_success(): void
    {
        $file = TelegramFile::create([
            'disk_path' => 'test/counter.txt',
            'original_name' => 'counter.txt',
            'size' => 5000,
            'status' => 'uploading',
            'channel_id' => '-1001234567890',
            'download_token' => 'counter-token',
        ]);

        $payload = json_encode([
            'file_record_id' => $file->id,
            'chunk_index' => null,
            'status' => 'success',
            'message_id' => 789,
            'file_id' => 'BQACAgIAAxkBBBI',
        ]);

        $this->postJson(
            '/telegram-storage/callback',
            json_decode($payload, true),
            $this->buildCallbackHeaders($payload),
        );

        $channel = TelegramChannel::where('channel_identifier', '-1001234567890')->first();
        $this->assertSame(1, $channel->total_files);
        $this->assertSame(5000, $channel->total_bytes);
    }

    protected function buildCallbackHeaders(string $payload): array
    {
        $secret = config('telegram-storage.worker_callback_secret');
        $signature = hash_hmac('sha256', $payload, $secret);

        return [
            'X-Signature' => $signature,
        ];
    }
}
