<?php

declare(strict_types=1);

namespace BoxinCode\FilamentSiteKit\Jobs;

use BoxinCode\FilamentSiteKit\SiteKitManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class SyncAccountJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public int $accountId)
    {
    }

    public function handle(SiteKitManager $manager): void
    {
        $account = $manager->connectedAccounts()->firstWhere('id', $this->accountId);

        if (! $account) {
            return;
        }

        $periods = (array) config('filament-sitekit.sync.periods', ['7d', '28d']);

        foreach ($periods as $period) {
            try {
                $ga4Rows = $manager->buildGa4DailyRows($account, (string) $period);

                if ($ga4Rows !== []) {
                    $manager->saveSnapshot($account, 'ga4', (string) $period, $ga4Rows);
                }
            } catch (Throwable $exception) {
                report($exception);
            }

            try {
                $gscRows = $manager->buildGscDailyRows($account, (string) $period);

                if ($gscRows !== []) {
                    $manager->saveSnapshot($account, 'gsc', (string) $period, $gscRows);
                }
            } catch (Throwable $exception) {
                report($exception);
            }
        }
    }
}
