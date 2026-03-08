<?php

namespace Shamimstack\Tgsdk;

use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use League\Flysystem\Config;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\UnableToCheckExistence;
use League\Flysystem\UnableToDeleteFile;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\UnableToRetrieveMetadata;
use League\Flysystem\UnableToWriteFile;
use Shamimstack\Tgsdk\Events\TelegramFileDeleted;
use Shamimstack\Tgsdk\Events\TelegramUploadQueued;
use Shamimstack\Tgsdk\Models\TelegramFile;
use Shamimstack\Tgsdk\Models\TelegramFileChunk;

class TelegramStorageAdapter implements FilesystemAdapter
{
    public function __construct(
        protected ChannelRotator $channelRotator,
        protected ChunkManager $chunkManager,
        protected IntegrityVerifier $verifier,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function fileExists(string $path): bool
    {
        return TelegramFile::where('disk_path', $path)
            ->where('status', 'available')
            ->exists();
    }

    /**
     * {@inheritDoc}
     */
    public function directoryExists(string $path): bool
    {
        return TelegramFile::where('disk_path', 'like', rtrim($path, '/') . '/%')
            ->where('status', 'available')
            ->exists();
    }

    /**
     * {@inheritDoc}
     */
    public function write(string $path, string $contents, Config $config): void
    {
        $this->uploadFromContents($path, $contents, $config);
    }

    /**
     * {@inheritDoc}
     */
    public function writeStream(string $path, $contents, Config $config): void
    {
        $tempPath = $this->chunkManager->getTempPath() . DIRECTORY_SEPARATOR . 'upload_' . Str::random(32);

        if (! is_dir($this->chunkManager->getTempPath())) {
            mkdir($this->chunkManager->getTempPath(), 0755, true);
        }

        $dest = fopen($tempPath, 'wb');
        if ($dest === false) {
            throw UnableToWriteFile::atLocation($path, 'Could not create temp file.');
        }

        try {
            stream_copy_to_stream($contents, $dest);
            fclose($dest);

            $fileContents = file_get_contents($tempPath);
            $this->uploadFromContents($path, $fileContents, $config);
        } finally {
            if (file_exists($tempPath)) {
                unlink($tempPath);
            }
        }
    }

    /**
     * {@inheritDoc}
     */
    public function read(string $path): string
    {
        $file = $this->findFileOrFail($path, 'read');

        if (! $file->isAvailable()) {
            throw UnableToReadFile::fromLocation($path, 'File is not yet available (status: ' . $file->status . ').');
        }

        // For synchronous reads, we download via Telegram Bot API HTTP endpoint
        // This requires the file_id to be present
        if (! $file->file_id && ! $file->is_chunked) {
            throw UnableToReadFile::fromLocation($path, 'File has no Telegram file_id. Upload may still be in progress.');
        }

        // Delegate to the Bot API download — this will be handled by the StreamController
        // For the adapter, we return the content via an internal HTTP call
        $url = route('telegram-storage.stream.raw', ['token' => $file->download_token]);

        $response = app('Illuminate\Http\Client\Factory')->get($url);

        if ($response->failed()) {
            throw UnableToReadFile::fromLocation($path, 'Failed to download from streaming endpoint.');
        }

        return $response->body();
    }

    /**
     * {@inheritDoc}
     */
    public function readStream(string $path)
    {
        $content = $this->read($path);
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, $content);
        rewind($stream);

        return $stream;
    }

    /**
     * {@inheritDoc}
     */
    public function delete(string $path): void
    {
        $file = TelegramFile::where('disk_path', $path)->first();

        if (! $file) {
            return; // Flysystem expects delete to be idempotent
        }

        // Delete associated chunks
        $file->chunks()->delete();

        // Decrement channel counters
        $channel = \Shamimstack\Tgsdk\Models\TelegramChannel::where('channel_identifier', $file->channel_id)->first();
        if ($channel) {
            $channel->decrementFileCount($file->size);
        }

        $diskPath = $file->disk_path;
        $fileId = $file->id;

        $file->delete();

        TelegramFileDeleted::dispatch($fileId, $diskPath);
    }

    /**
     * {@inheritDoc}
     */
    public function deleteDirectory(string $path): void
    {
        $prefix = rtrim($path, '/') . '/';

        TelegramFile::where('disk_path', 'like', $prefix . '%')->each(function (TelegramFile $file) {
            $this->delete($file->disk_path);
        });
    }

