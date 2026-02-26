<?php

declare(strict_types=1);

namespace BoxinCode\FilamentSiteKit\Filament;

use BoxInCoded\FilamentSiteKit\Core\Support\ExtensionsRegistry;
use BoxinCode\FilamentSiteKit\Filament\Pages\SiteKitDashboard;
use BoxinCode\FilamentSiteKit\Filament\Pages\SiteKitDiagnostics;
use BoxinCode\FilamentSiteKit\Filament\Pages\SiteKitAccounts;
use BoxinCode\FilamentSiteKit\Filament\Pages\SiteKitModules;
use BoxinCode\FilamentSiteKit\Filament\Pages\SiteKitPlans;
use BoxinCode\FilamentSiteKit\Filament\Pages\SiteKitSetupWizard;
use BoxinCode\FilamentSiteKit\Filament\Pages\SiteKitSettings;
use BoxinCode\FilamentSiteKit\Filament\Widgets\TrafficChartWidget;
use Filament\Contracts\Plugin as FilamentPlugin;
use Filament\Panel;

class Plugin implements FilamentPlugin
{
    public function getId(): string
    {
        return 'filament-sitekit';
    }

    public function register(Panel $panel): void
    {
        $corePages = [
            SiteKitDashboard::class,
            SiteKitSetupWizard::class,
            SiteKitAccounts::class,
            SiteKitModules::class,
            SiteKitSettings::class,
            SiteKitDiagnostics::class,
            SiteKitPlans::class,
        ];

        $extensions = app(ExtensionsRegistry::class);

        $panel->pages(array_values(array_unique(array_merge($corePages, $extensions->pages()))));

        if (method_exists($panel, 'widgets')) {
            $coreWidgets = [
                TrafficChartWidget::class,
            ];

            $panel->widgets(array_values(array_unique(array_merge($coreWidgets, $extensions->widgets()))));
        }
    }

    public function boot(Panel $panel): void
    {
        // Intentionally minimal for cross-version compatibility.
    }
}
