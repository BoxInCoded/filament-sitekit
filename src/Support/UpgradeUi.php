<?php

declare(strict_types=1);

namespace BoxinCode\FilamentSiteKit\Support;

use BoxinCode\FilamentSiteKit\SiteKitLicense;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Carbon;

class UpgradeUi
{
    protected const DISMISS_KEY = 'filament-sitekit.upgrade_banner_dismissed_until';

    public function __construct(
        protected SiteKitLicense $license,
        protected Session $session,
    ) {
    }

    public function shouldShowUpgradeBanner(): bool
    {
        if (! ($this->license->isFree() || $this->license->isPro())) {
            return false;
        }

        if ($this->isBannerDismissed()) {
            return false;
        }

        return $this->hasLockedPremiumFeatures();
    }

    public function hasLockedPremiumFeatures(): bool
    {
        return ! $this->license->allowsConnector('gsc')
            || ! $this->license->allowsMultipleAccounts()
            || ! $this->license->allowsDiagnosticsPro()
            || ! $this->license->allowsAccountSharing()
            || ! $this->license->allowsQueueSync();
    }

    public function dismissBannerForDays(int $days = 7): void
    {
        $this->session->put(self::DISMISS_KEY, now()->addDays(max(1, $days))->toIso8601String());
    }

    public function isBannerDismissed(): bool
    {
        $until = (string) $this->session->get(self::DISMISS_KEY, '');

        if ($until === '') {
            return false;
        }

        try {
            return Carbon::parse($until)->isFuture();
        } catch (\Throwable) {
            return false;
        }
    }
}
