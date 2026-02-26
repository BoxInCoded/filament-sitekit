<?php

declare(strict_types=1);

namespace BoxinCode\FilamentSiteKit\Commands;

use BoxinCode\FilamentSiteKit\Models\SiteKitAccount;
use BoxinCode\FilamentSiteKit\SiteKitManager;
use Illuminate\Console\Command;

class SiteKitDoctorCommand extends Command
{
    protected $signature = 'sitekit:doctor';

    protected $description = 'Run diagnostics for configuration, OAuth connectivity and selected settings.';

    public function __construct(protected SiteKitManager $manager)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('Running Site Kit diagnostics...');

        $this->checkConfig();
        $this->checkAccounts();

        $this->info('Diagnostics complete.');

        return self::SUCCESS;
    }

    protected function checkConfig(): void
    {
        $clientId = (string) config('filament-sitekit.google.client_id', '');
        $clientSecret = (string) config('filament-sitekit.google.client_secret', '');

        $this->line($clientId !== '' ? '✓ GOOGLE_CLIENT_ID configured' : '✗ GOOGLE_CLIENT_ID missing');
        $this->line($clientSecret !== '' ? '✓ GOOGLE_CLIENT_SECRET configured' : '✗ GOOGLE_CLIENT_SECRET missing');

        try {
            $callback = route('filament-sitekit.google.callback');
            $this->line("✓ OAuth callback route available: {$callback}");
        } catch (\Throwable) {
            $this->line('✗ OAuth callback route unavailable');
        }
    }

    protected function checkAccounts(): void
    {
        $accounts = SiteKitAccount::query()->where('provider', 'google')->get();

        if ($accounts->isEmpty()) {
            $this->warn('No connected Google accounts found.');

            return;
        }

        foreach ($accounts as $account) {
            $this->line("Account #{$account->id} ({$account->email})");

            $token = $account->token()->latest('id')->first();
            if (! $token) {
                $this->line('  ✗ Missing token');
                continue;
            }

            $this->line($token->isExpired() ? '  ⚠ Token appears expired' : '  ✓ Token expiry looks valid');

            $ga4 = $this->manager->getAccountSetting($account, 'ga4_property_id');
            $gsc = $this->manager->getAccountSetting($account, 'gsc_site_url');

            $this->line(! empty($ga4['value'] ?? null) ? '  ✓ GA4 property selected' : '  ⚠ GA4 property not selected');
            $this->line(! empty($gsc['value'] ?? null) ? '  ✓ GSC site selected' : '  ⚠ GSC site not selected');
        }
    }
}
