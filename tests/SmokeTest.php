<?php

declare(strict_types=1);

namespace BoxinCode\FilamentSiteKit\Tests;

final class SmokeTest extends TestCase
{
    public function test_config_is_loaded(): void
    {
        $this->assertIsArray(config('filament-sitekit.google.scopes'));
        $this->assertSame(3600, config('filament-sitekit.cache.ttl_seconds'));
    }

    public function test_oauth_routes_are_registered(): void
    {
        $this->assertSame(
            '/filament-sitekit/google/connect',
            route('filament-sitekit.google.connect', [], false)
        );

        $this->assertSame(
            '/filament-sitekit/google/callback',
            route('filament-sitekit.google.callback', [], false)
        );
    }

    public function test_commands_are_registered(): void
    {
        $this->artisan('list')
            ->expectsOutputToContain('sitekit:install')
            ->expectsOutputToContain('sitekit:sync')
            ->expectsOutputToContain('sitekit:doctor')
            ->assertExitCode(0);
    }
}
