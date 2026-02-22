<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrackedPlayer extends Model
{
    protected $fillable = ['user_id', 'player_name'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
