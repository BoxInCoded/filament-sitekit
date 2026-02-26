<?php

declare(strict_types=1);

namespace BoxinCode\FilamentSiteKit;

use BoxinCode\FilamentSiteKit\Contracts\TokenStore;
use BoxinCode\FilamentSiteKit\Models\SiteKitAccount;
use BoxinCode\FilamentSiteKit\Models\SiteKitToken;
use BoxinCode\FilamentSiteKit\OAuth\GoogleOAuthClient;
use Throwable;

class SiteKitTokenService
{
    public function __construct(
        protected TokenStore $tokenStore,
        protected GoogleOAuthClient $googleOAuthClient,
    ) {
    }

    public function getValidAccessToken(SiteKitAccount $account): ?string
    {
        $token = SiteKitToken::query()
            ->where('account_id', $account->id)
            ->latest('id')
            ->first();

        if (! $token) {
            return null;
        }

        if ($token->isExpired()) {
            if (! $token->refresh_token) {
                return null;
            }

            try {
                $refreshed = $this->googleOAuthClient->refreshToken($token->refresh_token);

                $this->tokenStore->store(
                    $account,
                    $refreshed['access_token'],
                    $refreshed['refresh_token'] ?? $token->refresh_token,
                    $refreshed['expires_at'],
                    $refreshed['scopes'] === [] ? ($token->scopes ?? []) : $refreshed['scopes']
                );
            } catch (Throwable $exception) {
                report($exception);

                return null;
            }

            $token->refresh();
        }

        return (string) $token->access_token;
    }
}
