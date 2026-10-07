<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Table extends Model
{
    public $timestamps = false;

    protected $fillable = ['name'];

    public function markerPlans(): HasMany
    {
        return $this->hasMany(MarkerPlan::class, 'table_no_id');
    }
}
