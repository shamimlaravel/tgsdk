<?php

namespace Shamimstack\Tgsdk\Tests;

use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [\Shamimstack\Tgsdk\TelegramStorageServiceProvider::class];
    }

    protected function getPackageAliases($app): array
    {
        return ['TelegramStorage' => \Shamimstack\Tgsdk\Facades\TelegramStorage::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        $app['config']->set('telegram-storage.channels', ['-1001234567890']);
        $app['config']->set('telegram-storage.rotation_strategy', 'round-robin');
        $app['config']->set('telegram-storage.worker_callback_secret', 'test-secret');
        $app['config']->set('telegram-storage.temp_path', sys_get_temp_dir() . '/telegram-storage-test');
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }
}
