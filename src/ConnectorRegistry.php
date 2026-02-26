<?php

declare(strict_types=1);

namespace BoxinCode\FilamentSiteKit;

use BoxinCode\FilamentSiteKit\Contracts\Connector;
use BoxinCode\FilamentSiteKit\Models\SiteKitSetting;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Arr;

class ConnectorRegistry
{
    public function __construct(
        protected Container $container,
        protected SiteKitLicense $license,
    )
    {
    }

    /**
     * @return array<int, Connector>
     */
    public function all(): array
    {
        $connectors = [];

        foreach ((array) config('filament-sitekit.connectors.available', []) as $className) {
            if (! is_string($className) || $className === '') {
                continue;
            }

            if (! class_exists($className)) {
                continue;
            }

            $instance = $this->container->make($className);

            if (! $instance instanceof Connector) {
                continue;
            }

            $connectors[] = $instance;
        }

        return $connectors;
    }

    /**
     * @return array<int, Connector>
     */
    public function enabled(): array
    {
        return collect($this->all())
            ->filter(fn (Connector $connector): bool => $this->isEnabled($connector->key(), $connector->isEnabled()))
            ->values()
            ->all();
    }

    public function find(string $key): ?Connector
    {
        return collect($this->all())->first(
            fn (Connector $connector): bool => $connector->key() === $key
        );
    }

    public function isEnabled(string $key, bool $default): bool
    {
        if (! $this->license->allowsConnector($key)) {
            return false;
        }

        $setting = SiteKitSetting::query()
            ->whereNull('account_id')
            ->where('key', 'module_enabled_' . $key)
            ->first();

        if (! $setting) {
            return $default;
        }

        return (bool) Arr::get($setting->value, 'enabled', $default);
    }
}
