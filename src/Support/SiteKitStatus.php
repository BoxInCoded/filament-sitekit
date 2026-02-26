<?php

declare(strict_types=1);

namespace BoxinCode\FilamentSiteKit\Support;

use BoxinCode\FilamentSiteKit\SiteKitAccountManager;
use BoxinCode\FilamentSiteKit\SiteKitManager;
use BoxinCode\FilamentSiteKit\Support\SiteKitSetupStatus;

class SiteKitStatus
{
    public function __construct(
        protected SiteKitAccountManager $accountManager,
        protected SiteKitManager $manager,
        protected SiteKitSetupStatus $setupStatus,
    ) {
    }

    public function isConfigured(): bool
    {
        return $this->setupStatus->setupComplete();
    }
}
