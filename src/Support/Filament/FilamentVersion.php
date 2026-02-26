<?php

declare(strict_types=1);

namespace BoxinCode\FilamentSiteKit\Support\Filament;

use Composer\InstalledVersions;

class FilamentVersion
{
    public function major(): int
    {
        try {
            if (! class_exists(InstalledVersions::class)) {
                return 0;
            }

            $version = InstalledVersions::getVersion('filament/filament');

            if (! is_string($version) || $version === '') {
                return 0;
            }

            if (preg_match('/^(\d+)/', $version, $matches) !== 1) {
                return 0;
            }

            return (int) $matches[1];
        } catch (\Throwable) {
            return 0;
        }
    }

    public function isV3(): bool
    {
        return $this->major() === 3;
    }

    public function isV4(): bool
    {
        return $this->major() === 4;
    }

    public function isV5(): bool
    {
        return $this->major() === 5;
    }
}
