<?php

declare(strict_types=1);

namespace BoxinCode\FilamentSiteKit\Filament\Pages;

use BoxinCode\FilamentSiteKit\Models\SiteKitAccount;
use BoxinCode\FilamentSiteKit\SiteKitAccountManager;
use BoxinCode\FilamentSiteKit\SiteKitLicense;
use BoxinCode\FilamentSiteKit\SiteKitManager;
use BoxinCode\FilamentSiteKit\Support\SiteKitHealthService;
use BoxinCode\FilamentSiteKit\Support\SiteKitTrackingVerifyService;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Gate;

class SiteKitDiagnostics extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-wrench-screwdriver';

    protected static ?string $navigationGroup = 'Site Kit';

    protected static ?int $navigationSort = 5;

    protected static ?string $title = 'Site Kit Diagnostics';

    protected string $view = 'filament-sitekit::pages.diagnostics';

    /**
     * @var array<string, array<int, array{level: string, title: string, description: string, action_url?: string}>>
     */
    public array $groupedIssues = [];

    /**
     * @var array<string, string>
     */
    public array $groupStatuses = [];

    /**
     * @var array<int, string>
     */
    public array $proTeaserItems = [
        'Tracking detection',
        'Data drop alerts',
        'SEO checks',
    ];

    /**
     * @var array<string, mixed>
     */
    public array $trackingVerification = [];

    public ?string $trackingVerificationCheckedAt = null;

    public static function getNavigationBadge(): ?string
    {
        return app(SiteKitLicense::class)->isFree() ? 'Pro' : null;
    }

    public function mount(SiteKitHealthService $healthService, SiteKitManager $manager): void
    {
        $this->authorizeAccess();

        $license = app(SiteKitLicense::class);

        if ($license->isFree()) {
            $account = $this->currentAccount();

            $ga4Property = (string) (($manager->getAccountSetting($account, 'ga4_property_id')['value'] ?? '') ?: '');

            $this->groupedIssues['Basic checks'] = [
                [
                    'level' => $manager->isConnected($account) ? 'ok' : 'warning',
                    'title' => 'Google connected',
                    'description' => $manager->isConnected($account)
                        ? 'Google account connection is active.'
                        : 'Connect your Google account to continue.',
                    'action_url' => SiteKitSettings::getUrl(),
                ],
                [
                    'level' => $ga4Property !== '' ? 'ok' : 'warning',
                    'title' => 'GA4 property selected',
                    'description' => $ga4Property !== ''
                        ? 'GA4 property is selected and ready.'
                        : 'Select a GA4 property in settings.',
                    'action_url' => SiteKitSettings::getUrl(),
                ],
            ];

            $this->groupStatuses['Basic checks'] = collect($this->groupedIssues['Basic checks'])
                ->contains(fn (array $check): bool => $check['level'] === 'warning')
                ? 'warning'
                : 'ok';

            $this->loadTrackingVerification($manager, $account);

            return;
        }

        $account = $this->currentAccount();

        if (! $account) {
            $this->groupedIssues['Connection'][] = [
                'level' => 'warning',
                'title' => 'No Google account connected',
                'description' => 'Connect your Google account in Site Kit settings.',
                'action_url' => SiteKitSettings::getUrl(),
            ];

            return;
        }

        $health = $healthService->healthStatus($account);

        foreach ($health as $group => $payload) {
            $label = (string) str($group)->replace('_', ' ')->title();
            $this->groupStatuses[$label] = (string) ($payload['status'] ?? 'warning');
            $this->groupedIssues[$label] = (array) ($payload['checks'] ?? []);
        }

        $this->loadTrackingVerification($manager, $account);
    }

    public function verifyTrackingNow(SiteKitTrackingVerifyService $verifyService, SiteKitManager $manager): void
    {
        $account = $this->currentAccount();

        if (! $account) {
            Notification::make()->warning()->title('No active account')->send();

            return;
        }

        $result = $verifyService->verify($account, null, true);

        $this->trackingVerification = $result;
        $this->trackingVerificationCheckedAt = (string) ($result['checked_at'] ?? now()->toIso8601String());

        $manager->setAccountSetting($account, 'tracking.last_verify', ['value' => $result]);

        Notification::make()
            ->title($this->trackingDetected() ? 'Tracking detected ✅' : 'Tracking not detected ⚠️')
            ->body((string) ($result['message'] ?? 'Verification complete.'))
            ->send();
    }

    public function severityBadgeClass(string $level): string
    {
        return match ($level) {
            'error' => 'bg-danger-500/10 text-danger-700 dark:text-danger-300',
            'warning' => 'bg-warning-500/10 text-warning-700 dark:text-warning-300',
            default => 'bg-primary-500/10 text-primary-700 dark:text-primary-300',
        };
    }

    public function trackingDetected(): bool
    {
        return (bool) ($this->trackingVerification['ga4_detected'] ?? false)
            || (bool) ($this->trackingVerification['gtm_detected'] ?? false)
            || (bool) ($this->trackingVerification['markers_detected'] ?? false);
    }

    protected function loadTrackingVerification(SiteKitManager $manager, ?SiteKitAccount $account): void
    {
        if (! $account) {
            $this->trackingVerification = [];
            $this->trackingVerificationCheckedAt = null;

            return;
        }

        $result = $manager->getAccountSetting($account, 'tracking.last_verify');
        $result = is_array($result) ? ($result['value'] ?? $result) : $result;

        $this->trackingVerification = is_array($result) ? $result : [];
        $checkedAt = (string) ($this->trackingVerification['checked_at'] ?? '');
        $this->trackingVerificationCheckedAt = $checkedAt !== '' ? $checkedAt : null;
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
