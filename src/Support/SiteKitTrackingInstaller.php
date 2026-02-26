<?php

declare(strict_types=1);

namespace BoxinCode\FilamentSiteKit\Support;

use BoxinCode\FilamentSiteKit\Models\SiteKitAccount;
use BoxinCode\FilamentSiteKit\SiteKitManager;

class SiteKitTrackingInstaller
{
    public function __construct(
        protected SiteKitManager $manager,
    ) {
    }

    public function installGa4(SiteKitAccount $account, string $measurementId): void
    {
        $measurementId = trim($measurementId);

        $this->set($account, 'tracking.enabled', true);
        $this->set($account, 'tracking.type', 'ga4');
        $this->set($account, 'tracking.measurement_id', $measurementId !== '' ? $measurementId : null);
        $this->set($account, 'tracking.container_id', null);
    }

    public function installGtm(SiteKitAccount $account, string $containerId): void
    {
        $containerId = trim($containerId);

        $this->set($account, 'tracking.enabled', true);
        $this->set($account, 'tracking.type', 'gtm');
        $this->set($account, 'tracking.container_id', $containerId !== '' ? $containerId : null);
        $this->set($account, 'tracking.measurement_id', null);
    }

    public function uninstall(SiteKitAccount $account): void
    {
        $this->set($account, 'tracking.enabled', false);
        $this->set($account, 'tracking.type', null);
        $this->set($account, 'tracking.measurement_id', null);
        $this->set($account, 'tracking.container_id', null);
    }

    public function isConfigured(SiteKitAccount $account): bool
    {
        $type = $this->configuredType($account);

        if ($type === 'ga4') {
            return $this->measurementId($account) !== null;
        }

        if ($type === 'gtm') {
            return $this->containerId($account) !== null;
        }

        return false;
    }

    public function configuredType(SiteKitAccount $account): ?string
    {
        $value = $this->get($account, 'tracking.type');

        return in_array($value, ['ga4', 'gtm'], true) ? $value : null;
    }

    public function isEnabled(SiteKitAccount $account): bool
    {
        $value = $this->get($account, 'tracking.enabled');

        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value === 1;
        }

        if (is_string($value)) {
            return in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
        }

        return false;
    }

    public function measurementId(SiteKitAccount $account): ?string
    {
        $value = trim((string) ($this->get($account, 'tracking.measurement_id') ?? ''));

        return $value !== '' ? $value : null;
    }

    public function containerId(SiteKitAccount $account): ?string
    {
        $value = trim((string) ($this->get($account, 'tracking.container_id') ?? ''));

        return $value !== '' ? $value : null;
    }

    /**
     * @return array{enabled: bool, type: ?string, measurement_id: ?string, container_id: ?string}
     */
    public function config(SiteKitAccount $account): array
    {
        return [
            'enabled' => $this->isEnabled($account),
            'type' => $this->configuredType($account),
            'measurement_id' => $this->measurementId($account),
            'container_id' => $this->containerId($account),
        ];
    }

    protected function set(SiteKitAccount $account, string $key, mixed $value): void
    {
        $this->manager->setAccountSetting($account, $key, ['value' => $value]);
    }

    protected function get(SiteKitAccount $account, string $key): mixed
    {
        $setting = $this->manager->getAccountSetting($account, $key);

        if (is_array($setting) && array_key_exists('value', $setting)) {
            return $setting['value'];
        }

        return $setting;
    }
}
