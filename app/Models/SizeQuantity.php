<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SizeQuantity extends Model
{
    protected $guarded = [];


    public function table(): BelongsTo
    {
        return $this->belongsTo(Table::class, 'table_no_id');
    }
}
