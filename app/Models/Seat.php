<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Seat extends Model
{
    protected $guarded = [];

    public function level()
    {
        return $this->belongsTo(HallLevel::class, 'hall_level_id');
    }

    public function category()
    {
        return $this->belongsTo(SeatCategory::class, 'seat_category_id');
    }
}
