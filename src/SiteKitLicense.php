<?php

declare(strict_types=1);

namespace BoxinCode\FilamentSiteKit;

class SiteKitLicense
{
    public function plan(): string
    {
        return 'free';
    }

    public function isFree(): bool
    {
        return $this->plan() === 'free';
    }

    public function isPro(): bool
    {
        return false;
    }

    public function isAgency(): bool
    {
        return false;
    }

    public function isEnterprise(): bool
    {
        return false;
    }

    public function allowsMultipleAccounts(): bool
    {
        return false;
    }

    public function allowsConnector(string $key): bool
    {
        return $key === 'ga4';
    }

    public function maxHistoryDays(): int
    {
        return 7;
    }

    /**
     * @return array<int, string>
     */
    public function allowedPeriods(): array
    {
        return ['7d'];
    }

    public function allowsDiagnosticsPro(): bool
    {
        return false;
    }

    public function allowsAccountSharing(): bool
    {
        return false;
    }

    public function allowsQueueSync(): bool
    {
        return false;
    }

    public function allowsWhiteLabel(): bool
    {
        return false;
    }

    public function allowsApiAccess(): bool
    {
        return false;
    }

    public function allowsWeeklyReports(): bool
    {
        return false;
    }

    public function weeklyReportRecipientsLimit(): int
    {
        return 0;
    }

    public function allowsWeeklyReportPdf(): bool
    {
        return false;
    }

    public function allowsShareLinks(): bool
    {
        return false;
    }

    public function shareLinksLimit(): int
    {
        return 0;
    }

    public function allowsShareLinkBranding(): bool
    {
        return false;
    }

    public function requiresProMessage(string $featureKey): string
    {
        return match ($featureKey) {
            'gsc', 'search_console' => 'Search Console is not available in this public package.',
            'alerts' => 'Alerts are not available in this public package.',
            'insights' => 'Insights are not available in this public package.',
            'health_advanced', 'diagnostics' => 'Advanced diagnostics are not available in this public package.',
            'multi_accounts' => 'Multiple accounts are not available in this public package.',
            'weekly_reports' => 'Weekly reports are not available in this public package.',
            'share_links' => 'Share links are not available in this public package.',
            default => 'This feature is not available in this public package.',
        };
    }
}
