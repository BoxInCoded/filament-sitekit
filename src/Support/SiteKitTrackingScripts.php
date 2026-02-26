<?php

declare(strict_types=1);

namespace BoxinCode\FilamentSiteKit\Support;

class SiteKitTrackingScripts
{
    public function ga4Head(string $measurementId): string
    {
        $measurementId = htmlspecialchars(trim($measurementId), ENT_QUOTES, 'UTF-8');

        return "<!-- SiteKit:GA4 -->\n"
            . '<script async src="https://www.googletagmanager.com/gtag/js?id=' . $measurementId . '"></script>' . "\n"
            . "<script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config','" . $measurementId . "');</script>\n"
            . "<!-- /SiteKit:GA4 -->";
    }

    public function gtmHead(string $containerId): string
    {
        $containerId = htmlspecialchars(trim($containerId), ENT_QUOTES, 'UTF-8');

        return "<!-- SiteKit:GTM -->\n"
            . "<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);})(window,document,'script','dataLayer','"
            . $containerId
            . "');</script>\n"
            . "<!-- /SiteKit:GTM -->";
    }

    public function gtmBody(string $containerId): string
    {
        $containerId = htmlspecialchars(trim($containerId), ENT_QUOTES, 'UTF-8');

        return "<!-- SiteKit:GTM -->\n"
            . '<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=' . $containerId . '" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>' . "\n"
            . "<!-- /SiteKit:GTM -->";
    }
}
