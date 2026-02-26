<?php

declare(strict_types=1);

namespace BoxInCoded\FilamentSiteKit\Core\Support\Guards;

use BoxInCoded\FilamentSiteKit\Core\Contracts\PlanGuard;
use BoxinCode\FilamentSiteKit\SiteKitAccountManager;
use BoxinCode\FilamentSiteKit\SiteKitLicense;
use Illuminate\Contracts\Auth\Authenticatable;

class FreePlanGuard implements PlanGuard
{
    public function __construct(
        protected SiteKitLicense $license,
        protected SiteKitAccountManager $accountManager,
    ) {
    }

    public function canCreateAnotherAccount(?Authenticatable $user): bool
    {
        if (! $user) {
            return false;
        }

        if (! $this->license->isFree()) {
            return true;
        }

        return $this->accountManager->allForUser()->count() < 1;
    }
}
