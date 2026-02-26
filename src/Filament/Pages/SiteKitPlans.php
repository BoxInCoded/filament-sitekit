<?php

declare(strict_types=1);

namespace BoxinCode\FilamentSiteKit\Filament\Pages;

use BoxinCode\FilamentSiteKit\SiteKitLicense;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Gate;

class SiteKitPlans extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-credit-card';

    protected static ?string $navigationGroup = 'Site Kit';

    protected static ?int $navigationSort = 6;

    protected static ?string $navigationLabel = 'Plans';

    protected static ?string $title = 'Plans';

    protected string $view = 'filament-sitekit::pages.plans';

    /**
     * @var array<int, array{feature: string, free: bool, pro: bool, agency: bool, enterprise: bool}>
     */
    public array $features = [
        ['feature' => 'Google Analytics 4', 'free' => true, 'pro' => true, 'agency' => true, 'enterprise' => true],
        ['feature' => 'Search Console', 'free' => false, 'pro' => true, 'agency' => true, 'enterprise' => true],
        ['feature' => 'Alerts & insights', 'free' => false, 'pro' => true, 'agency' => true, 'enterprise' => true],
        ['feature' => 'Advanced diagnostics', 'free' => false, 'pro' => true, 'agency' => true, 'enterprise' => true],
        ['feature' => 'Multi-accounts', 'free' => false, 'pro' => true, 'agency' => true, 'enterprise' => true],
        ['feature' => 'Account sharing', 'free' => false, 'pro' => false, 'agency' => true, 'enterprise' => true],
        ['feature' => 'Queue sync', 'free' => false, 'pro' => false, 'agency' => true, 'enterprise' => true],
        ['feature' => 'Client share links', 'free' => false, 'pro' => true, 'agency' => true, 'enterprise' => true],
        ['feature' => 'API access', 'free' => false, 'pro' => false, 'agency' => false, 'enterprise' => true],
        ['feature' => 'White label', 'free' => false, 'pro' => false, 'agency' => false, 'enterprise' => true],
    ];

    public static function shouldRegisterNavigation(): bool
    {
        $license = app(SiteKitLicense::class);

        return $license->isFree() || $license->isPro();
    }

    public static function getNavigationBadge(): ?string
    {
        return 'Pro';
    }

    public function currentPlanLabel(): string
    {
        return ucfirst(app(SiteKitLicense::class)->plan());
    }

    protected function authorizeAccess(): void
    {
        abort_unless(Filament::auth()->check(), 403);
        abort_unless(Gate::allows((string) config('filament-sitekit.authorization.gate', 'manageSiteKit')), 403);
    }

    public function mount(): void
    {
        $this->authorizeAccess();
    }
}
