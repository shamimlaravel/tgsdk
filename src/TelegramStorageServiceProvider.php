<?php

namespace Shamimstack\Tgsdk;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;
use League\Flysystem\Filesystem;

class TelegramStorageServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/telegram-storage.php', 'telegram-storage');

        $this->app->singleton(IntegrityVerifier::class, fn () => new IntegrityVerifier());

        $this->app->singleton(ChannelRotator::class, fn () => new ChannelRotator());

        $this->app->singleton(ChunkManager::class, fn ($app) => new ChunkManager($app->make(IntegrityVerifier::class)));

        $this->app->singleton(TelegramStorageAdapter::class, fn ($app) => new TelegramStorageAdapter(
            $app->make(ChannelRotator::class),
            $app->make(ChunkManager::class),
            $app->make(IntegrityVerifier::class),
        ));

        $this->app->bind('telegram-storage', fn ($app) => $app->make(TelegramStorageAdapter::class));
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/telegram-storage.php' => config_path('telegram-storage.php'),
        ], 'telegram-storage-config');

        $this->publishes([
            __DIR__ . '/../database/migrations/' => database_path('migrations'),
        ], 'telegram-storage-migrations');

        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        $this->loadRoutesFrom(__DIR__ . '/../routes/telegram-storage.php');

        Storage::extend('telegram', function ($app, $config) {
            $adapter = $app->make(TelegramStorageAdapter::class);
            $filesystem = new Filesystem($adapter, $config);

            return new FilesystemAdapter($filesystem, $adapter, $config);
        });
    }
}
