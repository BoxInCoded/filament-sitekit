<?php

declare(strict_types=1);

namespace BoxinCode\FilamentSiteKit\Filament\Pages;

use BoxinCode\FilamentSiteKit\Models\SiteKitAccount;
use BoxinCode\FilamentSiteKit\SiteKitAccountManager;
use BoxinCode\FilamentSiteKit\SiteKitLicense;
use BoxinCode\FilamentSiteKit\SiteKitManager;
use BoxinCode\FilamentSiteKit\Support\SiteKitTrackingInstaller;
use BoxinCode\FilamentSiteKit\Support\SiteKitTrackingScripts;
use BoxinCode\FilamentSiteKit\Support\SiteKitTrackingVerifyService;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Gate;

class SiteKitSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationGroup = 'Site Kit';

    protected static ?int $navigationSort = 4;

    protected static ?string $title = 'Site Kit Settings';

    protected string $view = 'filament-sitekit::pages.settings';

    public ?array $data = [];

    public ?string $connectedEmail = null;

    public ?string $connectedName = null;

    public string $connectionStatus = 'Not connected';

    public string $ga4Status = 'Needs setup';

    public string $gscStatus = 'Needs setup';

    public string $currentPlan = 'Free';

    public bool $multiAccountLocked = true;

    public bool $trackingInstalled = false;

    public bool $trackingEnabled = false;

    public string $trackingType = 'ga4';

    public string $trackingMeasurementId = '';

    public string $trackingContainerId = '';

    public string $resolvedWebsiteUrl = '';

    public ?string $trackingLastVerifyAt = null;

    public string $trackingLastSummary = 'No verification yet.';

    public bool $trackingLastDetected = false;

    public string $manualSnippet = '';

    public string $manualHeadSnippet = '';

    public string $manualBodySnippet = '';

    /**
     * @var array<string, string>
     */
    public array $ga4Options = [];

    /**
     * @var array<string, string>
     */
    public array $gscOptions = [];

    public function mount(SiteKitManager $manager): void
    {
        $this->authorizeAccess();

        $license = app(SiteKitLicense::class);
        $this->currentPlan = ucfirst($license->plan());
        $this->multiAccountLocked = ! $license->allowsMultipleAccounts();

        $account = $this->currentAccount();

        if ($account) {
            $this->connectedEmail = $account->email;
            $this->connectedName = $account->display_name;
            $this->connectionStatus = $manager->isConnected($account) ? 'Connected' : 'Connection issue';

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

            $this->data = [
                'ga4_property_id' => $manager->getAccountSetting($account, 'ga4_property_id')['value'] ?? null,
                'gsc_site_url' => $manager->getAccountSetting($account, 'gsc_site_url')['value'] ?? null,
                'site_url' => $this->settingString($manager->getAccountSetting($account, 'site.url')),
            ];

            $this->ga4Status = ! empty($this->data['ga4_property_id']) ? 'Connected' : 'Needs setup';
            $this->gscStatus = ! empty($this->data['gsc_site_url']) ? 'Connected' : 'Needs setup';

            $tracking = app(SiteKitTrackingInstaller::class)->config($account);
            $this->trackingInstalled = app(SiteKitTrackingInstaller::class)->isConfigured($account);
            $this->trackingEnabled = (bool) $tracking['enabled'];
            $this->trackingType = (string) ($tracking['type'] ?? 'ga4');
            $this->trackingMeasurementId = (string) ($tracking['measurement_id'] ?? '');
            $this->trackingContainerId = (string) ($tracking['container_id'] ?? '');

            $lastResult = $manager->getAccountSetting($account, 'tracking.last_verify');
            $lastResult = is_array($lastResult) ? ($lastResult['value'] ?? $lastResult) : $lastResult;
            $lastResult = is_array($lastResult) ? $lastResult : [];

            if ($lastResult !== []) {
                $this->trackingLastDetected = $this->detectFromResult($lastResult, $this->trackingType);
                $this->trackingLastSummary = (string) ($lastResult['message'] ?? $this->trackingLastSummary);
                $this->trackingLastVerifyAt = (string) ($lastResult['checked_at'] ?? null) ?: null;
            }

            $this->resolvedWebsiteUrl = $this->resolveWebsiteUrl($account, $manager);
            $this->refreshManualSnippet();
        } else {
            $this->connectionStatus = 'Not connected';
        }

        $this->form->fill($this->data);
    }

    public function form(Form $form): Form
    {
        return $form
            ->statePath('data')
            ->schema([
                Forms\Components\Section::make('Google Connection')
                    ->description('Manage your Google account connection and profile status.')
                    ->schema([
                        Forms\Components\Placeholder::make('connection_status')
                            ->label('Status')
                            ->content($this->connectionStatus),
                        Forms\Components\Placeholder::make('connection_name')
                            ->label('Connected user')
                            ->content($this->connectedName ?? '—'),
                        Forms\Components\Placeholder::make('connection_email')
                            ->label('Email')
                            ->content($this->connectedEmail ?? '—'),
                    ]),

                Forms\Components\Section::make('Website')
                    ->schema([
                        Forms\Components\TextInput::make('site_url')
                            ->label('Website URL')
                            ->url()
                            ->placeholder('https://example.com'),
                    ]),

                Forms\Components\Section::make('Google Analytics 4')
                    ->schema([
                        Forms\Components\Placeholder::make('ga4_status')
                            ->label('Module status')
                            ->content($this->ga4Status),
                        Forms\Components\Select::make('ga4_property_id')
                            ->label('GA4 Property')
                            ->options($this->ga4Options)
                            ->searchable()
                            ->placeholder('Select property'),
                    ]),

                Forms\Components\Section::make('Search Console')
                    ->schema([
                        Forms\Components\Placeholder::make('gsc_status')
                            ->label('Module status')
                            ->content($this->gscStatus),
                        Forms\Components\Select::make('gsc_site_url')
                            ->label('Search Console Site URL')
                            ->options($this->gscOptions)
                            ->searchable()
                            ->placeholder('Select site URL'),
                    ]),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('upgrade')
                ->label('Upgrade')
                ->icon('heroicon-o-credit-card')
                ->color('gray')
                ->url(SiteKitPlans::getUrl())
                ->visible(fn (): bool => app(SiteKitLicense::class)->isFree()),
            Action::make('connect_google')
                ->label('Connect Google')
                ->icon('heroicon-o-link')
                ->url(route('filament-sitekit.google.connect')),
            Action::make('disconnect_google')
                ->label('Disconnect')
                ->icon('heroicon-o-no-symbol')
                ->requiresConfirmation()
                ->url(route('filament-sitekit.google.disconnect'))
                ->visible(fn (): bool => $this->currentAccount() !== null),
            Action::make('setup_wizard')
                ->label('Go to Setup Wizard')
                ->icon('heroicon-o-sparkles')
                ->url(SiteKitSetupWizard::getUrl()),
        ];
    }

    public function save(SiteKitManager $manager): void
    {
        $this->authorizeAccess();

        $account = $this->currentAccount();

        if (! $account) {
            Notification::make()
                ->danger()
                ->title('Not connected')
                ->body('Connect Google before saving settings.')
                ->send();

            return;
        }

        if (! Gate::allows('configureConnectors', $account)) {
            abort(403);
        }

        $state = $this->form->getState();

        $manager->setAccountSetting($account, 'ga4_property_id', ['value' => $state['ga4_property_id'] ?? null]);
        $manager->setAccountSetting($account, 'gsc_site_url', ['value' => $state['gsc_site_url'] ?? null]);
        $manager->setAccountSetting($account, 'site.url', ['value' => $state['site_url'] ?? null]);
        $this->resolvedWebsiteUrl = $this->resolveWebsiteUrl($account, $manager);

        Notification::make()
            ->success()
            ->title('Settings saved')
            ->body('Site Kit settings have been updated.')
            ->send();
    }

    public function installGa4Tracking(SiteKitTrackingInstaller $installer): void
    {
        $account = $this->currentAccount();

        if (! $account || ! Gate::allows('configureConnectors', $account)) {
            return;
        }

        $measurementId = trim($this->trackingMeasurementId);

        if ($measurementId === '') {
            Notification::make()->warning()->title('Measurement ID required')->send();

            return;
        }

        $installer->installGa4($account, $measurementId);
        $this->trackingType = 'ga4';
        $this->trackingEnabled = true;
        $this->trackingInstalled = true;
        $this->trackingContainerId = '';
        $this->refreshManualSnippet();

        Notification::make()->success()->title('GA4 tracking installed')->send();
    }

    public function installGtmTracking(SiteKitTrackingInstaller $installer): void
    {
        $account = $this->currentAccount();

        if (! $account || ! Gate::allows('configureConnectors', $account)) {
            return;
        }

        $containerId = trim($this->trackingContainerId);

        if ($containerId === '') {
            Notification::make()->warning()->title('Container ID required')->send();

            return;
        }

        $installer->installGtm($account, $containerId);
        $this->trackingType = 'gtm';
        $this->trackingEnabled = true;
        $this->trackingInstalled = true;
        $this->trackingMeasurementId = '';
        $this->refreshManualSnippet();

        Notification::make()->success()->title('GTM tracking installed')->send();
    }

    public function setTrackingEnabled(bool $enabled, SiteKitManager $manager): void
    {
        $account = $this->currentAccount();

        if (! $account || ! Gate::allows('configureConnectors', $account)) {
            return;
        }

        $manager->setAccountSetting($account, 'tracking.enabled', ['value' => $enabled]);
        $this->trackingEnabled = $enabled;

        Notification::make()
            ->success()
            ->title($enabled ? 'Tracking enabled' : 'Tracking disabled')
            ->send();
    }

    public function removeTracking(SiteKitTrackingInstaller $installer): void
    {
        $account = $this->currentAccount();

        if (! $account || ! Gate::allows('configureConnectors', $account)) {
            return;
        }

        $installer->uninstall($account);
        $this->trackingInstalled = false;
        $this->trackingEnabled = false;
        $this->trackingMeasurementId = '';
        $this->trackingContainerId = '';
        $this->trackingType = 'ga4';
        $this->trackingLastDetected = false;
        $this->trackingLastSummary = 'No verification yet.';
        $this->manualSnippet = '';
        $this->manualHeadSnippet = '';
        $this->manualBodySnippet = '';

        Notification::make()->success()->title('Tracking removed')->send();
    }

    public function previewAndVerify(SiteKitTrackingVerifyService $verifyService, SiteKitManager $manager): void
    {
        $account = $this->currentAccount();

        if (! $account) {
            return;
        }

        $result = $verifyService->verify($account, $this->resolvedWebsiteUrl !== '' ? $this->resolvedWebsiteUrl : null, false);
        $this->applyVerificationResult($result, $account, $manager);
        $this->notifyVerification($result);
    }

    public function recheckTracking(SiteKitTrackingVerifyService $verifyService, SiteKitManager $manager): void
    {
        $account = $this->currentAccount();

        if (! $account) {
            return;
        }

        $result = $verifyService->verify($account, $this->resolvedWebsiteUrl !== '' ? $this->resolvedWebsiteUrl : null, true);
        $this->applyVerificationResult($result, $account, $manager);
        $this->notifyVerification($result);
    }

    public function notifySnippetCopied(): void
    {
        Notification::make()->success()->title('Copied')->send();
    }

    protected function applyVerificationResult(array $result, SiteKitAccount $account, SiteKitManager $manager): void
    {
        $this->trackingLastDetected = $this->detectFromResult($result, $this->trackingType);
        $this->trackingLastSummary = (string) ($result['message'] ?? 'Verification complete.');
        $this->trackingLastVerifyAt = (string) ($result['checked_at'] ?? now()->toIso8601String());
        $this->resolvedWebsiteUrl = (string) ($result['url'] ?? $this->resolvedWebsiteUrl);

        $manager->setAccountSetting($account, 'tracking.last_verify', ['value' => $result]);
    }

    protected function notifyVerification(array $result): void
    {
        $detected = $this->detectFromResult($result, $this->trackingType);
        $statusCode = $result['status_code'] ?? null;

        Notification::make()
            ->title($detected ? 'Tracking detected ✅' : 'Tracking not detected ⚠️')
            ->body('URL: ' . (string) ($result['url'] ?? '—') . ' | Status: ' . ($statusCode !== null ? (string) $statusCode : 'n/a'))
            ->send();
    }

    protected function detectFromResult(array $result, string $configuredType): bool
    {
        if ($configuredType === 'gtm') {
            return (bool) ($result['gtm_detected'] ?? false) || (bool) ($result['markers_detected'] ?? false);
        }

        return (bool) ($result['ga4_detected'] ?? false) || (bool) ($result['markers_detected'] ?? false);
    }

    protected function resolveWebsiteUrl(SiteKitAccount $account, SiteKitManager $manager): string
    {
        $site = $manager->getAccountSetting($account, 'site.url');
        $url = $this->settingString($site);

        if ($url === '') {
            $legacy = $manager->getAccountSetting($account, 'site_url');
            $url = $this->settingString($legacy);
        }

        if ($url === '') {
            $url = (string) config('app.url', '');
        }

        if ($url !== '' && ! preg_match('/^https?:\/\//i', $url)) {
            $url = 'https://' . $url;
        }

        return rtrim($url, '/');
    }

    protected function refreshManualSnippet(): void
    {
        if (! $this->trackingInstalled) {
            $this->manualSnippet = '';

            return;
        }

        $scripts = app(SiteKitTrackingScripts::class);

        if ($this->trackingType === 'gtm' && trim($this->trackingContainerId) !== '') {
            $this->manualHeadSnippet = $scripts->gtmHead($this->trackingContainerId);
            $this->manualBodySnippet = $scripts->gtmBody($this->trackingContainerId);
            $this->manualSnippet = $this->manualHeadSnippet . "\n\n" . $this->manualBodySnippet;

            return;
        }

        if ($this->trackingType === 'ga4' && trim($this->trackingMeasurementId) !== '') {
            $this->manualHeadSnippet = $scripts->ga4Head($this->trackingMeasurementId);
            $this->manualBodySnippet = '';
            $this->manualSnippet = $this->manualHeadSnippet;

            return;
        }

        $this->manualHeadSnippet = '';
        $this->manualBodySnippet = '';
        $this->manualSnippet = '';
    }

    protected function settingString(mixed $value): string
    {
        if (is_array($value) && array_key_exists('value', $value)) {
            $value = $value['value'];
        }

        return trim((string) ($value ?? ''));
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
