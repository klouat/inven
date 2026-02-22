<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Player extends Model
{
    protected $fillable = ['player_name', 'coins'];

    public function rods()
    {
        return $this->hasMany(PlayerRod::class);
    }

    public function inventories()
    {
        return $this->hasMany(PlayerInventory::class);
    }
}
