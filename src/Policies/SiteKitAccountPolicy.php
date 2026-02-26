<?php

declare(strict_types=1);

namespace BoxinCode\FilamentSiteKit\Policies;

use BoxinCode\FilamentSiteKit\Models\SiteKitAccount;
use BoxinCode\FilamentSiteKit\SiteKitLicense;

class SiteKitAccountPolicy
{
    public function viewAny(mixed $user): bool
    {
        return $user !== null;
    }

    public function view(mixed $user, SiteKitAccount $account): bool
    {
        return $this->role($user, $account) !== null;
    }

    public function switch(mixed $user, SiteKitAccount $account): bool
    {
        return $this->view($user, $account);
    }

    public function update(mixed $user, SiteKitAccount $account): bool
    {
        return in_array($this->role($user, $account), ['owner', 'admin'], true);
    }

    public function configureConnectors(mixed $user, SiteKitAccount $account): bool
    {
        return in_array($this->role($user, $account), ['owner', 'admin'], true);
    }

    public function sync(mixed $user, SiteKitAccount $account): bool
    {
        return in_array($this->role($user, $account), ['owner', 'admin'], true);
    }

    public function reconnect(mixed $user, SiteKitAccount $account): bool
    {
        return $this->role($user, $account) === 'owner';
    }

    public function delete(mixed $user, SiteKitAccount $account): bool
    {
        return $this->role($user, $account) === 'owner';
    }

    public function manageUsers(mixed $user, SiteKitAccount $account): bool
    {
        return $this->role($user, $account) === 'owner'
            && app(SiteKitLicense::class)->allowsAccountSharing();
    }

    protected function role(mixed $user, SiteKitAccount $account): ?string
    {
        $userId = (int) ($user?->id ?? 0);

        if ($userId <= 0) {
            return null;
        }

        return $account->roleForUserId($userId);
    }
}
