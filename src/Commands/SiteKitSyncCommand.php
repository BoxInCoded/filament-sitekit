<?php

declare(strict_types=1);

namespace BoxinCode\FilamentSiteKit\Commands;

use BoxinCode\FilamentSiteKit\Jobs\SyncAccountJob;
use BoxinCode\FilamentSiteKit\SiteKitLicense;
use BoxinCode\FilamentSiteKit\SiteKitManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Bus;

class SiteKitSyncCommand extends Command
{
    protected $signature = 'sitekit:sync';

    protected $description = 'Fetch and store site kit snapshots for all connected accounts.';

    public function __construct(
        protected SiteKitManager $manager,
        protected SiteKitLicense $license,
    )
    {
        parent::__construct();
    }

    public function handle(): int
    {
        if (! config('filament-sitekit.sync.enabled', true)) {
            $this->warn('Sync is disabled in config.');

            return self::SUCCESS;
        }

        $accounts = $this->manager->connectedAccounts();
        $queue = (string) config('filament-sitekit.sync.queue', 'default');

        if ($accounts->isEmpty()) {
            $this->info('No connected accounts to sync.');

            return self::SUCCESS;
        }

        if ($this->license->allowsQueueSync()) {
            $jobs = $accounts
                ->map(fn ($account): SyncAccountJob => (new SyncAccountJob((int) $account->id))->onQueue($queue))
                ->all();

            $batch = Bus::batch($jobs)
                ->name('filament-sitekit-sync')
                ->dispatch();

            $this->info('Site Kit sync batch dispatched.');
            $this->line('Batch ID: ' . $batch->id);

            return self::SUCCESS;
        }

        $periods = $this->manager->allowedPeriods();

        foreach ($accounts as $account) {
            foreach ($this->manager->connectors() as $connector) {
                if ($connector->setupStatus($account) !== 'ready') {
                    continue;
                }

                foreach ($periods as $period) {
                    $this->manager->saveSnapshot(
                        $account,
                        $connector->key(),
                        (string) $period,
                        $connector->fetchSnapshot($account, (string) $period)
                    );
                }
            }
        }

        $this->warn('Upgrade to Pro');
        $this->line('Queue sync is available on Agency and Enterprise plans.');
        $this->info('Direct sync completed.');

        return self::SUCCESS;
    }
}
