<?php

namespace Shamimstack\Tgsdk;

use Illuminate\Support\Facades\File;

class ChunkManager
{
    protected int $chunkThreshold;

    protected int $chunkSize;

    protected bool $compressionEnabled;

    protected bool $encryptionEnabled;

    protected ?string $encryptionKey;

    protected string $tempPath;

    protected IntegrityVerifier $verifier;

    /**
     * MIME types that should skip compression (already compressed).
     */
    protected array $skipCompressionMimeTypes = [
        'image/jpeg', 'image/png', 'image/gif', 'image/webp',
        'video/mp4', 'video/webm', 'video/avi', 'video/quicktime',
        'audio/mpeg', 'audio/ogg', 'audio/mp4',
        'application/zip', 'application/gzip', 'application/x-rar-compressed',
        'application/x-7z-compressed', 'application/x-bzip2',
    ];

    public function __construct(IntegrityVerifier $verifier)
    {
        $this->verifier = $verifier;
        $this->chunkThreshold = (int) config('telegram-storage.chunk_threshold', 1_950_000_000);
        $this->chunkSize = (int) config('telegram-storage.chunk_size', 1_950_000_000);
        $this->compressionEnabled = (bool) config('telegram-storage.chunk_compression', false);
        $this->encryptionEnabled = (bool) config('telegram-storage.chunk_encryption', false);
        $this->encryptionKey = config('telegram-storage.chunk_encryption_key');
        $this->tempPath = config('telegram-storage.temp_path', storage_path('app/telegram-tmp'));
    }

    /**
     * Determine if the file needs chunking.
     */
    public function needsChunking(int $fileSize): bool
    {
        return $fileSize > $this->chunkThreshold;
    }

    /**
     * Calculate how many chunks a file of the given size requires.
     */
    public function calculateChunkCount(int $fileSize): int
    {
        if (! $this->needsChunking($fileSize)) {
            return 1;
        }

        return (int) ceil($fileSize / $this->chunkSize);
    }

    /**
     * Split a file into chunks on disk and return metadata about each chunk.
     *
     * @return array<int, array{path: string, index: int, size: int, checksum: string}>
     */
    public function splitFile(string $filePath, ?string $mimeType = null): array
    {
        $this->ensureTempDirectory();

        $fileSize = filesize($filePath);
        if ($fileSize === false) {
            throw new \RuntimeException("Cannot determine file size: {$filePath}");
        }

        if (! $this->needsChunking($fileSize)) {
            $checksum = $this->verifier->checksumStream($filePath);
            $chunkPath = $this->processChunk($filePath, $mimeType, 0);

            return [[
                'path' => $chunkPath,
                'index' => 0,
                'size' => filesize($chunkPath),
                'checksum' => $checksum,
                'is_compressed' => $this->shouldCompress($mimeType) && $chunkPath !== $filePath,
                'encryption_iv' => null,
            ]];
        }

        $handle = fopen($filePath, 'rb');
        if ($handle === false) {
            throw new \RuntimeException("Cannot open file: {$filePath}");
        }

        $chunks = [];
        $index = 0;

        try {
            while (! feof($handle)) {
                $chunkData = fread($handle, $this->chunkSize);
                if ($chunkData === false || $chunkData === '') {
                    break;
                }

                $chunkTempPath = $this->tempPath . DIRECTORY_SEPARATOR . 'chunk_' . uniqid() . '_' . $index;
                file_put_contents($chunkTempPath, $chunkData);

                $checksum = $this->verifier->checksumFile($chunkTempPath);
                $processedPath = $this->processChunk($chunkTempPath, $mimeType, $index);

                $chunks[] = [
                    'path' => $processedPath,
                    'index' => $index,
                    'size' => filesize($processedPath),
                    'checksum' => $checksum,
                    'is_compressed' => $this->shouldCompress($mimeType) && $processedPath !== $chunkTempPath,
                    'encryption_iv' => null,
                ];

                // Clean up unprocessed chunk if it was transformed
                if ($processedPath !== $chunkTempPath && file_exists($chunkTempPath)) {
                    unlink($chunkTempPath);
                }

                $index++;
            }
        } finally {
            fclose($handle);
        }

        return $chunks;
    }

