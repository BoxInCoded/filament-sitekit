<?php

declare(strict_types=1);

namespace BoxinCode\FilamentSiteKit\Filament\Widgets;

use BoxinCode\FilamentSiteKit\Models\SiteKitAccount;
use BoxinCode\FilamentSiteKit\SiteKitAccountManager;
use BoxinCode\FilamentSiteKit\SiteKitManager;
use Filament\Widgets\Widget;

abstract class BaseWidget extends Widget
{
    protected function currentAccount(): ?SiteKitAccount
    {
        return app(SiteKitAccountManager::class)->current();
    }

    protected function manager(): SiteKitManager
    {
        return app(SiteKitManager::class);
    }
}
