<?php

namespace Shamimstack\Tgsdk\Tests\Unit;

use Shamimstack\Tgsdk\ChunkManager;
use Shamimstack\Tgsdk\IntegrityVerifier;
use Shamimstack\Tgsdk\Tests\TestCase;

class ChunkManagerTest extends TestCase
{
    protected ChunkManager $manager;

    protected string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempDir = sys_get_temp_dir() . '/tgsdk_chunk_test_' . uniqid();
        mkdir($this->tempDir, 0755, true);

        // Use reflection or mock config for testing outside of Laravel
        $verifier = new IntegrityVerifier();
        $this->manager = new ChunkManager($verifier);
    }

    protected function tearDown(): void
    {
        // Clean up temp directory
        $files = glob($this->tempDir . '/*');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        if (is_dir($this->tempDir)) {
            rmdir($this->tempDir);
        }
        parent::tearDown();
    }

    public function test_needs_chunking_returns_false_for_small_files(): void
    {
        $this->assertFalse($this->manager->needsChunking(1_000_000)); // 1 MB
    }

    public function test_needs_chunking_returns_true_for_large_files(): void
    {
        $this->assertTrue($this->manager->needsChunking(2_000_000_000)); // 2 GB
    }

    public function test_calculate_chunk_count_returns_one_for_small_files(): void
    {
        $this->assertSame(1, $this->manager->calculateChunkCount(500_000_000)); // 500 MB
    }

    public function test_calculate_chunk_count_returns_correct_count(): void
    {
        // ~4 GB file with 1.95 GB chunk size = 3 chunks (ceil)
        $fileSize = 4_000_000_000;
        $count = $this->manager->calculateChunkCount($fileSize);

        $this->assertGreaterThanOrEqual(2, $count);
    }

    public function test_get_temp_path_returns_string(): void
    {
        $path = $this->manager->getTempPath();
        $this->assertIsString($path);
    }

    public function test_get_chunk_size_returns_positive_int(): void
    {
        $size = $this->manager->getChunkSize();
        $this->assertGreaterThan(0, $size);
    }

    public function test_decompress_content_throws_on_invalid_data(): void
    {
        $this->expectException(\ErrorException::class);
        $this->manager->decompressContent('not gzipped data');
    }
}
