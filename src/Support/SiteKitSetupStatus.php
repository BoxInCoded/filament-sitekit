<?php

declare(strict_types=1);

namespace BoxinCode\FilamentSiteKit\Support;

use BoxinCode\FilamentSiteKit\Models\SiteKitAccount;
use BoxinCode\FilamentSiteKit\SiteKitAccountManager;
use BoxinCode\FilamentSiteKit\SiteKitLicense;
use BoxinCode\FilamentSiteKit\SiteKitManager;

class SiteKitSetupStatus
{
    public function __construct(
        protected SiteKitAccountManager $accountManager,
        protected SiteKitManager $manager,
        protected SiteKitTrackingDetector $trackingDetector,
        protected SiteKitLicense $license,
    ) {
    }

    public function isConnected(): bool
    {
        return $this->manager->isConnected($this->account());
    }

    public function analyticsConfigured(): bool
    {
        $account = $this->account();

        if (! $account) {
            return false;
        }

        $gaProperty = $this->settingString($account, 'ga4_property_id');

        return $gaProperty !== '';
    }

    public function searchConsoleConfigured(): bool
    {
        if ($this->license->isFree()) {
            return true;
        }

        $account = $this->account();

        if (! $account) {
            return false;
        }

        $gscSite = $this->settingString($account, 'gsc_site_url');

        return $gscSite !== '';
    }

    public function trackingDetected(): bool
    {
        $account = $this->account();

        if (! $account) {
            return false;
        }

        $result = $this->trackingDetector->detectTracking($account);

        return (bool) ($result['ga'] ?? false) || (bool) ($result['gtm'] ?? false);
    }

    public function setupComplete(): bool
    {
        return $this->isConnected()
            && $this->analyticsConfigured()
            && $this->searchConsoleConfigured();
    }

    protected function account(): ?SiteKitAccount
    {
        return $this->accountManager->current();
    }

    protected function settingString(SiteKitAccount $account, string $key): string
    {
        $value = $this->manager->getAccountSetting($account, $key);

        if (is_array($value)) {
            $value = $value['value'] ?? '';
        }

        return trim((string) $value);
    }
}
