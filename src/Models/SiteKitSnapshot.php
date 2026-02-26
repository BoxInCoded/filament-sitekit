<?php

declare(strict_types=1);

namespace BoxinCode\FilamentSiteKit\Models;

use BoxinCode\FilamentSiteKit\Models\SiteKitAccount;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiteKitSnapshot extends Model
{
    use HasFactory;

    protected $table = 'sitekit_snapshots';

    protected $fillable = [
        'account_id',
        'connector',
        'period',
        'data',
        'fetched_at',
        'fetched_on',
    ];

    protected $casts = [
        'data' => 'array',
        'fetched_at' => 'datetime',
        'fetched_on' => 'date',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(SiteKitAccount::class, 'account_id');
    }
}
