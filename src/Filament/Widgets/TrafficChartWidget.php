<?php

declare(strict_types=1);

namespace BoxinCode\FilamentSiteKit\Filament\Widgets;

use BoxinCode\FilamentSiteKit\Models\SiteKitAccount;

class TrafficChartWidget extends BaseChartWidget
{
    protected static ?string $heading = 'Traffic Overview';

    public ?string $period = '28d';

    public ?int $accountId = null;

    public ?string $filter = 'users';

    protected int|string|array $columnSpan = 'full';

    protected function getData(): array
    {
        if (! in_array((string) $this->filter, ['users', 'sessions', 'pageviews', 'clicks', 'impressions'], true)) {
            $this->filter = 'users';
        }

        $account = $this->resolveAccount();

        if (! $account) {
            return [
                'datasets' => [
                    [
                        'label' => 'No data',
                        'data' => [],
                        'borderColor' => '#64748b',
                    ],
                ],
                'labels' => [],
            ];
        }

        $metric = $this->filter ?? 'users';
        $manager = $this->manager();
        $period = $this->period ?? '28d';

        $series = $manager->getSnapshotTimeSeries($account, $period, $metric);

        if (($series['labels'] ?? []) === []) {
            $series = $manager->getTimeSeries($account, $period, $metric);
        }

        return [
            'datasets' => [
                [
                    'label' => ucfirst($metric),
                    'data' => $series['values'],
                    'fill' => false,
                    'borderColor' => '#2563eb',
                    'tension' => 0.3,
                ],
            ],
            'labels' => $series['labels'],
        ];
    }

    protected function getFilters(): ?array
    {
        return [
            'users' => 'Users',
            'sessions' => 'Sessions',
            'pageviews' => 'Pageviews',
            'clicks' => 'Clicks',
            'impressions' => 'Impressions',
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function resolveAccount(): ?SiteKitAccount
    {
        if ($this->accountId) {
            return SiteKitAccount::query()->find($this->accountId);
        }

        return SiteKitAccount::query()
            ->where('id', optional($this->currentAccount())->id)
            ->first();
    }
}
