<?php

declare(strict_types=1);

namespace BoxinCode\FilamentSiteKit;

use BoxinCode\FilamentSiteKit\Contracts\Connector;
use BoxinCode\FilamentSiteKit\Contracts\TokenStore;
use BoxinCode\FilamentSiteKit\Models\SiteKitAccount;
use BoxinCode\FilamentSiteKit\Models\SiteKitSetting;
use BoxinCode\FilamentSiteKit\Models\SiteKitSnapshot;
use BoxinCode\FilamentSiteKit\OAuth\GoogleOAuthClient;
use Illuminate\Support\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class SiteKitManager
{
    public function __construct(
        protected ConnectorRegistry $connectorRegistry,
        protected TokenStore $tokenStore,
        protected SiteKitTokenService $tokenService,
        protected GoogleOAuthClient $googleOAuthClient,
        protected SiteKitLicense $license,
    ) {
    }

    /**
     * @return array<int, string>
     */
    public function allowedPeriods(): array
    {
        return $this->license->allowedPeriods();
    }

    /**
     * @return array<int, Connector>
     */
    public function connectors(): array
    {
        return $this->connectorRegistry->enabled();
    }

    /**
     * @return array<int, Connector>
     */
    public function allConnectors(): array
    {
        return $this->connectorRegistry->all();
    }

    public function connector(string $key): ?Connector
    {
        return $this->connectorRegistry->find($key);
    }

    public function cacheTtlSeconds(): int
    {
        return (int) config('filament-sitekit.cache.ttl_seconds', 3600);
    }

    /**
     * @return array<string, mixed>
     */
    public function getMetrics(SiteKitAccount $account, string $connectorKey, string $period): array
    {
        if (! in_array($period, $this->allowedPeriods(), true)) {
            $period = $this->allowedPeriods()[0] ?? '7d';
        }

        $cacheKey = sprintf('filament-sitekit:%d:%s:%s', $account->id, $connectorKey, $period);

        return Cache::remember($cacheKey, $this->cacheTtlSeconds(), function () use ($account, $connectorKey, $period): array {
            $connector = $this->connector($connectorKey);

            if (! $connector) {
                return ['error' => 'Connector not enabled'];
            }

            return $connector->fetchSnapshot($account, $period);
        });
    }

    /**
     * @return array{labels: array<int, string>, values: array<int, int|float>}
     */
    public function getTimeSeries(SiteKitAccount $account, string $period, string $metric): array
    {
        if (! in_array($period, $this->allowedPeriods(), true)) {
            $period = $this->allowedPeriods()[0] ?? '7d';
        }

        $connectorKey = in_array($metric, ['clicks', 'impressions'], true) ? 'gsc' : 'ga4';
        $cacheKey = sprintf('filament-sitekit:timeseries:%d:%s:%s', $account->id, $period, $metric);

        return Cache::remember($cacheKey, $this->cacheTtlSeconds(), function () use ($account, $period, $metric, $connectorKey): array {
            $connector = $this->connector($connectorKey);

            if (! $connector) {
                return ['labels' => [], 'values' => []];
            }

            return $connector->fetchTimeSeries($account, $period, $metric);
        });
    }

    /**
     * @return Collection<int, SiteKitAccount>
     */
    public function connectedAccounts(): Collection
    {
        return SiteKitAccount::query()->where('provider', 'google')->get();
    }

    public function saveSnapshot(SiteKitAccount $account, string $connector, string $period, array $data): SiteKitSnapshot
    {
        return SiteKitSnapshot::query()->updateOrCreate(
            [
                'account_id' => $account->id,
                'connector' => $connector,
                'period' => $period,
                'fetched_on' => now()->toDateString(),
            ],
            [
                'data' => $data,
                'fetched_at' => now(),
            ]
        );
    }

    /**
     * @return array<int, array{date: string, users: int, sessions: int, pageviews: int}>
     */
    public function buildGa4DailyRows(SiteKitAccount $account, string $period): array
    {
        $users = $this->getTimeSeries($account, $period, 'users');
        $sessions = $this->getTimeSeries($account, $period, 'sessions');
        $pageviews = $this->getTimeSeries($account, $period, 'pageviews');

        return $this->mergeDailyRows(
            (array) ($users['labels'] ?? []),
            [
                'users' => (array) ($users['values'] ?? []),
                'sessions' => (array) ($sessions['values'] ?? []),
                'pageviews' => (array) ($pageviews['values'] ?? []),
            ]
        );
    }

    /**
     * @return array<int, array{date: string, clicks: float, impressions: float}>
     */
    public function buildGscDailyRows(SiteKitAccount $account, string $period): array
    {
        $clicks = $this->getTimeSeries($account, $period, 'clicks');
        $impressions = $this->getTimeSeries($account, $period, 'impressions');

        return $this->mergeDailyRows(
            (array) ($clicks['labels'] ?? []),
            [
                'clicks' => (array) ($clicks['values'] ?? []),
                'impressions' => (array) ($impressions['values'] ?? []),
            ]
        );
    }

    /**
     * @return array{labels: array<int, string>, values: array<int, int|float>}
     */
    public function getSnapshotTimeSeries(SiteKitAccount $account, string $period, string $metric): array
    {
        $connector = in_array($metric, ['clicks', 'impressions'], true) ? 'gsc' : 'ga4';

        $snapshot = SiteKitSnapshot::query()
            ->where('account_id', $account->id)
            ->where('connector', $connector)
            ->where('period', $period)
            ->orderByDesc('fetched_on')
            ->orderByDesc('id')
            ->first();

        if (! $snapshot || ! is_array($snapshot->data)) {
            return ['labels' => [], 'values' => []];
        }

        $labels = [];
        $values = [];

        foreach ($snapshot->data as $row) {
            if (! is_array($row)) {
                continue;
            }

            $date = (string) Arr::get($row, 'date', '');

            if ($date === '') {
                continue;
            }

            $labels[] = $date;
            $values[] = (float) Arr::get($row, $metric, 0);
        }

        return [
            'labels' => $labels,
            'values' => $values,
        ];
    }

    /**
     * @return array<int, array{propertyId: string, displayName: string}>
     */
    public function listGa4Properties(SiteKitAccount $account): array
    {
        $accessToken = $this->tokenService->getValidAccessToken($account);

        if (! $accessToken) {
            return [];
        }

        $response = $this->googleOAuthClient->listGa4Properties($accessToken);

        return Arr::get($response, 'properties', []);
    }

    /**
     * @return array<int, array{siteUrl: string, permissionLevel: string}>
     */
    public function listGscSites(SiteKitAccount $account): array
    {
        $accessToken = $this->tokenService->getValidAccessToken($account);

        if (! $accessToken) {
            return [];
        }

        $response = $this->googleOAuthClient->listSearchConsoleSites($accessToken);

        return Arr::get($response, 'siteEntry', []);
    }

    public function setAccountSetting(?SiteKitAccount $account, string $key, mixed $value): SiteKitSetting
    {
        return SiteKitSetting::query()->updateOrCreate(
            [
                'account_id' => $account?->id,
                'key' => $key,
            ],
            [
                'value' => $value,
            ]
        );
    }

    public function getAccountSetting(?SiteKitAccount $account, string $key, mixed $default = null): mixed
    {
        $setting = SiteKitSetting::query()
            ->where('account_id', $account?->id)
            ->where('key', $key)
            ->first();

        return $setting?->value ?? $default;
    }

    public function clearAccountData(SiteKitAccount $account): void
    {
        $this->tokenStore->delete($account);

        SiteKitSetting::query()->where('account_id', $account->id)->delete();
        SiteKitSnapshot::query()->where('account_id', $account->id)->delete();
    }

    public function isConnected(?SiteKitAccount $account): bool
    {
        if (! $account) {
            return false;
        }

        return $this->tokenService->getValidAccessToken($account) !== null;
    }

    public function isFullyConfigured(?SiteKitAccount $account): bool
    {
        if (! $this->isConnected($account)) {
            return false;
        }

        if (! $account) {
            return false;
        }

        $ga4 = $this->connector('ga4');

        if (! $ga4) {
            return false;
        }

        if ($this->license->isFree()) {
            return $ga4->setupStatus($account) === 'ready';
        }

        $gsc = $this->connector('gsc');

        if (! $gsc) {
            return false;
        }

        return $ga4->setupStatus($account) === 'ready' && $gsc->setupStatus($account) === 'ready';
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function moduleCards(?SiteKitAccount $account): array
    {
        return collect($this->allConnectors())
            ->map(function (Connector $connector) use ($account): array {
                $moduleMeta = (array) config('filament-sitekit.connectors.modules.' . $connector->key(), []);

                $status = 'needs_setup';
                $isLocked = ! $this->license->allowsConnector($connector->key());
                $isEnabled = $this->connectorRegistry->isEnabled($connector->key(), $connector->isEnabled());

                if ($isLocked) {
                    $status = 'locked';
                } elseif (! $isEnabled) {
                    $status = 'disabled';
                } elseif (! $account || ! $this->isConnected($account)) {
                    $status = 'disconnected';
                } else {
                    $status = $connector->setupStatus($account);

                    if (! in_array($status, ['ready', 'needs_setup', 'disconnected', 'error', 'locked'], true)) {
                        $status = 'error';
                    }
                }

                return [
                    'key' => $connector->key(),
                    'label' => $moduleMeta['title'] ?? $connector->label(),
                    'description' => $moduleMeta['description'] ?? $connector->description(),
                    'icon' => $moduleMeta['icon'] ?? $connector->icon(),
                    'toggleable' => (bool) ($moduleMeta['toggleable'] ?? false),
                    'status' => $status,
                    'locked' => $isLocked,
                    'upgrade_message' => $isLocked ? 'Upgrade to Pro' : null,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param array<int, string> $labels
     * @param array<string, array<int, int|float>> $metricMap
     * @return array<int, array<string, int|float|string>>
     */
    protected function mergeDailyRows(array $labels, array $metricMap): array
    {
        if ($labels === []) {
            return [];
        }

        $rows = [];

        foreach ($labels as $index => $label) {
            $date = $this->normalizeDateLabel((string) $label);

            if ($date === '') {
                continue;
            }

            $row = ['date' => $date];

            foreach ($metricMap as $metric => $values) {
                $row[$metric] = (float) ($values[$index] ?? 0);

                if (in_array($metric, ['users', 'sessions', 'pageviews'], true)) {
                    $row[$metric] = (int) $row[$metric];
                }
            }

            $rows[] = $row;
        }

        return $rows;
    }

    protected function normalizeDateLabel(string $label): string
    {
        if ($label === '') {
            return '';
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $label) === 1) {
            return $label;
        }

        try {
            return Carbon::parse($label)->toDateString();
        } catch (\Throwable) {
            return '';
        }
    }

}
