<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PoSheet extends Model
{
    /** @use HasFactory<\Database\Factories\PoSheetFactory> */
    use HasFactory;

    protected $fillable = [
        'file_no',
        'skcl_no',
        'order_no',
        'style_no',
        'country',
        'item_name',
        'color_name',
        'size',
        'quantity',
    ];
}
