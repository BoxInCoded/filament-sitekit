<?php

declare(strict_types=1);

namespace BoxinCode\FilamentSiteKit\Support;

use BoxinCode\FilamentSiteKit\Contracts\TokenStore;
use BoxinCode\FilamentSiteKit\Models\SiteKitAccount;
use BoxinCode\FilamentSiteKit\Models\SiteKitToken;
use DateTimeInterface;

class EloquentTokenStore implements TokenStore
{
    public function getValidToken(SiteKitAccount $account): ?array
    {
        $token = SiteKitToken::query()
            ->where('account_id', $account->id)
            ->latest('id')
            ->first();

        if (! $token) {
            return null;
        }

        return [
            'access_token' => $token->access_token,
            'refresh_token' => $token->refresh_token,
            'expires_at' => $token->expires_at,
            'scopes' => $token->scopes ?? [],
        ];
    }

    public function store(
        SiteKitAccount $account,
        string $accessToken,
        ?string $refreshToken,
        ?DateTimeInterface $expiresAt,
        array $scopes
    ): void {
        SiteKitToken::query()->updateOrCreate(
            ['account_id' => $account->id],
            [
                'access_token' => $accessToken,
                'refresh_token' => $refreshToken,
                'expires_at' => $expiresAt,
                'scopes' => $scopes,
            ]
        );
    }

    public function delete(SiteKitAccount $account): void
    {
        SiteKitToken::query()->where('account_id', $account->id)->delete();
    }
}