    /**
     * {@inheritDoc}
     */
    public function createDirectory(string $path, Config $config): void
    {
        // No-op: Telegram storage is flat, directories are virtual
    }

    /**
     * {@inheritDoc}
     */
    public function setVisibility(string $path, string $visibility): void
    {
        // No-op: Telegram doesn't support visibility concepts
    }

    /**
     * {@inheritDoc}
     */
    public function visibility(string $path): FileAttributes
    {
        return new FileAttributes($path, null, 'public');
    }

    /**
     * {@inheritDoc}
     */
    public function mimeType(string $path): FileAttributes
    {
        $file = $this->findFileOrFail($path, 'mime_type');

        return new FileAttributes($path, null, null, null, $file->mime_type);
    }

    /**
     * {@inheritDoc}
     */
    public function lastModified(string $path): FileAttributes
    {
        $file = $this->findFileOrFail($path, 'last_modified');

        return new FileAttributes($path, null, null, $file->updated_at?->getTimestamp());
    }

    /**
     * {@inheritDoc}
     */
    public function fileSize(string $path): FileAttributes
    {
        $file = $this->findFileOrFail($path, 'file_size');

        return new FileAttributes($path, $file->size);
    }

    /**
     * {@inheritDoc}
     */
    public function listContents(string $path, bool $deep): iterable
    {
        $prefix = $path === '' ? '' : rtrim($path, '/') . '/';

        $query = TelegramFile::where('status', 'available');

        if ($prefix !== '') {
            $query->where('disk_path', 'like', $prefix . '%');
        }

        foreach ($query->cursor() as $file) {
            $relativePath = $file->disk_path;

            if (! $deep && $prefix !== '') {
                // Only return direct children
                $remaining = substr($relativePath, strlen($prefix));
                if (str_contains($remaining, '/')) {
                    continue;
                }
            }

            yield new FileAttributes(
                $relativePath,
                $file->size,
                null,
                $file->updated_at?->getTimestamp(),
                $file->mime_type,
            );
        }
    }

    /**
     * {@inheritDoc}
     */
    public function move(string $source, string $destination, Config $config): void
    {
        $file = $this->findFileOrFail($source, 'move');
        $file->update(['disk_path' => $destination]);
    }

    /**
     * {@inheritDoc}
     */
    public function copy(string $source, string $destination, Config $config): void
    {
        $file = $this->findFileOrFail($source, 'copy');

        $newFile = $file->replicate();
        $newFile->id = null; // Let ULID auto-generate
        $newFile->disk_path = $destination;
        $newFile->download_token = Str::random(64);
        $newFile->save();

        // Copy chunk records if chunked
        if ($file->is_chunked) {
            foreach ($file->chunks as $chunk) {
                $newChunk = $chunk->replicate();
                $newChunk->id = null;
                $newChunk->file_id = $newFile->id;
                $newChunk->save();
            }
        }
    }

    /**
     * Generate a public URL for the file.
     */
    public function getUrl(string $path): string
    {
        $file = TelegramFile::where('disk_path', $path)->first();

        if (! $file) {
            return '';
        }

        $routePrefix = config('telegram-storage.download.route_prefix', 'tg-stream');
        $useSigned = config('telegram-storage.download.signed_urls', false);
        $cdnEnabled = config('telegram-storage.cdn.enabled', false);
        $cdnBaseUrl = config('telegram-storage.cdn.base_url', '');

        if ($useSigned) {
            $ttl = config('telegram-storage.download.url_ttl', 3600);
            $url = \Illuminate\Support\Facades\URL::temporarySignedRoute(
                'telegram-storage.stream',
                now()->addSeconds($ttl),
                ['token' => $file->download_token]
            );
        } else {
            $url = url($routePrefix . '/' . $file->download_token);
        }

        if ($cdnEnabled && $cdnBaseUrl) {
            $parsedUrl = parse_url($url);
            $url = rtrim($cdnBaseUrl, '/') . ($parsedUrl['path'] ?? '');
            if (isset($parsedUrl['query'])) {
                $url .= '?' . $parsedUrl['query'];
            }
        }

        return $url;
    }

