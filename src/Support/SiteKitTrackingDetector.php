<?php

declare(strict_types=1);

namespace BoxinCode\FilamentSiteKit\Support;

use BoxinCode\FilamentSiteKit\Models\SiteKitAccount;
use BoxinCode\FilamentSiteKit\SiteKitManager;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class SiteKitTrackingDetector
{
    public function __construct(protected SiteKitManager $manager)
    {
    }

    /**
     * @return array{ga: bool, gtm: bool, meta: bool}
     */
    public function detectTracking(SiteKitAccount $account): array
    {
        $cacheKey = 'filament-sitekit:tracking:' . $account->id;

        return Cache::remember($cacheKey, now()->addMinutes(15), function () use ($account): array {
            $url = $this->siteUrl($account);

            if ($url === null) {
                return ['ga' => false, 'gtm' => false, 'meta' => false];
            }

            try {
                $response = Http::timeout(8)->get($url);
                $html = (string) $response->body();
            } catch (\Throwable) {
                return ['ga' => false, 'gtm' => false, 'meta' => false];
            }

            return [
                'ga' => str_contains($html, 'gtag(') || str_contains($html, 'G-'),
                'gtm' => str_contains($html, 'GTM-'),
                'meta' => str_contains($html, 'fbq('),
            ];
        });
    }

    protected function siteUrl(SiteKitAccount $account): ?string
    {
        $setting = $this->manager->getAccountSetting($account, 'site.url');
        $configured = is_array($setting) ? (string) ($setting['value'] ?? '') : (string) $setting;

        if ($configured === '') {
            $legacy = $this->manager->getAccountSetting($account, 'site_url');
            $configured = is_array($legacy) ? (string) ($legacy['value'] ?? '') : (string) $legacy;
        }

        $url = $configured !== '' ? $configured : (string) config('app.url');

        if ($url === '') {
            return null;
        }

        return rtrim($url, '/');
    }
}
