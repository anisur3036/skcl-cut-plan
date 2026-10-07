<?php
// app/Models/MarkerPlanDetail.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarkerPlanDetail extends Model
{
    protected $guarded = [];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(MarkerPlan::class, 'marker_plan_id');
    }
}
