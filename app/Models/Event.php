<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Event extends Model
{
    protected $guarded = [];
    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'sales_open' => 'boolean',
        'is_featured' => 'boolean',
    ];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function hall()
    {
        return $this->belongsTo(Hall::class);
    }

    public function ticketTypes()
    {
        return $this->hasMany(TicketType::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function scopePublished($q)
    {
        return $q->where('status', 'published');
    }

    public function scopeUpcoming($q)
    {
        return $q->where(fn ($w) => $w->where('starts_at', '>=', now()->startOfDay()));
    }

    public function isSeated(): bool
    {
        return $this->hall_id && $this->ticketTypes->whereNotNull('seat_category_id')->where('is_active', true)->isNotEmpty();
    }

    public function isOnSale(): bool
    {
        return $this->status === 'published' && $this->sales_open
            && ($this->ends_at ?: $this->starts_at)->isFuture();
    }

    public function posterUrl(): string
    {
        return $this->poster ? upload_url($this->poster) : asset('img/placeholder.svg');
    }

    public function venueLabel(): string
    {
        if ($this->hall) {
            return $this->hall->venue->name.' — '.$this->hall->name;
        }
        return (string) $this->venue_name;
    }

    public function minPrice(): ?int
    {
        return $this->ticketTypes->where('is_active', true)->min('price');
    }
}
