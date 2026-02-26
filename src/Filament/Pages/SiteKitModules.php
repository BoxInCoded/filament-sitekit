<?php

declare(strict_types=1);

namespace BoxinCode\FilamentSiteKit\Filament\Pages;

use BoxinCode\FilamentSiteKit\Models\SiteKitAccount;
use BoxinCode\FilamentSiteKit\SiteKitAccountManager;
use BoxinCode\FilamentSiteKit\SiteKitLicense;
use BoxinCode\FilamentSiteKit\SiteKitManager;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Gate;

class SiteKitModules extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-squares-2x2';

    protected static ?string $navigationGroup = 'Site Kit';

    protected static ?int $navigationSort = 3;

    protected static ?string $title = 'Site Kit Modules';

    protected string $view = 'filament-sitekit::pages.modules';

    /**
     * @var array<int, array<string, mixed>>
     */
    public array $modules = [];

    /**
     * @var array<string, string>
     */
    public array $ga4Options = [];

    /**
     * @var array<string, string>
     */
    public array $gscOptions = [];

    public ?string $ga4PropertyId = null;

    public ?string $gscSiteUrl = null;

    public static function getNavigationBadge(): ?string
    {
        return app(SiteKitLicense::class)->isFree() ? 'Pro' : null;
    }

    public function mount(SiteKitManager $manager): void
    {
        $this->authorizeAccess();

        $account = $this->currentAccount();

        $this->modules = $manager->moduleCards($account);

        if ($account) {
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

            $this->ga4PropertyId = $manager->getAccountSetting($account, 'ga4_property_id')['value'] ?? null;
            $this->gscSiteUrl = $manager->getAccountSetting($account, 'gsc_site_url')['value'] ?? null;
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('plans')
                ->label('View plans')
                ->icon('heroicon-o-credit-card')
                ->color('gray')
                ->url(SiteKitPlans::getUrl()),
            Action::make('setup')
                ->label('Setup Wizard')
                ->icon('heroicon-o-sparkles')
                ->url(SiteKitSetupWizard::getUrl()),
            Action::make('settings')
                ->label('Settings')
                ->icon('heroicon-o-cog-6-tooth')
                ->url(SiteKitSettings::getUrl()),
        ];
    }

    public function enableModule(string $key, SiteKitManager $manager): void
    {
        $account = $this->currentAccount();

        if ($account && ! Gate::allows('configureConnectors', $account)) {
            return;
        }

        $manager->setAccountSetting(null, 'module_enabled_' . $key, ['enabled' => true]);
        $this->modules = $manager->moduleCards($this->currentAccount());

        Notification::make()
            ->success()
            ->title('Module enabled')
            ->body(ucfirst($key) . ' module has been enabled.')
            ->send();
    }

    public function disableModule(string $key, SiteKitManager $manager): void
    {
        $account = $this->currentAccount();

        if ($account && ! Gate::allows('configureConnectors', $account)) {
            return;
        }

        $manager->setAccountSetting(null, 'module_enabled_' . $key, ['enabled' => false]);
        $this->modules = $manager->moduleCards($this->currentAccount());

        Notification::make()
            ->success()
            ->title('Module disabled')
            ->body(ucfirst($key) . ' module has been disabled.')
            ->send();
    }

    public function saveGa4Config(SiteKitManager $manager): void
    {
        $account = $this->currentAccount();

        if (! $account || ! Gate::allows('configureConnectors', $account)) {
            return;
        }

        $manager->setAccountSetting($account, 'ga4_property_id', ['value' => $this->ga4PropertyId]);
        $this->modules = $manager->moduleCards($account);

        Notification::make()
            ->success()
            ->title('Analytics configuration saved')
            ->send();
    }

    public function saveGscConfig(SiteKitManager $manager): void
    {
        $account = $this->currentAccount();

        if (! $account || ! Gate::allows('configureConnectors', $account)) {
            return;
        }

        $manager->setAccountSetting($account, 'gsc_site_url', ['value' => $this->gscSiteUrl]);
        $this->modules = $manager->moduleCards($account);

        Notification::make()
            ->success()
            ->title('Search Console configuration saved')
            ->send();
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
