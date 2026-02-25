<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MasterRod extends Model
{
    protected $fillable = [
        'name', 'icon', 'image_url',
        'description', 'hint', 'from',
        'strength', 'line_distance',
        'luck', 'lure_speed', 'resilience', 'control',
        'level_requirement', 'disturbance',
        'mutation_pool', 'preferred_disturbance',
    ];

    protected $casts = [
        'mutation_pool'          => 'array',
        'preferred_disturbance'  => 'array',
    ];
}

