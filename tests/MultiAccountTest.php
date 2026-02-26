<?php

declare(strict_types=1);

namespace BoxinCode\FilamentSiteKit\Tests;

use BoxinCode\FilamentSiteKit\SiteKitAccountManager;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Support\Facades\Schema;

final class MultiAccountTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('sitekit_accounts')) {
            Schema::create('sitekit_accounts', function ($table): void {
                $table->id();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->unsignedBigInteger('workspace_id')->nullable();
                $table->string('provider');
                $table->string('email')->nullable();
                $table->string('display_name')->nullable();
                $table->string('name')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('sitekit_account_users')) {
            Schema::create('sitekit_account_users', function ($table): void {
                $table->id();
                $table->unsignedBigInteger('account_id');
                $table->unsignedBigInteger('user_id');
                $table->string('role', 16)->default('viewer');
                $table->timestamps();
            });
        }

        session()->start();
    }

    public function test_account_switch_route_is_registered(): void
    {
        $this->assertSame(
            '/filament-sitekit/accounts/switch',
            route('filament-sitekit.accounts.switch', [], false)
        );
    }

    public function test_current_and_set_current_work_per_user(): void
    {
        $guard = $this->mockGuard(1);
        $this->bindFilamentRoot($guard);

        $firstId = (int) $this->app['db']->table('sitekit_accounts')->insertGetId([
            'user_id' => 1,
            'provider' => 'google',
            'email' => 'client-a@example.com',
            'display_name' => 'Client A',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $secondId = (int) $this->app['db']->table('sitekit_accounts')->insertGetId([
            'user_id' => 1,
            'provider' => 'google',
            'email' => 'client-b@example.com',
            'display_name' => 'Client B',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $otherUserId = (int) $this->app['db']->table('sitekit_accounts')->insertGetId([
            'user_id' => 2,
            'provider' => 'google',
            'email' => 'other@example.com',
            'display_name' => 'Other User',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $manager = app(SiteKitAccountManager::class);

        $current = $manager->current();
        $this->assertNotNull($current);
        $this->assertSame(1, $current->user_id);

        $this->assertTrue($manager->setCurrent($secondId));
        $this->assertSame($secondId, optional($manager->current())->id);

        $this->assertFalse($manager->setCurrent($otherUserId));
        $this->assertSame($secondId, optional($manager->current())->id);

        $manager->clearCurrent();
        $fallback = $manager->current();
        $this->assertNotNull($fallback);
        $this->assertSame(1, $fallback->user_id);

        $this->assertTrue(in_array(optional($fallback)->id, [$firstId, $secondId], true));
    }

    private function mockGuard(int $userId): Guard
    {
        /** @var Guard&\Mockery\MockInterface $guard */
        $guard = \Mockery::mock(Guard::class);
        $guard->shouldReceive('check')->andReturn(true);
        $guard->shouldReceive('id')->andReturn($userId);

        return $guard;
    }

    private function bindFilamentRoot(Guard $guard): void
    {
        $filament = new class($guard) {
            public function __construct(private Guard $guard)
            {
            }

            public function auth(): Guard
            {
                return $this->guard;
            }
        };

        $this->app->instance('filament', $filament);
    }
}
