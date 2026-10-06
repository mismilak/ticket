<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventSession extends Model
{
    protected $guarded = [];
    protected $casts = ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'sales_open' => 'boolean'];

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function isOnSale(): bool
    {
        return $this->sales_open && $this->event->status === 'published'
            && ($this->ends_at ?: $this->starts_at)->isFuture();
    }
}
