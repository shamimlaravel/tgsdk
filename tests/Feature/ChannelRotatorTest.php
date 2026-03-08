<?php

namespace Shamimstack\Tgsdk\Tests\Feature;

use Shamimstack\Tgsdk\ChannelRotator;
use Shamimstack\Tgsdk\Models\TelegramChannel;
use Shamimstack\Tgsdk\Tests\TestCase;

class ChannelRotatorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Skip tests if Redis extension is not available
        if (!extension_loaded('redis')) {
            $this->markTestSkipped('Redis extension not loaded. Install php-redis extension for full test coverage.');
        }
        TelegramChannel::create(['channel_identifier' => '-100111', 'label' => 'A', 'is_active' => true, 'priority' => 0, 'total_files' => 10, 'total_bytes' => 500000]);
        TelegramChannel::create(['channel_identifier' => '-100222', 'label' => 'B', 'is_active' => true, 'priority' => 1, 'total_files' => 5, 'total_bytes' => 200000]);
        TelegramChannel::create(['channel_identifier' => '-100333', 'label' => 'C', 'is_active' => false, 'priority' => 0, 'total_files' => 0, 'total_bytes' => 0]);
    }

    public function test_select_returns_active_channel(): void
    {
        $this->app['config']->set('telegram-storage.rotation_strategy', 'round-robin');
        $channel = (new ChannelRotator())->select();
        $this->assertTrue($channel->is_active);
    }

    public function test_least_used_selects_channel_with_fewest_files(): void
    {
        $this->app['config']->set('telegram-storage.rotation_strategy', 'least-used');
        $channel = (new ChannelRotator())->select();
        $this->assertSame('-100222', $channel->channel_identifier);
    }

    public function test_capacity_aware_selects_channel_with_least_bytes(): void
    {
        $this->app['config']->set('telegram-storage.rotation_strategy', 'capacity-aware');
        $channel = (new ChannelRotator())->select();
        $this->assertSame('-100222', $channel->channel_identifier);
    }

    public function test_inactive_channels_are_excluded(): void
    {
        $this->app['config']->set('telegram-storage.rotation_strategy', 'least-used');
        $channel = (new ChannelRotator())->select();
        $this->assertNotSame('-100333', $channel->channel_identifier);
    }

    public function test_throws_when_no_active_channels(): void
    {
        TelegramChannel::query()->update(['is_active' => false]);
        $this->app['config']->set('telegram-storage.rotation_strategy', 'round-robin');
        $this->expectException(\RuntimeException::class);
        (new ChannelRotator())->select();
    }
}
