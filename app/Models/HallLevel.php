<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HallLevel extends Model
{
    protected $guarded = [];
    protected $casts = ['shapes' => 'array'];

    public function hall()
    {
        return $this->belongsTo(Hall::class);
    }

    public function seats()
    {
        return $this->hasMany(Seat::class);
    }
}
