<?php

namespace Shamimstack\Tgsdk\Tests\Feature;

use Shamimstack\Tgsdk\Models\TelegramChannel;
use Shamimstack\Tgsdk\Models\TelegramFile;
use Shamimstack\Tgsdk\Tests\TestCase;

class StreamControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        TelegramChannel::create(['channel_identifier' => '-1001234567890', 'label' => 'Test', 'is_active' => true]);
    }

    public function test_stream_returns_404_for_invalid_token(): void
    {
        $this->get('/tg-stream/nonexistenttoken')->assertStatus(404);
    }

    public function test_stream_returns_202_for_pending_file(): void
    {
        TelegramFile::create(['disk_path' => 'test/pending.txt', 'original_name' => 'pending.txt', 'size' => 100, 'status' => 'pending', 'channel_id' => '-1001234567890', 'download_token' => 'pendingtoken123']);
        $this->get('/tg-stream/pendingtoken123')->assertStatus(202);
    }

    public function test_stream_returns_410_for_failed_file(): void
    {
        TelegramFile::create(['disk_path' => 'test/failed.txt', 'original_name' => 'failed.txt', 'size' => 100, 'status' => 'failed', 'channel_id' => '-1001234567890', 'download_token' => 'failedtoken123']);
        $this->get('/tg-stream/failedtoken123')->assertStatus(410);
    }

    public function test_stream_returns_correct_headers_for_available_file(): void
    {
        TelegramFile::create(['disk_path' => 'test/ready.pdf', 'original_name' => 'ready.pdf', 'mime_type' => 'application/pdf', 'size' => 5000, 'status' => 'available', 'channel_id' => '-1001234567890', 'download_token' => 'readytoken123', 'file_id' => 'BQACAgIAAxkBAAI']);
        $response = $this->get('/tg-stream/readytoken123');
        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/pdf');
        $response->assertHeader('Accept-Ranges', 'bytes');
    }
}
