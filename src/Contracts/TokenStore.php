<?php

declare(strict_types=1);

namespace BoxinCode\FilamentSiteKit\Contracts;

use BoxinCode\FilamentSiteKit\Models\SiteKitAccount;
use DateTimeInterface;

interface TokenStore
{
    /**
     * @return array{access_token: string, refresh_token: string|null, expires_at: DateTimeInterface|null, scopes: string[]}|null
     */
    public function getValidToken(SiteKitAccount $account): ?array;

    /**
     * @param array<int, string> $scopes
     */
    public function store(
        SiteKitAccount $account,
        string $accessToken,
        ?string $refreshToken,
        ?DateTimeInterface $expiresAt,
        array $scopes
    ): void;

    public function delete(SiteKitAccount $account): void;
}
