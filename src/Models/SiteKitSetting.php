<?php

declare(strict_types=1);

namespace BoxinCode\FilamentSiteKit\Models;

use BoxinCode\FilamentSiteKit\Models\SiteKitAccount;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiteKitSetting extends Model
{
    use HasFactory;

    protected $table = 'sitekit_settings';

    protected $fillable = [
        'account_id',
        'key',
        'value',
    ];

    protected $casts = [
        'value' => 'array',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(SiteKitAccount::class, 'account_id');
    }
}
