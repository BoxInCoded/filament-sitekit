<?php

declare(strict_types=1);

namespace BoxinCode\FilamentSiteKit\Http\Middleware;

use BoxinCode\FilamentSiteKit\SiteKitAccountManager;
use BoxinCode\FilamentSiteKit\Support\SiteKitTrackingInstaller;
use BoxinCode\FilamentSiteKit\Support\SiteKitTrackingScripts;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response as BaseResponse;

class SiteKitTrackingMiddleware
{
    public function __construct(
        protected SiteKitAccountManager $accountManager,
        protected SiteKitTrackingInstaller $installer,
        protected SiteKitTrackingScripts $scripts,
    ) {
    }

    public function handle(Request $request, Closure $next): BaseResponse
    {
        $response = $next($request);

        try {
            if (! config('filament-sitekit.tracking.enabled', true)) {
                return $response;
            }

            if ((string) config('filament-sitekit.tracking.method', 'middleware') !== 'middleware') {
                return $response;
            }

            if ($this->shouldExcludePath($request->path())) {
                return $response;
            }

            if (! $response instanceof Response) {
                return $response;
            }

            $content = $response->getContent();

            if (! is_string($content) || $content === '') {
                return $response;
            }

            if (! $this->isHtmlResponse($response, $content)) {
                return $response;
            }

            $account = $this->accountManager->current();

            if (! $account) {
                return $response;
            }

            $tracking = $this->installer->config($account);

            if (! $tracking['enabled'] || ! $tracking['type']) {
                return $response;
            }

            if ((bool) config('filament-sitekit.tracking.inject_only_if_missing', true)) {
                if (str_contains($content, '<!-- SiteKit:GA4 -->') || str_contains($content, '<!-- SiteKit:GTM -->')) {
                    return $response;
                }

                if (str_contains($content, 'gtag(') || str_contains($content, 'googletagmanager.com/gtm.js')) {
                    return $response;
                }
            }

            if (! str_contains(strtolower($content), '</head>')) {
                return $response;
            }

            if ($tracking['type'] === 'ga4' && $tracking['measurement_id']) {
                $headSnippet = $this->scripts->ga4Head((string) $tracking['measurement_id']);
                $updated = $this->injectBeforeClosingHead($content, $headSnippet);

                if ($updated !== null) {
                    $response->setContent($updated);
                }

                return $response;
            }

            if ($tracking['type'] === 'gtm' && $tracking['container_id']) {
                $headSnippet = $this->scripts->gtmHead((string) $tracking['container_id']);
                $bodySnippet = $this->scripts->gtmBody((string) $tracking['container_id']);

                $updated = $this->injectBeforeClosingHead($content, $headSnippet);

                if ($updated === null) {
                    return $response;
                }

                $updatedWithBody = $this->injectAfterOpeningBody($updated, $bodySnippet);

                if ($updatedWithBody === null) {
                    return $response;
                }

                $updated = $updatedWithBody;
                $response->setContent($updated);
            }
        } catch (\Throwable) {
            return $response;
        }

        return $response;
    }

    protected function isHtmlResponse(Response $response, string $content): bool
    {
        $contentType = strtolower((string) $response->headers->get('Content-Type', ''));

        if (str_contains($contentType, 'text/html')) {
            return true;
        }

        return $contentType === '' && str_contains(strtolower($content), '</html>');
    }

    protected function shouldExcludePath(string $path): bool
    {
        $patterns = (array) config('filament-sitekit.tracking.exclude_paths', []);

        foreach ($patterns as $pattern) {
            if (is_string($pattern) && Str::is($pattern, $path)) {
                return true;
            }
        }

        return false;
    }

    protected function injectBeforeClosingHead(string $html, string $snippet): ?string
    {
        $position = stripos($html, '</head>');

        if ($position === false) {
            return null;
        }

        return substr($html, 0, $position) . $snippet . "\n" . substr($html, $position);
    }

    protected function injectAfterOpeningBody(string $html, string $snippet): ?string
    {
        if (preg_match('/<body[^>]*>/i', $html, $matches, PREG_OFFSET_CAPTURE) !== 1) {
            return null;
        }

        $matchedTag = (string) $matches[0][0];
        $offset = (int) $matches[0][1] + strlen($matchedTag);

        return substr($html, 0, $offset) . "\n" . $snippet . "\n" . substr($html, $offset);
    }
}
