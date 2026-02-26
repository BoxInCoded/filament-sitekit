<?php

declare(strict_types=1);

namespace BoxinCode\FilamentSiteKit\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SiteKitAccount extends Model
{
    use HasFactory;

    protected $table = 'sitekit_accounts';

    protected $fillable = [
        'user_id',
        'workspace_id',
        'provider',
        'email',
        'display_name',
        'name',
    ];

    protected $casts = [
        'workspace_id' => 'integer',
    ];

    public function user(): BelongsTo
    {
        $authModel = config('auth.providers.users.model');

        return $this->belongsTo($authModel);
    }

    public function users(): BelongsToMany
    {
        $authModel = config('auth.providers.users.model');

        return $this->belongsToMany($authModel, 'sitekit_account_users', 'account_id', 'user_id')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function members(): HasMany
    {
        return $this->hasMany(SiteKitAccountUser::class, 'account_id');
    }

    public function token(): HasMany
    {
        return $this->hasMany(SiteKitToken::class, 'account_id');
    }

    public function settings(): HasMany
    {
        return $this->hasMany(SiteKitSetting::class, 'account_id');
    }

    public function snapshots(): HasMany
    {
        return $this->hasMany(SiteKitSnapshot::class, 'account_id');
    }

    public function label(): string
    {
        return (string) ($this->name ?: $this->display_name ?: $this->email ?: 'Account #' . $this->id);
    }

    public function roleForUserId(int $userId): ?string
    {
        if ($userId <= 0) {
            return null;
        }

        if ((int) $this->user_id === $userId) {
            return 'owner';
        }

        $pivot = $this->users()
            ->wherePivot('user_id', $userId)
            ->first();

        return $pivot?->pivot?->role;
    }

    public function hasRole(int $userId, array $roles): bool
    {
        return in_array($this->roleForUserId($userId), $roles, true);
    }
}
