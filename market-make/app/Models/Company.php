<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    use HasFactory;

    protected $fillable = [
        'symbol',
        'name',
        'sector',
        'industry',
        'revenue_growth',
        'eps_growth',
        'reliability_score',
        'reliability_max',
        'reliability_checks',
        'data_from',
        'data_to',
    ];

    protected $casts = [
        'data_from' => 'date',
        'data_to' => 'date',
        'revenue_growth' => 'float',
        'eps_growth' => 'float',
        'reliability_score' => 'integer',
        'reliability_max' => 'integer',
        'reliability_checks' => 'array',
    ];
}
