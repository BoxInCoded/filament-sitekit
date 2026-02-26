<?php

declare(strict_types=1);

namespace BoxinCode\FilamentSiteKit\Support;

use BoxinCode\FilamentSiteKit\Models\SiteKitAccount;
use BoxinCode\FilamentSiteKit\SiteKitManager;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class SiteKitTrackingVerifyService
{
    public function __construct(
        protected SiteKitManager $manager,
    ) {
    }

    /**
     * @return array{url:string|null,reachable:bool,status_code:int|null,ga4_detected:bool,gtm_detected:bool,meta_detected:bool,markers_detected:bool,message:string,checked_at:string}
     */
    public function verify(SiteKitAccount $account, ?string $url = null, bool $force = false): array
    {
        $resolvedUrl = $url !== null && trim($url) !== '' ? rtrim(trim($url), '/') : $this->resolveSiteUrl($account);

        if ($resolvedUrl === null) {
            $result = $this->emptyResult('Website URL is missing. Add it in Settings and try again.');
            $this->manager->setAccountSetting($account, 'tracking.last_verify', ['value' => $result]);

            return $result;
        }

        $cacheKey = $this->cacheKey($account, $resolvedUrl);

        if ($force) {
            Cache::forget($cacheKey);
        }

        $result = Cache::remember($cacheKey, now()->addSeconds(60), fn (): array => $this->runCheck($resolvedUrl));

        $this->manager->setAccountSetting($account, 'tracking.last_verify', ['value' => $result]);

        return $result;
    }

    public function resolveSiteUrl(SiteKitAccount $account): ?string
    {
        return $this->resolveUrl($account, null);
    }

    protected function resolveUrl(SiteKitAccount $account, ?string $url = null): ?string
    {
        $candidate = trim((string) ($url ?? ''));

        if ($candidate === '') {
            $setting = $this->manager->getAccountSetting($account, 'site.url');
            $candidate = is_array($setting) ? trim((string) ($setting['value'] ?? '')) : trim((string) $setting);
        }

        if ($candidate === '') {
            $legacy = $this->manager->getAccountSetting($account, 'site_url');
            $candidate = is_array($legacy) ? trim((string) ($legacy['value'] ?? '')) : trim((string) $legacy);
        }

        if ($candidate === '') {
            $candidate = trim((string) config('app.url', ''));
        }

        if ($candidate === '' && app()->bound('request')) {
            try {
                $request = request();
                $candidate = trim((string) $request->getSchemeAndHttpHost());
            } catch (\Throwable) {
                $candidate = '';
            }
        }

        if ($candidate === '') {
            return null;
        }

        if (! preg_match('/^https?:\/\//i', $candidate)) {
            $candidate = 'https://' . $candidate;
        }

        return rtrim($candidate, '/');
    }

    protected function cacheKey(SiteKitAccount $account, string $url): string
    {
        return 'filament-sitekit:tracking-verify:' . $account->id . ':' . sha1($url);
    }

    /**
     * @return array{url:string|null,reachable:bool,status_code:int|null,ga4_detected:bool,gtm_detected:bool,meta_detected:bool,markers_detected:bool,message:string,checked_at:string}
     */
    protected function runCheck(string $url): array
    {
        try {
            $response = Http::timeout(10)->get($url);
            $statusCode = $response->status();
            $html = (string) $response->body();
            $reachable = $statusCode >= 200 && $statusCode < 400;

            $ga4 = str_contains($html, 'gtag(')
                || str_contains($html, 'G-')
                || str_contains($html, 'google-analytics.com')
                || str_contains($html, 'googletagmanager.com/gtag');
            $gtm = str_contains($html, 'GTM-');
            $meta = str_contains($html, 'fbq(');
            $markers = str_contains($html, '<!-- SiteKit:GA4 -->') || str_contains($html, '<!-- SiteKit:GTM -->');

            $detected = $ga4 || $gtm || $markers;

            return [
                'url' => $url,
                'reachable' => $reachable,
                'status_code' => $statusCode,
                'ga4_detected' => $ga4,
                'gtm_detected' => $gtm,
                'meta_detected' => $meta,
                'markers_detected' => $markers,
                'message' => ! $reachable
                    ? 'Website responded with a non-success status. Please review URL and hosting response.'
                    : ($detected ? 'Tracking detected ✅' : 'Tracking not detected ⚠️'),
                'checked_at' => now()->toIso8601String(),
            ];
        } catch (\Throwable) {
            return [
                'url' => $url,
                'reachable' => false,
                'status_code' => null,
                'ga4_detected' => false,
                'gtm_detected' => false,
                'meta_detected' => false,
                'markers_detected' => false,
                'message' => 'Website is not reachable right now. Please check URL and try again.',
                'checked_at' => now()->toIso8601String(),
            ];
        }
    }

    /**
     * @return array{url:string|null,reachable:bool,status_code:int|null,ga4_detected:bool,gtm_detected:bool,meta_detected:bool,markers_detected:bool,message:string,checked_at:string}
     */
    protected function emptyResult(string $message): array
    {
        return [
            'url' => null,
            'reachable' => false,
            'status_code' => null,
            'ga4_detected' => false,
            'gtm_detected' => false,
            'meta_detected' => false,
            'markers_detected' => false,
            'message' => $message,
            'checked_at' => now()->toIso8601String(),
        ];
    }
}
