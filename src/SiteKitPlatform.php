<?php

declare(strict_types=1);

namespace BoxinCode\FilamentSiteKit;

use BoxinCode\FilamentSiteKit\Jobs\SyncAccountJob;
use BoxinCode\FilamentSiteKit\Models\SiteKitAccount;
use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Bus;

class SiteKitPlatform
{
    public function __construct(
        protected SiteKitAccountManager $accountManager,
        protected SiteKitManager $manager,
        protected SiteKitTokenService $tokenService,
    ) {
    }

    public function account(): ?SiteKitAccount
    {
        return $this->accountManager->current();
    }

    /**
     * @return array<int, \BoxinCode\FilamentSiteKit\Contracts\Connector>
     */
    public function connectors(): array
    {
        return $this->manager->connectors();
    }

    public function sync(): Batch
    {
        $queue = (string) config('filament-sitekit.sync.queue', 'default');

        $jobs = $this->manager->connectedAccounts()
            ->map(fn (SiteKitAccount $account): SyncAccountJob => (new SyncAccountJob((int) $account->id))->onQueue($queue))
            ->all();

        return Bus::batch($jobs)
            ->name('filament-sitekit-sync')
            ->dispatch();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function status(): array
    {
        return $this->manager->moduleCards($this->account());
    }

    /**
     * @return array{access_token: string|null, has_token: bool}
     */
    public function tokens(): array
    {
        $account = $this->account();

        if (! $account) {
            return ['access_token' => null, 'has_token' => false];
        }

        $accessToken = $this->tokenService->getValidAccessToken($account);

        return [
            'access_token' => $accessToken,
            'has_token' => $accessToken !== null,
        ];
    }
}