    /**
     * Core upload logic: create DB records, enqueue to Redis.
     */
    protected function uploadFromContents(string $path, string $contents, Config $config): void
    {
        $tempPath = $this->chunkManager->getTempPath() . DIRECTORY_SEPARATOR . 'upload_' . Str::random(32);

        if (! is_dir($this->chunkManager->getTempPath())) {
            mkdir($this->chunkManager->getTempPath(), 0755, true);
        }

        file_put_contents($tempPath, $contents);

        try {
            $fileSize = strlen($contents);
            $mimeType = $this->detectMimeType($tempPath);
            $checksum = $this->verifier->checksumFile($tempPath);
            $channel = $this->channelRotator->select();
            $needsChunking = $this->chunkManager->needsChunking($fileSize);

            $fileRecord = TelegramFile::create([
                'disk_path' => $path,
                'original_name' => basename($path),
                'mime_type' => $mimeType,
                'size' => $fileSize,
                'checksum' => $checksum,
                'status' => 'pending',
                'channel_id' => $channel->channel_identifier,
                'is_chunked' => $needsChunking,
                'chunk_count' => $this->chunkManager->calculateChunkCount($fileSize),
                'download_token' => Str::random(64),
                'metadata' => $config->get('metadata'),
            ]);

            if ($needsChunking) {
                $chunks = $this->chunkManager->splitFile($tempPath, $mimeType);

                foreach ($chunks as $chunkMeta) {
                    $chunkChannel = $this->channelRotator->select();

                    TelegramFileChunk::create([
                        'file_id' => $fileRecord->id,
                        'chunk_index' => $chunkMeta['index'],
                        'channel_id' => $chunkChannel->channel_identifier,
                        'size' => $chunkMeta['size'],
                        'checksum' => $chunkMeta['checksum'],
                        'is_compressed' => $chunkMeta['is_compressed'],
                        'encryption_iv' => $chunkMeta['encryption_iv'],
                        'status' => 'pending',
                    ]);

                    $this->enqueueUpload($fileRecord, $chunkMeta['index'], $chunkMeta['path'], $chunkChannel->channel_identifier);
                }
            } else {
                $this->enqueueUpload($fileRecord, null, $tempPath, $channel->channel_identifier);
            }

            TelegramUploadQueued::dispatch($fileRecord->id, $path);
        } catch (\Throwable $e) {
            if (file_exists($tempPath)) {
                unlink($tempPath);
            }
            throw UnableToWriteFile::atLocation($path, $e->getMessage(), $e);
        }
    }

    /**
     * Enqueue an upload job to the Redis queue for the Python worker.
     */
    protected function enqueueUpload(TelegramFile $file, ?int $chunkIndex, string $tempPath, string $channelId): void
    {
        $payload = json_encode([
            'job_id' => Str::ulid()->toString(),
            'file_record_id' => $file->id,
            'chunk_index' => $chunkIndex,
            'temp_path' => $tempPath,
            'channel_id' => $channelId,
            'callback_url' => config('telegram-storage.worker_callback_url'),
            'hmac_secret' => config('telegram-storage.worker_callback_secret'),
        ]);

        $connection = config('telegram-storage.redis.connection', 'default');
        $queueKey = config('telegram-storage.redis.queue_key', 'telegram_upload_queue');

        Redis::connection($connection)->rpush($queueKey, $payload);
    }

    /**
     * Detect MIME type using PHP's finfo.
     */
    protected function detectMimeType(string $filePath): ?string
    {
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($filePath);

        return $mime !== false ? $mime : null;
    }

    /**
     * Find a TelegramFile record or throw an appropriate exception.
     */
    protected function findFileOrFail(string $path, string $operation): TelegramFile
    {
        $file = TelegramFile::where('disk_path', $path)->first();

        if (! $file) {
            throw match ($operation) {
                'read' => UnableToReadFile::fromLocation($path, 'File not found.'),
                'mime_type' => UnableToRetrieveMetadata::mimeType($path),
                'last_modified' => UnableToRetrieveMetadata::lastModified($path),
                'file_size' => UnableToRetrieveMetadata::fileSize($path),
                'move', 'copy' => UnableToReadFile::fromLocation($path, 'File not found.'),
                default => new \RuntimeException("File not found: {$path}"),
            };
        }

        return $file;
    }
}

