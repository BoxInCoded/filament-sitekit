<?php

declare(strict_types=1);

namespace BoxinCode\FilamentSiteKit\Connectors;

use BoxinCode\FilamentSiteKit\Contracts\Connector;
use BoxinCode\FilamentSiteKit\Models\SiteKitAccount;
use BoxinCode\FilamentSiteKit\OAuth\GoogleOAuthClient;
use BoxinCode\FilamentSiteKit\SiteKitTokenService;
use Illuminate\Support\Arr;
use Throwable;

class GoogleAnalytics4Connector implements Connector
{
    public function __construct(
        protected SiteKitTokenService $tokenService,
        protected GoogleOAuthClient $oauthClient,
    ) {
    }

    public function key(): string
    {
        return 'ga4';
    }

    public function label(): string
    {
        return 'Google Analytics 4';
    }

    public function description(): string
    {
        return 'Track users, sessions and content performance from GA4.';
    }

    public function icon(): string
    {
        return 'heroicon-o-chart-bar';
    }

    public function isEnabled(): bool
    {
        return (bool) config('filament-sitekit.connectors.enabled.ga4', true);
    }

    public function setupStatus(SiteKitAccount $account): string
    {
        try {
            $token = $this->tokenService->getValidAccessToken($account);

            if (! $token) {
                return 'disconnected';
            }

            return $this->selectedPropertyId($account) ? 'ready' : 'needs_setup';
        } catch (Throwable $exception) {
            report($exception);

            return 'error';
        }
    }

    public function healthCheck(SiteKitAccount $account): array
    {
        $issues = [];

        $token = $this->tokenService->getValidAccessToken($account);

        if (! $token) {
            $issues[] = [
                'level' => 'error',
                'title' => 'Google token missing',
                'description' => 'Reconnect Google to enable GA4 metrics.',
                'action_url' => route('filament-sitekit.google.connect'),
            ];

            return $issues;
        }

        $propertyId = $this->selectedPropertyId($account);

        if (! $propertyId) {
            $issues[] = [
                'level' => 'warning',
                'title' => 'GA4 property not selected',
                'description' => 'Choose a GA4 property in Site Kit settings.',
                'action_url' => \BoxinCode\FilamentSiteKit\Filament\Pages\SiteKitSettings::getUrl(),
            ];
        }

        return $issues;
    }

    public function fetchSnapshot(SiteKitAccount $account, string $period): array
    {
        $accessToken = $this->tokenService->getValidAccessToken($account);

        if (! $accessToken) {
            return ['error' => 'No valid Google token found'];
        }

        $propertyId = $this->selectedPropertyId($account);

        if (! $propertyId) {
            return ['error' => 'No GA4 property selected'];
        }

        try {
            $metricsReport = $this->oauthClient->runGa4Report(
                $accessToken,
                $propertyId,
                [
                    'dateRanges' => [[
                        'startDate' => $this->startDateFromPeriod($period),
                        'endDate' => 'today',
                    ]],
                    'metrics' => [
                        ['name' => 'activeUsers'],
                        ['name' => 'sessions'],
                        ['name' => 'screenPageViews'],
                        ['name' => 'totalUsers'],
                        ['name' => 'conversions'],
                    ],
                ]
            );

            $topPagesReport = $this->oauthClient->runGa4Report(
                $accessToken,
                $propertyId,
                [
                    'dateRanges' => [[
                        'startDate' => $this->startDateFromPeriod($period),
                        'endDate' => 'today',
                    ]],
                    'dimensions' => [
                        ['name' => 'pagePath'],
                    ],
                    'metrics' => [
                        ['name' => 'screenPageViews'],
                    ],
                    'limit' => 10,
                    'orderBys' => [[
                        'metric' => ['metricName' => 'screenPageViews'],
                        'desc' => true,
                    ]],
                ]
            );

            $trafficAcquisitionReport = $this->oauthClient->runGa4Report(
                $accessToken,
                $propertyId,
                [
                    'dateRanges' => [[
                        'startDate' => $this->startDateFromPeriod($period),
                        'endDate' => 'today',
                    ]],
                    'dimensions' => [
                        ['name' => 'sessionSourceMedium'],
                    ],
                    'metrics' => [
                        ['name' => 'sessions'],
                    ],
                    'limit' => 8,
                    'orderBys' => [[
                        'metric' => ['metricName' => 'sessions'],
                        'desc' => true,
                    ]],
                ]
            );

            return [
                'metrics' => $this->normalizeMetricRows($metricsReport),
                'top_pages' => $this->normalizeTopPages($topPagesReport),
                'traffic_acquisition' => $this->normalizeTrafficAcquisition($trafficAcquisitionReport),
                'period' => $period,
            ];
        } catch (Throwable $exception) {
            report($exception);

            return [
                'error' => 'Unable to fetch GA4 metrics. Please verify your property access.',
                'meta' => [
                    'exception' => class_basename($exception),
                ],
            ];
        }
    }

