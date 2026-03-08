<?php

namespace Shamimstack\Tgsdk\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Shamimstack\Tgsdk\IntegrityVerifier;

class IntegrityVerifierTest extends TestCase
{
    protected IntegrityVerifier $verifier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->verifier = new IntegrityVerifier();
    }

    public function test_checksum_content_returns_sha256_hex(): void
    {
        $content = 'Hello, Telegram Storage!';
        $checksum = $this->verifier->checksumContent($content);
        $this->assertSame(64, strlen($checksum));
        $this->assertSame(hash('sha256', $content), $checksum);
    }

    public function test_verify_content_returns_true_for_match(): void
    {
        $content = 'Test content';
        $this->assertTrue($this->verifier->verifyContent($content, hash('sha256', $content)));
    }

    public function test_verify_content_returns_false_for_mismatch(): void
    {
        $this->assertFalse($this->verifier->verifyContent('Test', hash('sha256', 'Other')));
    }

    public function test_checksum_file_computes_hash(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'tgsdk_');
        file_put_contents($tmp, 'File content');
        try {
            $this->assertSame(hash_file('sha256', $tmp), $this->verifier->checksumFile($tmp));
        } finally {
            unlink($tmp);
        }
    }

    public function test_checksum_file_throws_for_missing(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->verifier->checksumFile('/nonexistent/file.txt');
    }

    public function test_checksum_stream_matches_regular(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'tgsdk_');
        file_put_contents($tmp, str_repeat('A', 100000));
        try {
            $this->assertSame($this->verifier->checksumFile($tmp), $this->verifier->checksumStream($tmp, 4096));
        } finally {
            unlink($tmp);
        }
    }
}
