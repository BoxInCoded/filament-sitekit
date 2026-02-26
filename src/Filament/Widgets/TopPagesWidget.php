<?php

declare(strict_types=1);

namespace BoxinCode\FilamentSiteKit\Filament\Widgets;

class TopPagesWidget extends BaseWidget
{
    protected static string $view = 'filament-sitekit::widgets.top-pages-widget';

    public ?string $period = '7d';

    /**
     * @return array<int, array{page: string, views: int|float}>
     */
    public function pages(): array
    {
        $account = $this->currentAccount();

        if (! $account) {
            return [];
        }

        $manager = $this->manager();
        $period = $this->period ?? '7d';

        $ga4 = $manager->getMetrics($account, 'ga4', $period);
        $gsc = $manager->getMetrics($account, 'gsc', $period);

        $ga4Pages = collect(data_get($ga4, 'top_pages', []))
            ->map(fn (array $row): array => [
                'page' => (string) ($row['page'] ?? '/'),
                'views' => (int) ($row['views'] ?? 0),
            ]);

        $gscPages = collect(data_get($gsc, 'top_pages', []))
            ->map(fn (array $row): array => [
                'page' => (string) ($row['label'] ?? '/'),
                'views' => (float) ($row['clicks'] ?? 0),
            ]);

        return $ga4Pages
            ->merge($gscPages)
            ->sortByDesc('views')
            ->take(10)
            ->values()
            ->all();
    }
}