    public function fetchTimeSeries(SiteKitAccount $account, string $period, string $metric): array
    {
        $metricName = match ($metric) {
            'users' => 'totalUsers',
            'sessions' => 'sessions',
            'pageviews' => 'screenPageViews',
            default => null,
        };

        if (! $metricName) {
            return ['labels' => [], 'values' => []];
        }

        $accessToken = $this->tokenService->getValidAccessToken($account);

        if (! $accessToken) {
            return ['labels' => [], 'values' => []];
        }

        $propertyId = $this->selectedPropertyId($account);

        if (! $propertyId) {
            return ['labels' => [], 'values' => []];
        }

        try {
            $report = $this->oauthClient->runGa4Report(
                $accessToken,
                $propertyId,
                [
                    'dateRanges' => [[
                        'startDate' => $this->startDateFromPeriod($period),
                        'endDate' => 'today',
                    ]],
                    'dimensions' => [
                        ['name' => 'date'],
                    ],
                    'metrics' => [
                        ['name' => $metricName],
                    ],
                    'orderBys' => [[
                        'dimension' => ['dimensionName' => 'date'],
                    ]],
                ]
            );

            $labels = [];
            $values = [];

            foreach ((array) Arr::get($report, 'rows', []) as $row) {
                $dateRaw = (string) Arr::get($row, 'dimensionValues.0.value', '');
                $labels[] = $this->formatGaDate($dateRaw);
                $values[] = (int) Arr::get($row, 'metricValues.0.value', 0);
            }

            return [
                'labels' => $labels,
                'values' => $values,
            ];
        } catch (Throwable $exception) {
            report($exception);

            return ['labels' => [], 'values' => []];
        }
    }

    protected function selectedPropertyId(SiteKitAccount $account): ?string
    {
        $setting = $account->settings()->where('key', 'ga4_property_id')->first();

        return Arr::get($setting?->value, 'value');
    }

    protected function startDateFromPeriod(string $period): string
    {
        if (preg_match('/^(\d{1,3})d$/', $period, $matches) === 1) {
            return (int) $matches[1] . 'daysAgo';
        }

        return match ($period) {
            '90d' => '90daysAgo',
            '28d' => '28daysAgo',
            default => '7daysAgo',
        };
    }

    protected function formatGaDate(string $dateRaw): string
    {
        if (strlen($dateRaw) !== 8) {
            return $dateRaw;
        }

        return sprintf('%s-%s-%s', substr($dateRaw, 0, 4), substr($dateRaw, 4, 2), substr($dateRaw, 6, 2));
    }

    /**
     * @param array<string, mixed> $report
     * @return array<string, int|float>
     */
    protected function normalizeMetricRows(array $report): array
    {
        $values = Arr::get($report, 'rows.0.metricValues', []);

        return [
            'activeUsers' => (int) Arr::get($values, '0.value', 0),
            'sessions' => (int) Arr::get($values, '1.value', 0),
            'screenPageViews' => (int) Arr::get($values, '2.value', 0),
            'totalUsers' => (int) Arr::get($values, '3.value', 0),
            'conversions' => (float) Arr::get($values, '4.value', 0),
        ];
    }

    /**
     * @param array<string, mixed> $report
     * @return array<int, array{page: string, views: int}>
     */
    protected function normalizeTopPages(array $report): array
    {
        return collect(Arr::get($report, 'rows', []))
            ->map(fn (array $row): array => [
                'page' => (string) Arr::get($row, 'dimensionValues.0.value', '/'),
                'views' => (int) Arr::get($row, 'metricValues.0.value', 0),
            ])
            ->values()
            ->all();
    }

    /**
     * @param array<string, mixed> $report
     * @return array<int, array{source: string, sessions: int}>
     */
    protected function normalizeTrafficAcquisition(array $report): array
    {
        return collect(Arr::get($report, 'rows', []))
            ->map(fn (array $row): array => [
                'source' => (string) Arr::get($row, 'dimensionValues.0.value', '(direct)'),
                'sessions' => (int) Arr::get($row, 'metricValues.0.value', 0),
            ])
            ->values()
            ->all();
    }
}
