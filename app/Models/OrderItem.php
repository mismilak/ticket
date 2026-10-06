<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    protected $guarded = [];
    protected $casts = ['active' => 'boolean', 'checked_in_at' => 'datetime'];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function ticketType()
    {
        return $this->belongsTo(TicketType::class);
    }

    public function seat()
    {
        return $this->belongsTo(Seat::class);
    }

    public function seatLabel(): ?string
    {
        if (! $this->seat) {
            return null;
        }
        return trim(($this->seat->row_label ? 'ردیف '.$this->seat->row_label.' - ' : '').'صندلی '.$this->seat->label);
    }
}
