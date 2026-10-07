<?php
// app/Models/MarkerPlan.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MarkerPlan extends Model
{
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_APPROVED = 'approved';
    public const LOCKED_STATUSES = [self::STATUS_APPROVED];

    // value => label (এখান থেকেই ফর্ম, তালিকা ও PDF-এর স্ট্যাটাস আসে)
    public const STATUSES = [
        'draft'     => 'Draft',
        'approved'  => 'Approved',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
    ];

    protected $guarded = [];

    public function cuttingTable(): BelongsTo
    {
        return $this->belongsTo(Table::class, 'table_no_id');
    }

    public function details(): HasMany
    {
        return $this->hasMany(MarkerPlanDetail::class);
    }

    public function isLocked(): bool
    {
        return in_array($this->status, self::LOCKED_STATUSES, true);
    }
}
