<?php

declare(strict_types=1);

namespace BoxinCode\FilamentSiteKit;

use BoxinCode\FilamentSiteKit\Models\SiteKitAccount;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class SiteKitAccountManager
{
    public const SESSION_KEY = 'sitekit_account';

    public function current(): ?SiteKitAccount
    {
        if (! Filament::auth()->check()) {
            return null;
        }

        $userId = (int) Filament::auth()->id();

        $accountId = session(self::SESSION_KEY);

        if (is_numeric($accountId)) {
            $account = $this->accessibleAccountsQuery($userId)
                ->where('id', (int) $accountId)
                ->first();

            if ($account) {
                return $account;
            }
        }

        $first = $this->allForUser()->first();

        if ($first) {
            session([self::SESSION_KEY => $first->id]);
        }

        return $first;
    }

    public function setCurrent(int $accountId): bool
    {
        if (! Filament::auth()->check()) {
            return false;
        }

        $userId = (int) Filament::auth()->id();

        $exists = $this->accessibleAccountsQuery($userId)
            ->where('id', $accountId)
            ->exists();

        if (! $exists) {
            return false;
        }

        session([self::SESSION_KEY => $accountId]);

        return true;
    }

    /**
     * @return Collection<int, SiteKitAccount>
     */
    public function allForUser(): Collection
    {
        if (! Filament::auth()->check()) {
            return collect();
        }

        $userId = (int) Filament::auth()->id();

        return $this->accessibleAccountsQuery($userId)
            ->where('provider', 'google')
            ->withCount(['members as shared_users_count'])
            ->orderBy('name')
            ->orderBy('display_name')
            ->orderBy('email')
            ->get();
    }

    public function clearCurrent(): void
    {
        session()->forget(self::SESSION_KEY);
    }

    public function resolveWorkspaceId(?Request $request = null): ?int
    {
        $resolver = config('filament-sitekit.workspace.resolver');

        if (is_callable($resolver)) {
            $resolved = $resolver($request);

            return is_numeric($resolved) ? (int) $resolved : null;
        }

        return null;
    }

    protected function accessibleAccountsQuery(int $userId): Builder
    {
        return SiteKitAccount::query()->where(function (Builder $query) use ($userId): void {
            $query->where('user_id', $userId)
                ->orWhereHas('members', function (Builder $membership) use ($userId): void {
                    $membership->where('user_id', $userId);
                });
        });
    }
}
