<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlayerStorage extends Model
{
    protected $fillable = [
        'player_id',
        'sparkling',
        'name',
        'weight',
        'shiny',
        'stack',
        'mutation',
        'favourited',
    ];
}
