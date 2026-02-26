<?php

declare(strict_types=1);

namespace BoxinCode\FilamentSiteKit\Facades;

use BoxinCode\FilamentSiteKit\SiteKitPlatform;
use Illuminate\Support\Facades\Facade;

/**
 * @method static \BoxinCode\FilamentSiteKit\Models\SiteKitAccount|null account()
 * @method static array<int, \BoxinCode\FilamentSiteKit\Contracts\Connector> connectors()
 * @method static \Illuminate\Bus\Batch sync()
 * @method static array<int, array<string, mixed>> status()
 * @method static array{access_token: string|null, has_token: bool} tokens()
 *
 * @see SiteKitPlatform
 */
class SiteKit extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'filament-sitekit';
    }
}
