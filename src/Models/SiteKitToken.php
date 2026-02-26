<?php

declare(strict_types=1);

namespace BoxinCode\FilamentSiteKit\Models;

use BoxinCode\FilamentSiteKit\Models\SiteKitAccount;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;

class SiteKitToken extends Model
{
    use HasFactory;

    protected $table = 'sitekit_tokens';

    protected $fillable = [
        'account_id',
        'access_token',
        'refresh_token',
        'expires_at',
        'scopes',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'scopes' => 'array',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(SiteKitAccount::class, 'account_id');
    }

    public function isExpired(): bool
    {
        if (! $this->expires_at instanceof Carbon) {
            return false;
        }

        return $this->expires_at->isPast();
    }

    public function setAccessTokenAttribute(string $value): void
    {
        $this->attributes['access_token'] = Crypt::encryptString($value);
    }

    public function getAccessTokenAttribute(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return Crypt::decryptString($value);
    }

    public function setRefreshTokenAttribute(?string $value): void
    {
        $this->attributes['refresh_token'] = $value ? Crypt::encryptString($value) : null;
    }

    public function getRefreshTokenAttribute(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return Crypt::decryptString($value);
    }
}
