<?php

declare(strict_types=1);

namespace BoxinCode\FilamentSiteKit;

use BoxInCoded\FilamentSiteKit\Core\Contracts\PlanGuard;
use BoxInCoded\FilamentSiteKit\Core\Support\ExtensionsRegistry;
use BoxInCoded\FilamentSiteKit\Core\Support\Guards\FreePlanGuard;
use BoxinCode\FilamentSiteKit\Commands\SiteKitDoctorCommand;
use BoxinCode\FilamentSiteKit\Commands\SiteKitInstallCommand;
use BoxinCode\FilamentSiteKit\Commands\SiteKitSyncCommand;
use BoxinCode\FilamentSiteKit\Contracts\TokenStore;
use BoxinCode\FilamentSiteKit\Filament\Plugin as SiteKitPlugin;
use BoxinCode\FilamentSiteKit\Http\Middleware\SiteKitTrackingMiddleware;
use BoxinCode\FilamentSiteKit\Models\SiteKitAccount;
use BoxinCode\FilamentSiteKit\OAuth\GoogleOAuthClient;
use BoxinCode\FilamentSiteKit\Policies\SiteKitAccountPolicy;
use BoxinCode\FilamentSiteKit\SiteKitLicense;
use BoxinCode\FilamentSiteKit\Support\Filament\FilamentCompat;
use BoxinCode\FilamentSiteKit\Support\Filament\FilamentVersion;
use BoxinCode\FilamentSiteKit\Support\EloquentTokenStore;
use BoxinCode\FilamentSiteKit\Support\SiteKitHealthService;
use BoxinCode\FilamentSiteKit\Support\SiteKitSetupStatus;
use BoxinCode\FilamentSiteKit\Support\SiteKitStatus;
use BoxinCode\FilamentSiteKit\Support\SiteKitTrackingDetector;
use BoxinCode\FilamentSiteKit\Support\SiteKitTrackingInstaller;
use BoxinCode\FilamentSiteKit\Support\SiteKitTrackingScripts;
use BoxinCode\FilamentSiteKit\Support\SiteKitTrackingVerifyService;
use BoxinCode\FilamentSiteKit\Support\UpgradeUi;
use Filament\Facades\Filament;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Throwable;

class FilamentSiteKitServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/filament-sitekit.php', 'filament-sitekit');

        $this->app->instance('filament-sitekit.version', FilamentSiteKit::VERSION);
        $this->app->singleton(ExtensionsRegistry::class, ExtensionsRegistry::class);
        $this->app->singleton(PlanGuard::class, FreePlanGuard::class);

        $this->app->singleton(TokenStore::class, EloquentTokenStore::class);
        $this->app->singleton(GoogleOAuthClient::class, GoogleOAuthClient::class);
        $this->app->singleton(ConnectorRegistry::class, ConnectorRegistry::class);
        $this->app->singleton(SiteKitAccountManager::class, SiteKitAccountManager::class);
        $this->app->singleton(SiteKitTokenService::class, SiteKitTokenService::class);
        $this->app->singleton(SiteKitManager::class, SiteKitManager::class);
        $this->app->singleton(SiteKitLicense::class, SiteKitLicense::class);
        $this->app->singleton(SiteKitPlatform::class, SiteKitPlatform::class);
        $this->app->singleton(FilamentVersion::class, FilamentVersion::class);
        $this->app->singleton(FilamentCompat::class, FilamentCompat::class);
        $this->app->singleton(SiteKitStatus::class, SiteKitStatus::class);
        $this->app->singleton(SiteKitTrackingDetector::class, SiteKitTrackingDetector::class);
        $this->app->singleton(SiteKitTrackingScripts::class, SiteKitTrackingScripts::class);
        $this->app->singleton(SiteKitTrackingInstaller::class, SiteKitTrackingInstaller::class);
        $this->app->singleton(SiteKitTrackingVerifyService::class, SiteKitTrackingVerifyService::class);
        $this->app->singleton(SiteKitHealthService::class, SiteKitHealthService::class);
        $this->app->singleton(SiteKitSetupStatus::class, SiteKitSetupStatus::class);
        $this->app->singleton(UpgradeUi::class, UpgradeUi::class);
        $this->app->alias(SiteKitPlatform::class, 'filament-sitekit');

        $this->registerGate();
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'filament-sitekit');
        $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        $this->registerFilamentPlugin();
        $this->registerAccountSwitcherHook();
        $this->registerTrackingMiddleware();

        $this->publishes([
            __DIR__ . '/../config/filament-sitekit.php' => config_path('filament-sitekit.php'),
        ], 'filament-sitekit-config');

        $this->publishes([
            __DIR__ . '/../config/filament-sitekit.php' => config_path('filament-sitekit.php'),
        ], 'sitekit-config');

        $this->publishes([
            __DIR__ . '/../database/migrations' => database_path('migrations'),
        ], 'filament-sitekit-migrations');

        $this->publishes([
            __DIR__ . '/../database/migrations' => database_path('migrations'),
        ], 'sitekit-migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([
                SiteKitInstallCommand::class,
                SiteKitSyncCommand::class,
                SiteKitDoctorCommand::class,
            ]);
        }

        $this->app->booted(function (): void {
            if (! $this->app->runningInConsole()) {
                return;
            }

            if (! config('filament-sitekit.sync.enabled', true)) {
                return;
            }

            if (! config('filament-sitekit.sync.auto_schedule', false)) {
                return;
            }

            $schedule = $this->app->make(Schedule::class);
            $frequency = (string) config('filament-sitekit.sync.schedule', 'hourly');

            $event = $schedule->command('sitekit:sync');

            match ($frequency) {
                'daily' => $event->daily(),
                'everyTwoHours' => $event->everyTwoHours(),
                default => $event->hourly(),
            };
        });
    }

    protected function registerFilamentPlugin(): void
    {
        try {
            $filament = Filament::getFacadeRoot();

            if ($filament && method_exists($filament, 'registerPlugin')) {
                $filament->registerPlugin(new SiteKitPlugin());
            }
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    protected function registerGate(): void
    {
        Gate::policy(SiteKitAccount::class, SiteKitAccountPolicy::class);

        $ability = (string) config('filament-sitekit.authorization.gate', 'manageSiteKit');

        Gate::define($ability, function ($user): bool {
            if (! $user) {
                return false;
            }

            if (method_exists($user, 'isSuperAdmin')) {
                return (bool) $user->isSuperAdmin();
            }

            return true;
        });
    }

    protected function registerAccountSwitcherHook(): void
    {
        try {
            $filament = Filament::getFacadeRoot();
            $hook = class_exists('Filament\\View\\PanelsRenderHook')
                ? \Filament\View\PanelsRenderHook::TOPBAR_END
                : 'panels::topbar.end';

            if ($filament && method_exists($filament, 'registerRenderHook')) {
                $filament->registerRenderHook(
                    $hook,
                    fn (): string => (string) view('filament-sitekit::partials.account-switcher')
                );
            }
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    protected function registerTrackingMiddleware(): void
    {
        if ($this->app->runningInConsole()) {
            return;
        }

        if (! config('filament-sitekit.tracking.enabled', true)) {
            return;
        }

        if ((string) config('filament-sitekit.tracking.method', 'middleware') !== 'middleware') {
            return;
        }

        /** @var Router $router */
        $router = $this->app->make(Router::class);
        $router->aliasMiddleware('sitekit.tracking', SiteKitTrackingMiddleware::class);
        $router->pushMiddlewareToGroup('web', SiteKitTrackingMiddleware::class);
    }
}
