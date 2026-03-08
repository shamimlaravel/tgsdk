<?php

namespace Shamimstack\Tgsdk\Tests\Unit;

use Shamimstack\Tgsdk\Models\TelegramFile;
use Shamimstack\Tgsdk\Tests\TestCase;

class TelegramStorageAdapterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Seed a channel for the adapter to use
        \Shamimstack\Tgsdk\Models\TelegramChannel::create([
            'channel_identifier' => '-1001234567890',
            'label' => 'Test Channel',
            'is_active' => true,
        ]);
    }

    public function test_file_exists_returns_false_for_missing_file(): void
    {
        $adapter = app(\Shamimstack\Tgsdk\TelegramStorageAdapter::class);

        $this->assertFalse($adapter->fileExists('nonexistent/file.txt'));
    }

    public function test_file_exists_returns_true_for_available_file(): void
    {
        TelegramFile::create([
            'disk_path' => 'test/file.txt',
            'original_name' => 'file.txt',
            'size' => 100,
            'status' => 'available',
            'channel_id' => '-1001234567890',
            'download_token' => 'test-token-123',
        ]);

        $adapter = app(\Shamimstack\Tgsdk\TelegramStorageAdapter::class);

        $this->assertTrue($adapter->fileExists('test/file.txt'));
    }

    public function test_file_exists_returns_false_for_pending_file(): void
    {
        TelegramFile::create([
            'disk_path' => 'test/pending.txt',
            'original_name' => 'pending.txt',
            'size' => 100,
            'status' => 'pending',
            'channel_id' => '-1001234567890',
            'download_token' => 'test-token-456',
        ]);

        $adapter = app(\Shamimstack\Tgsdk\TelegramStorageAdapter::class);

        $this->assertFalse($adapter->fileExists('test/pending.txt'));
    }

    public function test_directory_exists_returns_true_when_files_present(): void
    {
        TelegramFile::create([
            'disk_path' => 'uploads/photo.jpg',
            'original_name' => 'photo.jpg',
            'size' => 5000,
            'status' => 'available',
            'channel_id' => '-1001234567890',
            'download_token' => 'test-token-789',
        ]);

        $adapter = app(\Shamimstack\Tgsdk\TelegramStorageAdapter::class);

        $this->assertTrue($adapter->directoryExists('uploads'));
    }

    public function test_get_url_returns_empty_for_missing_file(): void
    {
        $adapter = app(\Shamimstack\Tgsdk\TelegramStorageAdapter::class);

        $this->assertEmpty($adapter->getUrl('nonexistent.txt'));
    }

    public function test_get_url_returns_url_for_available_file(): void
    {
        TelegramFile::create([
            'disk_path' => 'test/url-file.txt',
            'original_name' => 'url-file.txt',
            'size' => 100,
            'status' => 'available',
            'channel_id' => '-1001234567890',
            'download_token' => 'abc123def456',
        ]);

        $adapter = app(\Shamimstack\Tgsdk\TelegramStorageAdapter::class);
        $url = $adapter->getUrl('test/url-file.txt');

        $this->assertStringContainsString('abc123def456', $url);
    }

    public function test_delete_removes_file_record(): void
    {
        TelegramFile::create([
            'disk_path' => 'test/delete-me.txt',
            'original_name' => 'delete-me.txt',
            'size' => 100,
            'status' => 'available',
            'channel_id' => '-1001234567890',
            'download_token' => 'delete-token',
        ]);

        $adapter = app(\Shamimstack\Tgsdk\TelegramStorageAdapter::class);
        $adapter->delete('test/delete-me.txt');

        $this->assertNull(TelegramFile::where('disk_path', 'test/delete-me.txt')->first());
    }

    public function test_delete_is_idempotent(): void
    {
        $adapter = app(\Shamimstack\Tgsdk\TelegramStorageAdapter::class);

        // Should not throw
        $adapter->delete('nonexistent/file.txt');
        $this->assertTrue(true);
    }

    public function test_move_updates_disk_path(): void
    {
        TelegramFile::create([
            'disk_path' => 'old/path.txt',
            'original_name' => 'path.txt',
            'size' => 100,
            'status' => 'available',
            'channel_id' => '-1001234567890',
            'download_token' => 'move-token',
        ]);

        $adapter = app(\Shamimstack\Tgsdk\TelegramStorageAdapter::class);
        $adapter->move('old/path.txt', 'new/path.txt', new \League\Flysystem\Config());

        $this->assertNull(TelegramFile::where('disk_path', 'old/path.txt')->first());
        $this->assertNotNull(TelegramFile::where('disk_path', 'new/path.txt')->first());
    }

    public function test_file_size_returns_correct_size(): void
    {
        TelegramFile::create([
            'disk_path' => 'test/sized.txt',
            'original_name' => 'sized.txt',
            'size' => 54321,
            'status' => 'available',
            'channel_id' => '-1001234567890',
            'download_token' => 'size-token',
        ]);

        $adapter = app(\Shamimstack\Tgsdk\TelegramStorageAdapter::class);
        $attrs = $adapter->fileSize('test/sized.txt');

        $this->assertSame(54321, $attrs->fileSize());
    }

    public function test_mime_type_returns_stored_value(): void
    {
        TelegramFile::create([
            'disk_path' => 'test/typed.pdf',
            'original_name' => 'typed.pdf',
            'mime_type' => 'application/pdf',
            'size' => 1000,
            'status' => 'available',
            'channel_id' => '-1001234567890',
            'download_token' => 'mime-token',
        ]);

        $adapter = app(\Shamimstack\Tgsdk\TelegramStorageAdapter::class);
        $attrs = $adapter->mimeType('test/typed.pdf');

        $this->assertSame('application/pdf', $attrs->mimeType());
    }
}
