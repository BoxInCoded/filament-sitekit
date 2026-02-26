<?php

declare(strict_types=1);

namespace BoxinCode\FilamentSiteKit\Filament\Widgets;

class OverviewStatsWidget extends BaseWidget
{
    protected static string $view = 'filament-sitekit::widgets.overview-stats-widget';

    public ?string $period = '7d';

    /**
     * @return array<int, array{label: string, value: string, description?: string}>
     */
    public function stats(): array
    {
        $account = $this->currentAccount();

        if (! $account) {
            return [
                [
                    'label' => 'Google',
                    'value' => 'Not connected',
                    'description' => 'Connect your account in settings',
                ],
            ];
        }

        $period = $this->period ?? '7d';
        $manager = $this->manager();

        $ga4 = $manager->getMetrics($account, 'ga4', $period);
        $gsc = $manager->getMetrics($account, 'gsc', $period);

        return [
            ['label' => 'GA4 Users', 'value' => (string) data_get($ga4, 'metrics.totalUsers', 0)],
            ['label' => 'GA4 Sessions', 'value' => (string) data_get($ga4, 'metrics.sessions', 0)],
            ['label' => 'GA4 Pageviews', 'value' => (string) data_get($ga4, 'metrics.screenPageViews', 0)],
            ['label' => 'GSC Clicks', 'value' => (string) data_get($gsc, 'metrics.clicks', 0)],
            ['label' => 'GSC Impressions', 'value' => (string) data_get($gsc, 'metrics.impressions', 0)],
        ];
    }
}
