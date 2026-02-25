<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Fish extends Model
{
    protected $table = 'fishes';

    protected $fillable = [
        'name',
        'price_per_kg',
        'max_weight',
        'rarity',
        'icon',
        'from',
    ];
}
