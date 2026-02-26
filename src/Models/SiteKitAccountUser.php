<?php

declare(strict_types=1);

namespace BoxinCode\FilamentSiteKit\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiteKitAccountUser extends Model
{
    protected $table = 'sitekit_account_users';

    protected $fillable = [
        'account_id',
        'user_id',
        'role',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(SiteKitAccount::class, 'account_id');
    }
}
