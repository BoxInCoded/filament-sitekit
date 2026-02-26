<?php

declare(strict_types=1);

namespace BoxinCode\FilamentSiteKit\Filament\Pages;

use BoxinCode\FilamentSiteKit\Models\SiteKitAccount;
use BoxinCode\FilamentSiteKit\SiteKitAccountManager;
use BoxinCode\FilamentSiteKit\SiteKitLicense;
use BoxinCode\FilamentSiteKit\SiteKitManager;
use BoxinCode\FilamentSiteKit\Support\SiteKitSetupStatus;
use BoxinCode\FilamentSiteKit\Support\SiteKitStatus;
use BoxinCode\FilamentSiteKit\Support\SiteKitTrackingInstaller;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Gate;

class SiteKitSetupWizard extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-sparkles';

    protected static ?string $navigationGroup = 'Site Kit';

    protected static ?int $navigationSort = 2;

    protected static ?string $title = 'Site Kit Setup';

    protected string $view = 'filament-sitekit::pages.setup-wizard';

    public int $step = 1;

    public bool $googleConnected = false;

    public bool $analyticsConfigured = false;

    public bool $searchConsoleConfigured = false;

    public bool $trackingDetected = false;

    public bool $googleFailed = false;

    public bool $installTrackingAutomatically = true;

    public string $trackingType = 'ga4';

    public string $trackingMeasurementId = '';

    public string $trackingContainerId = '';

    public ?string $ga4_property_id = null;

    public ?string $gsc_site_url = null;

    /**
     * @var array<string, string>
     */
    public array $ga4Options = [];

    /**
     * @var array<string, string>
     */
    public array $gscOptions = [];

    public static function shouldRegisterNavigation(): bool
    {
        try {
            return ! app(SiteKitStatus::class)->isConfigured();
        } catch (\Throwable) {
            return true;
        }
    }

    public static function getNavigationBadge(): ?string
    {
        try {
            return app(SiteKitStatus::class)->isConfigured() ? 'Completed' : null;
        } catch (\Throwable) {
            return null;
        }
    }

    public function mount(SiteKitManager $manager): void
    {
        $this->authorizeAccess();

        $this->googleFailed = (string) request()->query('google', '') === 'failed';

        $this->hydrateOptions($manager);
        $this->refreshStatus();

        if ((string) request()->query('setup', '') === '1') {
            $this->startSetup($manager, true);
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('settings')
                ->label('Open Settings')
                ->icon('heroicon-o-cog-6-tooth')
                ->url(SiteKitSettings::getUrl()),
            Action::make('dashboard')
                ->label('Go to Dashboard')
                ->icon('heroicon-o-chart-bar')
                ->url(SiteKitDashboard::getUrl()),
        ];
    }

    public function startSetup(SiteKitManager $manager, bool $silent = false): void
    {
        $account = $this->currentAccount();

        if (! $account) {
            redirect()->to(route('filament-sitekit.google.connect', ['redirect' => static::getUrl(['setup' => 1])]));

            return;
        }

        $this->hydrateOptions($manager);
        $this->autoDetectGa4($manager);
        $this->autoDetectGsc($manager);
        $this->refreshStatus();

        if (! $this->analyticsConfigured) {
            $this->step = 2;
        } elseif (! $this->searchConsoleConfigured) {
            $this->step = 3;
        } else {
            $this->step = 5;
        }

        if (! $silent) {
            Notification::make()->success()->title('Setup in progress')->body('Auto-detection completed.')->send();
        }
    }

    public function saveGa4(SiteKitManager $manager, bool $notify = true): void
    {
        $account = $this->currentAccount();

        if (! $account) {
            return;
        }

        if (! Gate::allows('configureConnectors', $account)) {
            abort(403);
        }

        if (! $this->ga4_property_id) {
            if ($notify) {
                Notification::make()->warning()->title('Choose GA4 property')->send();
            }

            return;
        }

        $manager->setAccountSetting($account, 'ga4_property_id', ['value' => $this->ga4_property_id]);

        $prefill = $this->extractMeasurementId($this->ga4_property_id);

        if ($prefill !== null) {
            $this->trackingMeasurementId = $prefill;
            $this->trackingType = 'ga4';
        }

        if ($notify) {
            Notification::make()->success()->title('GA4 property saved')->send();
        }

        $this->refreshStatus();

        if ($this->analyticsConfigured) {
            $this->autoDetectGsc($manager);
            $this->refreshStatus();

            if ($this->searchConsoleConfigured) {
                $this->step = 5;
            } else {
                $this->step = 3;
            }
        }
    }

    public function saveGsc(SiteKitManager $manager, bool $notify = true): void
    {
        $account = $this->currentAccount();

        if (! $account) {
            return;
        }

        if (! Gate::allows('configureConnectors', $account)) {
            abort(403);
        }

        if (! $this->gsc_site_url) {
            if ($notify) {
                Notification::make()->warning()->title('Choose Search Console site')->send();
            }

            return;
        }

        $manager->setAccountSetting($account, 'gsc_site_url', ['value' => $this->gsc_site_url]);

        if ($notify) {
            Notification::make()->success()->title('Search Console site saved')->send();
        }

        $this->refreshStatus();

        if ($this->searchConsoleConfigured) {
            $this->step = 5;
        }
    }

    public function finish(SiteKitManager $manager, SiteKitTrackingInstaller $trackingInstaller): void
    {
        if (! app(SiteKitSetupStatus::class)->setupComplete()) {
            Notification::make()
                ->warning()
                ->title('Setup incomplete')
                ->body('Please complete all setup steps before finishing.')
                ->send();

            return;
        }

        $account = $this->currentAccount();

        if ($account && $this->installTrackingAutomatically) {
            if ($this->trackingType === 'gtm' && trim($this->trackingContainerId) !== '') {
                $trackingInstaller->installGtm($account, trim($this->trackingContainerId));
            }

            if ($this->trackingType === 'ga4' && trim($this->trackingMeasurementId) !== '') {
                $trackingInstaller->installGa4($account, trim($this->trackingMeasurementId));
            }
        }

        redirect(SiteKitDashboard::getUrl());
    }

    protected function autoDetectGa4(SiteKitManager $manager): void
    {
        if ($this->ga4_property_id) {
            return;
        }

        $account = $this->currentAccount();

        if (! $account) {
            return;
        }

        if ($this->ga4Options === []) {
            return;
        }

        if (count($this->ga4Options) === 1) {
            $this->ga4_property_id = (string) array_key_first($this->ga4Options);
            $this->saveGa4($manager, false);

            return;
        }

        $match = $this->detectMatchingGa4PropertyId();

        if ($match !== null) {
            $this->ga4_property_id = $match;
            $this->saveGa4($manager, false);
        }
    }

    protected function autoDetectGsc(SiteKitManager $manager): void
    {
        if (app(SiteKitLicense::class)->isFree()) {
            return;
        }

        if ($this->gsc_site_url) {
            return;
        }

        $account = $this->currentAccount();

        if (! $account) {
            return;
        }

        if ($this->gscOptions === []) {
            return;
        }

        if (count($this->gscOptions) === 1) {
            $this->gsc_site_url = (string) array_key_first($this->gscOptions);
            $this->saveGsc($manager, false);

            return;
        }

        $match = $this->detectMatchingGscSiteUrl();

        if ($match !== null) {
            $this->gsc_site_url = $match;
            $this->saveGsc($manager, false);
        }
    }

    protected function detectMatchingGa4PropertyId(): ?string
    {
        $host = $this->targetHost();

        if ($host === null) {
            return null;
        }

        foreach ($this->ga4Options as $propertyId => $label) {
            $normalized = $this->normalizeDomain((string) $label);

            if ($normalized === $host || str_contains($normalized, $host) || str_contains($host, $normalized)) {
                return (string) $propertyId;
            }
        }

        return null;
    }

    protected function detectMatchingGscSiteUrl(): ?string
    {
        $host = $this->targetHost();

        if ($host === null) {
            return null;
        }

        foreach ($this->gscOptions as $siteUrl => $label) {
            $candidate = parse_url((string) $siteUrl, PHP_URL_HOST);
            $normalized = $this->normalizeDomain((string) ($candidate ?: $label));

            if ($normalized === $host || str_contains($normalized, $host) || str_contains($host, $normalized)) {
                return (string) $siteUrl;
            }
        }

        return null;
    }

    protected function targetHost(): ?string
    {
        $account = $this->currentAccount();

        if (! $account) {
            return null;
        }

        $siteSetting = app(SiteKitManager::class)->getAccountSetting($account, 'site_url');
        $siteUrl = is_array($siteSetting) ? (string) ($siteSetting['value'] ?? '') : (string) $siteSetting;

        if ($siteUrl === '') {
            $siteUrl = (string) config('app.url');
        }

        $host = parse_url($siteUrl, PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            return null;
        }

        return $this->normalizeDomain($host);
    }

    protected function normalizeDomain(string $value): string
    {
        $domain = strtolower(trim($value));
        $domain = preg_replace('/^https?:\/\//', '', $domain) ?? $domain;
        $domain = preg_replace('/^www\./', '', $domain) ?? $domain;

        return trim($domain, '/');
    }

    protected function extractMeasurementId(?string $value): ?string
    {
        if (! $value) {
            return null;
        }

        if (preg_match('/(G-[A-Z0-9]{6,})/i', $value, $matches) === 1) {
            return strtoupper((string) $matches[1]);
        }

        $label = $this->ga4Options[$value] ?? '';

        if (is_string($label) && preg_match('/(G-[A-Z0-9]{6,})/i', $label, $matches) === 1) {
            return strtoupper((string) $matches[1]);
        }

        return null;
    }

    protected function refreshStatus(): void
    {
        $status = app(SiteKitSetupStatus::class);

        $this->googleConnected = $status->isConnected();
        $this->analyticsConfigured = $status->analyticsConfigured();
        $this->searchConsoleConfigured = $status->searchConsoleConfigured();
        $this->trackingDetected = $status->trackingDetected();
    }

    protected function hydrateOptions(SiteKitManager $manager): void
    {
        $account = $this->currentAccount();

        if (! $account) {
            $this->ga4Options = [];
            $this->gscOptions = [];
            $this->ga4_property_id = null;
            $this->gsc_site_url = null;

            return;
        }

        $this->ga4Options = collect($manager->listGa4Properties($account))
            ->mapWithKeys(fn (array $property): array => [
                (string) $property['propertyId'] => (string) $property['displayName'],
            ])
            ->all();

        $this->gscOptions = collect($manager->listGscSites($account))
            ->mapWithKeys(fn (array $site): array => [
                (string) $site['siteUrl'] => (string) $site['siteUrl'],
            ])
            ->all();

        $ga = $manager->getAccountSetting($account, 'ga4_property_id');
        $gsc = $manager->getAccountSetting($account, 'gsc_site_url');

        $this->ga4_property_id = is_array($ga) ? ($ga['value'] ?? null) : (is_string($ga) ? $ga : null);
        $this->gsc_site_url = is_array($gsc) ? ($gsc['value'] ?? null) : (is_string($gsc) ? $gsc : null);
    }

    protected function authorizeAccess(): void
    {
        abort_unless(Filament::auth()->check(), 403);
        abort_unless(Gate::allows((string) config('filament-sitekit.authorization.gate', 'manageSiteKit')), 403);

        $account = $this->currentAccount();

        if ($account && ! Gate::allows('configureConnectors', $account)) {
            abort(403);
        }
    }

    protected function currentAccount(): ?SiteKitAccount
    {
        return app(SiteKitAccountManager::class)->current();
    }
}
