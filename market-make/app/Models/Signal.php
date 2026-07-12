<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Signal extends Model
{
    use HasFactory;

    protected $fillable = [
        'symbol',
        'date',
        'close',
        'dc_upper',
        'dc_lower',
        'atr',
        'signal',
        'position',
        'entry_date',
        'entry_price',
        'stop_loss',
        'stop_hit',
        'alerted_position',
        'stop_alerted_for',
    ];

    protected $casts = [
        'date' => 'date',
        'entry_date' => 'date',
        'stop_alerted_for' => 'date',
        'close' => 'float',
        'dc_upper' => 'float',
        'dc_lower' => 'float',
        'atr' => 'float',
        'entry_price' => 'float',
        'stop_loss' => 'float',
        'stop_hit' => 'boolean',
    ];
}
