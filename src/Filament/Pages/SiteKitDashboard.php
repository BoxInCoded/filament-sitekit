<?php

declare(strict_types=1);

namespace BoxinCode\FilamentSiteKit\Filament\Pages;

use BoxInCoded\FilamentSiteKit\Core\Support\ExtensionsRegistry;
use BoxinCode\FilamentSiteKit\Filament\Widgets\TrafficChartWidget;
use BoxinCode\FilamentSiteKit\Models\SiteKitAccount;
use BoxinCode\FilamentSiteKit\SiteKitAccountManager;
use BoxinCode\FilamentSiteKit\SiteKitManager;
use BoxinCode\FilamentSiteKit\Support\SiteKitSetupStatus;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;

class SiteKitDashboard extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $navigationGroup = 'Site Kit';

    protected static ?int $navigationSort = 1;

    protected static ?string $title = 'Site Kit Dashboard';

    protected string $view = 'filament-sitekit::pages.dashboard';

    public string $period = '28d';

    public string $chartMetric = 'users';

    /**
     * @var array<int, array{label: string, value: int|float|string, delta: float|null, sparkline: array<int, float>}>
     */
    public array $keyMetrics = [];

    /**
     * @var array<int, array{source: string, sessions: int}>
     */
    public array $trafficAcquisition = [];

    /**
     * @var array<int, array{page: string, views: int|float}>
     */
    public array $topContent = [];

    /**
     * @var array<int, array{label: string, clicks: float, impressions: float, ctr: float, position: float}>
     */
    public array $searchTraffic = [];

    public bool $configured = false;

    public function mount(SiteKitManager $manager): void
    {
        $this->authorizeAccess();

        $allowedPeriods = $manager->allowedPeriods();

        $requestedPeriod = (string) request()->query('period', '28d');
        $this->period = in_array($requestedPeriod, $allowedPeriods, true)
            ? $requestedPeriod
            : ($allowedPeriods[0] ?? '7d');

        $requestedMetric = (string) request()->query('metric', 'users');
        $allowedMetrics = ['users', 'sessions', 'pageviews'];

        $this->chartMetric = in_array($requestedMetric, $allowedMetrics, true)
            ? $requestedMetric
            : 'users';

        $account = $this->currentAccount();
        $this->configured = app(SiteKitSetupStatus::class)->setupComplete();

        if (! $this->configured) {
            redirect(SiteKitSetupWizard::getUrl());

            return;
        }

        if (! $account) {
            return;
        }

        $ga4 = $manager->getMetrics($account, 'ga4', $this->period);

        $deltas = $this->calculateDeltas($manager, $account);

        $sparkUsers = $this->sparklineValues($manager->getTimeSeries($account, $this->period, 'users')['values'] ?? []);
        $sparkSessions = $this->sparklineValues($manager->getTimeSeries($account, $this->period, 'sessions')['values'] ?? []);
        $sparkPageviews = $this->sparklineValues($manager->getTimeSeries($account, $this->period, 'pageviews')['values'] ?? []);

        $this->keyMetrics = [
            ['label' => 'Users', 'value' => (int) data_get($ga4, 'metrics.totalUsers', 0), 'delta' => $deltas['users'], 'sparkline' => $sparkUsers],
            ['label' => 'Sessions', 'value' => (int) data_get($ga4, 'metrics.sessions', 0), 'delta' => $deltas['sessions'], 'sparkline' => $sparkSessions],
            ['label' => 'Pageviews', 'value' => (int) data_get($ga4, 'metrics.screenPageViews', 0), 'delta' => $deltas['pageviews'], 'sparkline' => $sparkPageviews],
        ];

        $this->trafficAcquisition = (array) data_get($ga4, 'traffic_acquisition', []);
        $this->topContent = collect((array) data_get($ga4, 'top_pages', []))
            ->map(fn (array $row): array => [
                'page' => (string) ($row['page'] ?? '/'),
                'views' => (int) ($row['views'] ?? 0),
            ])
            ->values()
            ->all();
        $this->searchTraffic = [];
    }

    protected function getHeaderActions(): array
    {
        $actions = [
            Action::make('period_7d')
                ->label('Last 7 days')
                ->icon('heroicon-o-calendar-days')
                ->color($this->period === '7d' ? 'primary' : 'gray')
                ->url(static::getUrl(['period' => '7d', 'metric' => $this->chartMetric])),
        ];

        $actions[] = Action::make('metric_users')
            ->label('Users')
            ->icon('heroicon-o-user-group')
            ->color($this->chartMetric === 'users' ? 'primary' : 'gray')
            ->url(static::getUrl(['period' => $this->period, 'metric' => 'users']));

        $actions[] = Action::make('metric_sessions')
            ->label('Sessions')
            ->icon('heroicon-o-arrow-trending-up')
            ->color($this->chartMetric === 'sessions' ? 'primary' : 'gray')
            ->url(static::getUrl(['period' => $this->period, 'metric' => 'sessions']));

        $actions[] = Action::make('metric_pageviews')
            ->label('Pageviews')
            ->icon('heroicon-o-document-text')
            ->color($this->chartMetric === 'pageviews' ? 'primary' : 'gray')
            ->url(static::getUrl(['period' => $this->period, 'metric' => 'pageviews']));

        $actions[] = Action::make('modules')
            ->label('Modules')
            ->icon('heroicon-o-squares-2x2')
            ->url(SiteKitModules::getUrl());

        $actions[] = Action::make('settings')
            ->label('Settings')
            ->icon('heroicon-o-cog-6-tooth')
            ->url(SiteKitSettings::getUrl());

        return $actions;
    }

    protected function getHeaderWidgets(): array
    {
        $coreWidgets = [
            TrafficChartWidget::class,
        ];

        $extensions = app(ExtensionsRegistry::class);

        return array_values(array_unique(array_merge($coreWidgets, $extensions->widgets())));
    }

    /**
     * @return array<string, mixed>
     */
    protected function getHeaderWidgetsData(): array
    {
        return [
            'period' => $this->period,
            'accountId' => $this->currentAccount()?->id,
            'filter' => $this->chartMetric,
        ];
    }

    /**
     * @return array{users: float|null, sessions: float|null, pageviews: float|null, clicks: float|null, impressions: float|null}
     */
    protected function calculateDeltas(SiteKitManager $manager, SiteKitAccount $account): array
    {
        $days = $this->periodDays($this->period);
        $extendedPeriod = ($days * 2) . 'd';

        $metrics = ['users', 'sessions', 'pageviews'];
        $deltas = [];

        foreach ($metrics as $metric) {
            $series = $manager->getTimeSeries($account, $extendedPeriod, $metric);
            $values = collect((array) Arr::get($series, 'values', []))
                ->map(fn ($value): float => (float) $value)
                ->values();

            if ($values->count() < ($days * 2)) {
                $deltas[$metric] = null;
                continue;
            }

            $previousSum = $values->slice(-$days * 2, $days)->sum();
            $currentSum = $values->slice(-$days, $days)->sum();

            if ($previousSum <= 0) {
                $deltas[$metric] = null;
                continue;
            }

            $deltas[$metric] = round((($currentSum - $previousSum) / $previousSum) * 100, 2);
        }

        return [
            'users' => $deltas['users'] ?? null,
            'sessions' => $deltas['sessions'] ?? null,
            'pageviews' => $deltas['pageviews'] ?? null,
            'clicks' => null,
            'impressions' => null,
        ];
    }

    protected function periodDays(string $period): int
    {
        return match ($period) {
            '90d' => 90,
            '28d' => 28,
            default => 7,
        };
    }

    /**
     * @param array<int, mixed> $values
     * @return array<int, float>
     */
    protected function sparklineValues(array $values): array
    {
        $collection = collect($values)
            ->map(fn ($value): float => (float) $value)
            ->values();

        if ($collection->isEmpty()) {
            return [];
        }

        return $collection->slice(-14)->values()->all();
    }

    protected function authorizeAccess(): void
    {
        abort_unless(Filament::auth()->check(), 403);
        abort_unless(Gate::allows((string) config('filament-sitekit.authorization.gate', 'manageSiteKit')), 403);

        $account = $this->currentAccount();

        if ($account && ! Gate::allows('view', $account)) {
            abort(403);
        }
    }

    protected function currentAccount(): ?SiteKitAccount
    {
        return app(SiteKitAccountManager::class)->current();
    }
}
