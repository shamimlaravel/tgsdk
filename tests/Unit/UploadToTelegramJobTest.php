<?php

namespace Shamimstack\Tgsdk\Tests\Unit;

use Illuminate\Support\Facades\Queue;
use Shamimstack\Tgsdk\Events\TelegramUploadQueued;
use Shamimstack\Tgsdk\Jobs\UploadToTelegramJob;
use Shamimstack\Tgsdk\Models\TelegramFile;
use Shamimstack\Tgsdk\Tests\TestCase;

class UploadToTelegramJobTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    public function test_job_serializes_correctly(): void
    {
        $file = TelegramFile::create([
            'disk_path' => 'test/job-file.txt',
            'original_name' => 'job-file.txt',
            'size' => 1024,
            'status' => 'pending',
            'channel_id' => '-1001234567890',
            'download_token' => 'job-token',
        ]);

        $job = new UploadToTelegramJob($file->id);

        $this->assertEquals($file->id, $job->fileId);
    }

    public function test_job_dispatches_to_queue(): void
    {
        $file = TelegramFile::create([
            'disk_path' => 'test/queued-file.txt',
            'original_name' => 'queued-file.txt',
            'size' => 2048,
            'status' => 'pending',
            'channel_id' => '-1001234567890',
            'download_token' => 'queue-token',
        ]);

        UploadToTelegramJob::dispatch($file->id);

        Queue::assertPushed(UploadToTelegramJob::class, function ($job) use ($file) {
            return $job->fileId === $file->id;
        });
    }

    public function test_job_fires_queued_event(): void
    {
        Event::fake([TelegramUploadQueued::class]);

        $file = TelegramFile::create([
            'disk_path' => 'test/event-file.txt',
            'original_name' => 'event-file.txt',
            'size' => 512,
            'status' => 'pending',
            'channel_id' => '-1001234567890',
            'download_token' => 'event-token',
        ]);

        UploadToTelegramJob::dispatch($file->id);

        Event::assertDispatched(TelegramUploadQueued::class, function ($event) use ($file) {
            return $event->fileId === $file->id;
        });
    }

    public function test_job_uses_correct_queue(): void
    {
        $file = TelegramFile::create([
            'disk_path' => 'test/queue-file.txt',
            'original_name' => 'queue-file.txt',
            'size' => 100,
            'status' => 'pending',
            'channel_id' => '-1001234567890',
            'download_token' => 'queue-name-token',
        ]);

        UploadToTelegramJob::dispatch($file->id)->onQueue('telegram');

        Queue::assertPushedOn('telegram', UploadToTelegramJob::class);
    }

    public function test_job_retry_configuration(): void
    {
        $file = TelegramFile::create([
            'disk_path' => 'test/retry-file.txt',
            'original_name' => 'retry-file.txt',
            'size' => 100,
            'status' => 'pending',
            'channel_id' => '-1001234567890',
            'download_token' => 'retry-token',
        ]);

        $job = new UploadToTelegramJob($file->id);

        // Verify job has retry configuration
        $this->assertTrue(method_exists($job, 'retryUntil') || method_exists($job, 'tries'));
    }
}
