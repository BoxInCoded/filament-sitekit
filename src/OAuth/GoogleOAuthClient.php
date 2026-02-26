<?php

declare(strict_types=1);

namespace BoxinCode\FilamentSiteKit\OAuth;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GoogleOAuthClient
{
    public function authorizationUrl(string $state): string
    {
        $query = http_build_query([
            'client_id' => config('filament-sitekit.google.client_id'),
            'redirect_uri' => $this->redirectUri(),
            'response_type' => 'code',
            'scope' => implode(' ', config('filament-sitekit.google.scopes', [])),
            'access_type' => 'offline',
            'prompt' => 'consent',
            'state' => $state,
            'include_granted_scopes' => 'true',
        ]);

        return 'https://accounts.google.com/o/oauth2/v2/auth?' . $query;
    }

    /**
     * @return array{access_token: string, refresh_token: string|null, expires_at: Carbon|null, scopes: string[], id_token?: string}
     */
    public function exchangeCodeForToken(string $code): array
    {
        $response = $this->baseRequest()->asForm()->post('https://oauth2.googleapis.com/token', [
            'client_id' => config('filament-sitekit.google.client_id'),
            'client_secret' => config('filament-sitekit.google.client_secret'),
            'redirect_uri' => $this->redirectUri(),
            'grant_type' => 'authorization_code',
            'code' => $code,
        ])->throw()->json();

        return $this->normalizeTokenResponse($response);
    }

    /**
     * @return array{access_token: string, refresh_token: string|null, expires_at: Carbon|null, scopes: string[]}
     */
    public function refreshToken(string $refreshToken): array
    {
        $response = $this->baseRequest()->asForm()->post('https://oauth2.googleapis.com/token', [
            'client_id' => config('filament-sitekit.google.client_id'),
            'client_secret' => config('filament-sitekit.google.client_secret'),
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
        ])->throw()->json();

        $normalized = $this->normalizeTokenResponse($response);
        $normalized['refresh_token'] = $refreshToken;

        return $normalized;
    }

    /**
     * @return array{email: string|null, name: string|null}
     */
    public function fetchProfile(string $accessToken): array
    {
        $response = $this->authorizedRequest($accessToken)
            ->get('https://www.googleapis.com/oauth2/v2/userinfo')
            ->throw()
            ->json();

        return [
            'email' => Arr::get($response, 'email'),
            'name' => Arr::get($response, 'name'),
        ];
    }

    /**
     * @return array{properties: array<int, array{propertyId: string, displayName: string}>}
     */
    public function listGa4Properties(string $accessToken): array
    {
        $response = $this->authorizedRequest($accessToken)
            ->get('https://analyticsadmin.googleapis.com/v1beta/accountSummaries')
            ->throw()
            ->json();

        $properties = [];

        foreach (Arr::get($response, 'accountSummaries', []) as $accountSummary) {
            foreach (Arr::get($accountSummary, 'propertySummaries', []) as $propertySummary) {
                $propertyPath = (string) Arr::get($propertySummary, 'property', '');
                $propertyId = str_replace('properties/', '', $propertyPath);

                if ($propertyId === '') {
                    continue;
                }

                $properties[] = [
                    'propertyId' => $propertyId,
                    'displayName' => (string) Arr::get($propertySummary, 'displayName', $propertyId),
                ];
            }
        }

        return ['properties' => $properties];
    }

    /**
     * @return array{siteEntry: array<int, array{siteUrl: string, permissionLevel: string}>}
     */
    public function listSearchConsoleSites(string $accessToken): array
    {
        $response = $this->authorizedRequest($accessToken)
            ->get('https://www.googleapis.com/webmasters/v3/sites')
            ->throw()
            ->json();

        return [
            'siteEntry' => Arr::get($response, 'siteEntry', []),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function runGa4Report(string $accessToken, string $propertyId, array $payload): array
    {
        return $this->authorizedRequest($accessToken)
            ->post("https://analyticsdata.googleapis.com/v1beta/properties/{$propertyId}:runReport", $payload)
            ->throw()
            ->json();
    }

    /**
     * @return array<string, mixed>
     */
    public function runSearchConsoleQuery(string $accessToken, string $siteUrl, array $payload): array
    {
        $encodedSite = rawurlencode($siteUrl);

        return $this->authorizedRequest($accessToken)
            ->post("https://www.googleapis.com/webmasters/v3/sites/{$encodedSite}/searchAnalytics/query", $payload)
            ->throw()
            ->json();
    }

    protected function redirectUri(): string
    {
        return (string) (config('filament-sitekit.google.redirect_uri')
            ?: route('filament-sitekit.google.callback'));
    }

    /**
     * @param array<string, mixed> $response
     * @return array{access_token: string, refresh_token: string|null, expires_at: Carbon|null, scopes: string[]}
     */
    protected function normalizeTokenResponse(array $response): array
    {
        $accessToken = Arr::get($response, 'access_token');

        if (! is_string($accessToken) || $accessToken === '') {
            throw new RuntimeException('Google OAuth did not return an access token.');
        }

        $expiresIn = (int) Arr::get($response, 'expires_in', 0);
        $expiresAt = $expiresIn > 0 ? now()->addSeconds($expiresIn) : null;
        $scopeString = (string) Arr::get($response, 'scope', '');

        return [
            'access_token' => $accessToken,
            'refresh_token' => Arr::get($response, 'refresh_token'),
            'expires_at' => $expiresAt,
            'scopes' => $scopeString !== '' ? preg_split('/\s+/', trim($scopeString)) ?: [] : [],
        ];
    }

    protected function baseRequest(): PendingRequest
    {
        return Http::acceptJson()->timeout(20);
    }

    protected function authorizedRequest(string $accessToken): PendingRequest
    {
        return $this->baseRequest()->withToken($accessToken);
    }
}
