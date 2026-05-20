<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Player extends Model
{
    protected $fillable = [
        'player_name',
        'coins',
        'inventory_rows_count',
        'inventory_stack_count',
        'inventory_total_value',
        'storage_rows_count',
        'storage_stack_count',
        'storage_total_value',
    ];

    public function rods()
    {
        return $this->hasMany(PlayerRod::class);
    }

    public function inventories()
    {
        return $this->hasMany(PlayerInventory::class);
    }

    public function storages()
    {
        return $this->hasMany(PlayerStorage::class);
    }
}