    /**
     * Apply compression and/or encryption to a chunk.
     */
    protected function processChunk(string $chunkPath, ?string $mimeType, int $index): string
    {
        $currentPath = $chunkPath;

        if ($this->shouldCompress($mimeType)) {
            $compressedPath = $chunkPath . '.gz';
            $this->compressFile($currentPath, $compressedPath);

            // Only use compression if it actually reduces size
            if (filesize($compressedPath) < filesize($currentPath)) {
                if ($currentPath !== $chunkPath) {
                    unlink($currentPath);
                }
                $currentPath = $compressedPath;
            } else {
                unlink($compressedPath);
            }
        }

        if ($this->encryptionEnabled && $this->encryptionKey) {
            $encryptedPath = $currentPath . '.enc';
            $this->encryptFile($currentPath, $encryptedPath);
            if ($currentPath !== $chunkPath) {
                unlink($currentPath);
            }
            $currentPath = $encryptedPath;
        }

        return $currentPath;
    }

    /**
     * Check whether compression should be applied for the given MIME type.
     */
    protected function shouldCompress(?string $mimeType): bool
    {
        if (! $this->compressionEnabled) {
            return false;
        }

        if ($mimeType && in_array($mimeType, $this->skipCompressionMimeTypes, true)) {
            return false;
        }

        return true;
    }

    /**
     * Compress a file using gzip.
     */
    protected function compressFile(string $sourcePath, string $destPath): void
    {
        $source = fopen($sourcePath, 'rb');
        $dest = gzopen($destPath, 'wb9');

        if ($source === false || $dest === false) {
            throw new \RuntimeException('Failed to open files for compression.');
        }

        try {
            while (! feof($source)) {
                $buffer = fread($source, 8192);
                if ($buffer !== false) {
                    gzwrite($dest, $buffer);
                }
            }
        } finally {
            fclose($source);
            gzclose($dest);
        }
    }

    /**
     * Encrypt a file using AES-256-GCM.
     */
    protected function encryptFile(string $sourcePath, string $destPath): void
    {
        $plaintext = file_get_contents($sourcePath);
        if ($plaintext === false) {
            throw new \RuntimeException("Cannot read file for encryption: {$sourcePath}");
        }

        $iv = random_bytes(12); // 96-bit nonce for GCM
        $tag = '';

        $ciphertext = openssl_encrypt(
            $plaintext,
            'aes-256-gcm',
            hex2bin($this->encryptionKey),
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            '',
            16
        );

        if ($ciphertext === false) {
            throw new \RuntimeException('Encryption failed.');
        }

        // Store: IV (12 bytes) + Tag (16 bytes) + Ciphertext
        file_put_contents($destPath, $iv . $tag . $ciphertext);
    }

    /**
     * Decrypt a file that was encrypted with AES-256-GCM.
     */
    public function decryptContent(string $encrypted): string
    {
        $iv = substr($encrypted, 0, 12);
        $tag = substr($encrypted, 12, 16);
        $ciphertext = substr($encrypted, 28);

        $plaintext = openssl_decrypt(
            $ciphertext,
            'aes-256-gcm',
            hex2bin($this->encryptionKey),
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        if ($plaintext === false) {
            throw new \RuntimeException('Decryption failed.');
        }

        return $plaintext;
    }

    /**
     * Decompress gzipped content.
     */
    public function decompressContent(string $compressed): string
    {
        $decompressed = gzdecode($compressed);

        if ($decompressed === false) {
            throw new \RuntimeException('Decompression failed.');
        }

        return $decompressed;
    }

    /**
     * Clean up temp chunk files for a given set of chunk paths.
     */
    public function cleanupChunks(array $chunkPaths): void
    {
        foreach ($chunkPaths as $path) {
            if (file_exists($path)) {
                unlink($path);
            }
        }
    }

    /**
     * Ensure the temporary directory exists.
     */
    protected function ensureTempDirectory(): void
    {
        if (! is_dir($this->tempPath)) {
            File::makeDirectory($this->tempPath, 0755, true);
        }
    }

    public function getTempPath(): string
    {
        return $this->tempPath;
    }

    public function getChunkSize(): int
    {
        return $this->chunkSize;
    }

    public function getChunkThreshold(): int
    {
        return $this->chunkThreshold;
    }
}
