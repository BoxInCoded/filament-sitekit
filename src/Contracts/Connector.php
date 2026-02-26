<?php

declare(strict_types=1);

namespace BoxinCode\FilamentSiteKit\Contracts;

use BoxinCode\FilamentSiteKit\Models\SiteKitAccount;

interface Connector
{
    public function key(): string;

    public function label(): string;

    public function description(): string;

    public function icon(): string;

    public function isEnabled(): bool;

    /**
        * @return 'ready'|'needs_setup'|'disconnected'|'error'
     */
    public function setupStatus(SiteKitAccount $account): string;

    /**
     * @return array<int, array{level: string, title: string, description: string, action_url?: string}>
     */
    public function healthCheck(SiteKitAccount $account): array;

    /**
     * @return array<string, mixed>
     */
    public function fetchSnapshot(SiteKitAccount $account, string $period): array;

    /**
     * @return array{labels: array<int, string>, values: array<int, int|float>}
     */
    public function fetchTimeSeries(SiteKitAccount $account, string $period, string $metric): array;
}
