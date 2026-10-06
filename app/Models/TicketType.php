<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TicketType extends Model
{
    protected $guarded = [];
    protected $casts = ['is_active' => 'boolean'];

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function seatCategory()
    {
        return $this->belongsTo(SeatCategory::class);
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }
}
