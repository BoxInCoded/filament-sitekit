<?php

declare(strict_types=1);

namespace BoxinCode\FilamentSiteKit\Support;

use BoxinCode\FilamentSiteKit\Models\SiteKitAccount;
use BoxinCode\FilamentSiteKit\Models\SiteKitSnapshot;
use BoxinCode\FilamentSiteKit\SiteKitManager;
use BoxinCode\FilamentSiteKit\SiteKitTokenService;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class SiteKitHealthService
{
    public function __construct(
        protected SiteKitManager $manager,
        protected SiteKitTokenService $tokenService,
    ) {
    }

    /**
     * @return array{
     *   analytics: array{status: string, checks: array<int, array{level: string, title: string, description: string}>},
     *   search_console: array{status: string, checks: array<int, array{level: string, title: string, description: string}>},
     *   technical: array{status: string, checks: array<int, array{level: string, title: string, description: string}>}
     * }
     */
    public function healthStatus(SiteKitAccount $account): array
    {
        $analyticsChecks = $this->analyticsChecks($account);
        $searchChecks = $this->searchConsoleChecks($account);
        $technicalChecks = $this->technicalChecks($account);

        return [
            'analytics' => [
                'status' => $this->groupStatus($analyticsChecks),
                'checks' => $analyticsChecks,
            ],
            'search_console' => [
                'status' => $this->groupStatus($searchChecks),
                'checks' => $searchChecks,
            ],
            'technical' => [
                'status' => $this->groupStatus($technicalChecks),
                'checks' => $technicalChecks,
            ],
        ];
    }

    /**
     * @return array<int, array{level: string, title: string, description: string}>
     */
    protected function analyticsChecks(SiteKitAccount $account): array
    {
        $checks = [];

        $connected = $this->manager->isConnected($account);
        $checks[] = [
            'level' => $connected ? 'ok' : 'error',
            'title' => 'GA4 Connected',
            'description' => $connected ? 'Analytics account connected' : 'Google account is not connected',
        ];

        $tokenValid = $this->tokenService->getValidAccessToken($account) !== null;
        $checks[] = [
            'level' => $tokenValid ? 'ok' : 'error',
            'title' => 'Token valid',
            'description' => $tokenValid ? 'Access token is valid' : 'Access token is missing or expired',
        ];

        $propertySelected = (string) (($this->manager->getAccountSetting($account, 'ga4_property_id')['value'] ?? '') ?: '');
        $checks[] = [
            'level' => $propertySelected !== '' ? 'ok' : 'warning',
            'title' => 'GA4 property selected',
            'description' => $propertySelected !== '' ? 'Analytics property is selected' : 'Select a GA4 property',
        ];

        $hasRecentData = $this->hasRecentAnalyticsData($account, 24);
        $checks[] = [
            'level' => $hasRecentData ? 'ok' : 'warning',
            'title' => $hasRecentData ? 'Recent data detected' : 'No data detected',
            'description' => $hasRecentData
                ? 'Analytics data exists in last 24 hours'
                : 'No analytics data last 24 hours',
        ];

        return $checks;
    }

    /**
     * @return array<int, array{level: string, title: string, description: string}>
     */
    protected function searchConsoleChecks(SiteKitAccount $account): array
    {
        $checks = [];

        $connected = $this->manager->isConnected($account);
        $checks[] = [
            'level' => $connected ? 'ok' : 'error',
            'title' => 'Search Console connected',
            'description' => $connected ? 'Google connection is active' : 'Google connection is missing',
        ];

        $siteSelected = (string) (($this->manager->getAccountSetting($account, 'gsc_site_url')['value'] ?? '') ?: '');
        $checks[] = [
            'level' => $siteSelected !== '' ? 'ok' : 'warning',
            'title' => 'Search Console site selected',
            'description' => $siteSelected !== '' ? 'Search Console site is selected' : 'Select a Search Console site',
        ];

        return $checks;
    }

    /**
     * @return array<int, array{level: string, title: string, description: string}>
     */
    protected function technicalChecks(SiteKitAccount $account): array
    {
        $url = $this->siteUrl($account);

        if ($url === null) {
            return [
                [
                    'level' => 'warning',
                    'title' => 'Site URL missing',
                    'description' => 'Set a site URL to run technical checks',
                ],
            ];
        }

        $sitemap = $this->checkUrl($account, '/sitemap.xml');
        $robots = $this->checkUrl($account, '/robots.txt');

        return [
            [
                'level' => $sitemap ? 'ok' : 'warning',
                'title' => 'Sitemap check',
                'description' => $sitemap ? 'sitemap.xml reachable' : 'sitemap.xml not reachable',
            ],
            [
                'level' => $robots ? 'ok' : 'warning',
                'title' => 'Robots check',
                'description' => $robots ? 'robots.txt reachable' : 'robots.txt not reachable',
            ],
        ];
    }

    protected function groupStatus(array $checks): string
    {
        $levels = collect($checks)->pluck('level')->all();

        if (in_array('error', $levels, true)) {
            return 'error';
        }

        if (in_array('warning', $levels, true)) {
            return 'warning';
        }

        return 'ok';
    }

    protected function hasRecentAnalyticsData(SiteKitAccount $account, int $hours): bool
    {
        $snapshot = SiteKitSnapshot::query()
            ->where('account_id', $account->id)
            ->where('connector', 'ga4')
            ->orderByDesc('fetched_on')
            ->orderByDesc('id')
            ->first();

        if (! $snapshot || ! is_array($snapshot->data)) {
            return false;
        }

        $threshold = now()->subHours($hours);

        foreach ($snapshot->data as $row) {
            if (! is_array($row)) {
                continue;
            }

            $date = (string) Arr::get($row, 'date', '');

            if ($date === '') {
                continue;
            }

            try {
                $rowDate = Carbon::parse($date);
            } catch (\Throwable) {
                continue;
            }

            if ($rowDate->lt($threshold)) {
                continue;
            }

            $users = (float) Arr::get($row, 'users', 0);
            $sessions = (float) Arr::get($row, 'sessions', 0);
            $pageviews = (float) Arr::get($row, 'pageviews', 0);

            if ($users > 0 || $sessions > 0 || $pageviews > 0) {
                return true;
            }
        }

        return false;
    }

    protected function checkUrl(SiteKitAccount $account, string $path): bool
    {
        $cacheKey = sprintf('filament-sitekit:technical:%d:%s', $account->id, ltrim($path, '/'));

        return Cache::remember($cacheKey, now()->addMinutes(30), function () use ($account, $path): bool {
            $url = $this->siteUrl($account);

            if ($url === null) {
                return false;
            }

            try {
                $response = Http::timeout(8)->get($url . $path);

                return $response->successful();
            } catch (\Throwable) {
                return false;
            }
        });
    }

    protected function siteUrl(SiteKitAccount $account): ?string
    {
        $setting = $this->manager->getAccountSetting($account, 'site_url');
        $configured = is_array($setting) ? (string) ($setting['value'] ?? '') : '';
        $url = $configured !== '' ? $configured : (string) config('app.url');

        return $url !== '' ? rtrim($url, '/') : null;
    }
}
