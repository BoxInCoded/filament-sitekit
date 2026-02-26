<?php

declare(strict_types=1);

namespace BoxinCode\FilamentSiteKit\Tests;

use BoxinCode\FilamentSiteKit\FilamentSiteKitServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            FilamentSiteKitServiceProvider::class,
        ];
    }
}
