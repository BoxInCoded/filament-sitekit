<?php

declare(strict_types=1);

namespace BoxinCode\FilamentSiteKit\Commands;

use BoxinCode\FilamentSiteKit\FilamentSiteKit;
use Illuminate\Console\Command;

class SiteKitInstallCommand extends Command
{
    protected $signature = 'sitekit:install';

    protected $description = 'Install Filament SiteKit assets and show next setup steps.';

    public function handle(): int
    {
        $this->info('Installing Filament SiteKit v' . FilamentSiteKit::VERSION . '...');

        $this->call('vendor:publish', [
            '--tag' => 'sitekit-config',
        ]);

        $this->call('vendor:publish', [
            '--tag' => 'sitekit-migrations',
        ]);

        $this->newLine();
        $this->info('Next steps:');
        $this->line('php artisan migrate');
        $this->line('Set Google OAuth redirect: /admin/sitekit/oauth/google/callback');
        $this->line('Open Setup Wizard: /admin/sitekit/setup');

        return self::SUCCESS;
    }
}
