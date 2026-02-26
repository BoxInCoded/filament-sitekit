<?php

declare(strict_types=1);

namespace BoxinCode\FilamentSiteKit\Filament\Pages;

use BoxInCoded\FilamentSiteKit\Core\Contracts\PlanGuard;
use BoxinCode\FilamentSiteKit\Models\SiteKitAccount;
use BoxinCode\FilamentSiteKit\SiteKitAccountManager;
use BoxinCode\FilamentSiteKit\SiteKitLicense;
use BoxinCode\FilamentSiteKit\SiteKitManager;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

class SiteKitAccounts extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationGroup = 'Site Kit';

    protected static ?int $navigationSort = 2;

    protected static ?string $title = 'Accounts';

    protected string $view = 'filament-sitekit::pages.accounts';

    /**
     * @var array<int, SiteKitAccount>
     */
    public array $accounts = [];

    public ?int $editingAccountId = null;

    public string $editingName = '';

    public ?int $managingAccountId = null;

    public ?int $shareUserId = null;

    public string $shareRole = 'viewer';

    /**
     * @var array<int, array{id: int, name: string, email: string|null, role: string}>
     */
    public array $managedUsers = [];

    public static function shouldRegisterNavigation(): bool
    {
        return app(SiteKitLicense::class)->allowsMultipleAccounts();
    }

    public function mount(SiteKitAccountManager $accountManager): void
    {
        $this->authorizeAccess();
        $this->refreshAccounts($accountManager);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('add_account')
                ->label('+ Add account')
                ->icon('heroicon-o-plus-circle')
                ->action('addAccount'),
        ];
    }

    public function addAccount(SiteKitAccountManager $accountManager, SiteKitLicense $license): void
    {
        $guard = app(PlanGuard::class);

        if (! $guard->canCreateAnotherAccount(Filament::auth()->user())) {
            Notification::make()
                ->warning()
                ->title('Upgrade to Pro')
                ->body('Free plan supports only one account. Upgrade to Pro to add more.')
                ->send();

            return;
        }

        redirect()->to(route('filament-sitekit.google.connect'));
    }

    public function switchAccount(int $accountId, SiteKitAccountManager $accountManager): void
    {
        $account = $this->findAccessibleAccount($accountId);

        if ($account && Gate::allows('switch', $account) && $accountManager->setCurrent($accountId)) {
            Notification::make()
                ->success()
                ->title('Switched account')
                ->body('Active account has been updated.')
                ->send();
        }

        $this->refreshAccounts($accountManager);
    }

    public function startEdit(int $accountId): void
    {
        $account = $this->findAccessibleAccount($accountId);

        if (! $account || ! Gate::allows('update', $account)) {
            return;
        }

        $this->editingAccountId = $account->id;
        $this->editingName = (string) ($account->name ?? $account->display_name ?? '');
    }

    public function saveEdit(SiteKitAccountManager $accountManager): void
    {
        if (! $this->editingAccountId) {
            return;
        }

        $account = $this->findAccessibleAccount($this->editingAccountId);

        if (! $account || ! Gate::allows('update', $account)) {
            return;
        }

        $account->name = trim($this->editingName) !== '' ? trim($this->editingName) : null;
        $account->save();

        $this->editingAccountId = null;
        $this->editingName = '';

        $this->refreshAccounts($accountManager);

        Notification::make()
            ->success()
            ->title('Account updated')
            ->send();
    }

    public function deleteAccount(int $accountId, SiteKitAccountManager $accountManager, SiteKitManager $siteKitManager): void
    {
        $account = $this->findAccessibleAccount($accountId);

        if (! $account || ! Gate::allows('delete', $account)) {
            return;
        }

        $siteKitManager->clearAccountData($account);
        $account->users()->detach();
        $account->delete();

        if (optional($accountManager->current())->id === $accountId) {
            $accountManager->clearCurrent();
        }

        $this->refreshAccounts($accountManager);

        Notification::make()
            ->success()
            ->title('Account deleted')
            ->send();
    }

    public function reconnectAccount(int $accountId, SiteKitAccountManager $accountManager): void
    {
        $account = $this->findAccessibleAccount($accountId);

        if (! $account || ! Gate::allows('reconnect', $account)) {
            return;
        }

        $accountManager->setCurrent($accountId);
        redirect()->to(route('filament-sitekit.google.connect'));
    }

    public function startManageUsers(int $accountId): void
    {
        if (! app(SiteKitLicense::class)->allowsAccountSharing()) {
            Notification::make()
                ->warning()
                ->title('Upgrade to Pro')
                ->body('Account sharing is locked on your plan.')
                ->send();

            return;
        }

        $account = $this->findAccessibleAccount($accountId);

        if (! $account || ! Gate::allows('manageUsers', $account)) {
            return;
        }

        $this->managingAccountId = $account->id;
        $this->shareUserId = null;
        $this->shareRole = 'viewer';

        $this->loadManagedUsers($account);
    }

    public function stopManageUsers(): void
    {
        $this->managingAccountId = null;
        $this->managedUsers = [];
        $this->shareUserId = null;
        $this->shareRole = 'viewer';
    }

    public function addSharedUser(): void
    {
        if (! app(SiteKitLicense::class)->allowsAccountSharing()) {
            Notification::make()
                ->warning()
                ->title('Upgrade to Pro')
                ->body('Account sharing is locked on your plan.')
                ->send();

            return;
        }

        if (! $this->managingAccountId || ! is_numeric($this->shareUserId)) {
            return;
        }

        $account = $this->findAccessibleAccount($this->managingAccountId);

        if (! $account || ! Gate::allows('manageUsers', $account)) {
            return;
        }

        $authModel = config('auth.providers.users.model');

        if (! is_string($authModel) || ! class_exists($authModel)) {
            return;
        }

        /** @var Model|null $user */
        $user = $authModel::query()->find((int) $this->shareUserId);

        if (! $user || ! isset($user->id)) {
            Notification::make()->warning()->title('User not found')->send();

            return;
        }

        $role = in_array($this->shareRole, ['admin', 'viewer'], true) ? $this->shareRole : 'viewer';

        $account->users()->syncWithoutDetaching([
            (int) $user->id => ['role' => $role],
        ]);

        $this->shareUserId = null;

        $this->loadManagedUsers($account);

        Notification::make()
            ->success()
            ->title('User added')
            ->send();
    }

    public function updateSharedUserRole(int $userId, string $role): void
    {
        if (! $this->managingAccountId) {
            return;
        }

        $account = $this->findAccessibleAccount($this->managingAccountId);

        if (! $account || ! Gate::allows('manageUsers', $account)) {
            return;
        }

        if (! in_array($role, ['admin', 'viewer'], true)) {
            return;
        }

        if ((int) $account->user_id === $userId) {
            return;
        }

        $account->users()->updateExistingPivot($userId, ['role' => $role]);

        $this->loadManagedUsers($account);
    }

    public function removeSharedUser(int $userId): void
    {
        if (! $this->managingAccountId) {
            return;
        }

        $account = $this->findAccessibleAccount($this->managingAccountId);

        if (! $account || ! Gate::allows('manageUsers', $account)) {
            return;
        }

        if ((int) $account->user_id === $userId) {
            return;
        }

        $account->users()->detach($userId);

        $this->loadManagedUsers($account);
    }

    protected function refreshAccounts(SiteKitAccountManager $accountManager): void
    {
        $this->accounts = $accountManager->allForUser()->all();
    }

    protected function authorizeAccess(): void
    {
        abort_unless(Filament::auth()->check(), 403);
        abort_unless(Gate::allows((string) config('filament-sitekit.authorization.gate', 'manageSiteKit')), 403);

        $current = app(SiteKitAccountManager::class)->current();

        if ($current && ! Gate::allows('update', $current) && ! Gate::allows('manageUsers', $current)) {
            abort(403);
        }
    }

    protected function findAccessibleAccount(int $accountId): ?SiteKitAccount
    {
        $userId = (int) Filament::auth()->id();

        return SiteKitAccount::query()
            ->where('id', $accountId)
            ->where(function (Builder $query) use ($userId): void {
                $query->where('user_id', $userId)
                    ->orWhereHas('members', function (Builder $membership) use ($userId): void {
                        $membership->where('user_id', $userId);
                    });
            })
            ->first();
    }

    protected function loadManagedUsers(SiteKitAccount $account): void
    {
        $owner = $account->user;

        $users = collect();

        if ($owner && isset($owner->id)) {
            $users->push([
                'id' => (int) $owner->id,
                'name' => (string) ($owner->name ?? ('User #' . $owner->id)),
                'email' => $owner->email ?? null,
                'role' => 'owner',
            ]);
        }

        $shared = $account->users()
            ->wherePivot('role', '!=', 'owner')
            ->get()
            ->map(fn (Model $user): array => [
                'id' => (int) $user->id,
                'name' => (string) ($user->name ?? ('User #' . $user->id)),
                'email' => $user->email ?? null,
                'role' => (string) $user->pivot->role,
            ]);

        $this->managedUsers = $users
            ->merge($shared)
            ->values()
            ->all();
    }
}
