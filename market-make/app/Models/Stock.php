<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Stock extends Model
{
    use HasFactory;

    protected $fillable = [
        'symbol',
        'date',
        'open',
        'high',
        'low',
        'close',
        'volume',
    ];

    protected $casts = [
        'date' => 'date',
        'open' => 'float',
        'high' => 'float',
        'low' => 'float',
        'close' => 'float',
        'volume' => 'integer',
    ];
}
