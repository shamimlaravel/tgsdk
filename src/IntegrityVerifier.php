<?php

namespace Shamimstack\Tgsdk;

class IntegrityVerifier
{
    /**
     * Compute a SHA-256 checksum for the given file path.
     */
    public function checksumFile(string $filePath): string
    {
        if (! file_exists($filePath)) {
            throw new \RuntimeException("File not found: {$filePath}");
        }

        return hash_file('sha256', $filePath);
    }

    /**
     * Compute a SHA-256 checksum for raw content.
     */
    public function checksumContent(string $content): string
    {
        return hash('sha256', $content);
    }

    /**
     * Verify that the given file matches the expected checksum.
     */
    public function verifyFile(string $filePath, string $expectedChecksum): bool
    {
        return hash_equals($expectedChecksum, $this->checksumFile($filePath));
    }

    /**
     * Verify that raw content matches the expected checksum.
     */
    public function verifyContent(string $content, string $expectedChecksum): bool
    {
        return hash_equals($expectedChecksum, $this->checksumContent($content));
    }

    /**
     * Compute a streaming SHA-256 checksum for a large file in chunks.
     */
    public function checksumStream(string $filePath, int $bufferSize = 8192): string
    {
        if (! file_exists($filePath)) {
            throw new \RuntimeException("File not found: {$filePath}");
        }

        $context = hash_init('sha256');
        $handle = fopen($filePath, 'rb');

        if ($handle === false) {
            throw new \RuntimeException("Cannot open file: {$filePath}");
        }

        try {
            while (! feof($handle)) {
                $buffer = fread($handle, $bufferSize);
                if ($buffer !== false) {
                    hash_update($context, $buffer);
                }
            }
        } finally {
            fclose($handle);
        }

        return hash_final($context);
    }
}
