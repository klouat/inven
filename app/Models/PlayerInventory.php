<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlayerInventory extends Model
{
    protected $fillable = [
        'player_id', 'sync_key', 'sparkling', 'name', 'weight', 'shiny', 'stack', 'mutation', 'favourited'
    ];
}
