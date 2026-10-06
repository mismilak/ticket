<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $guarded = [];
    protected $casts = ['expires_at' => 'datetime', 'paid_at' => 'datetime'];

    public const STATUS = [
        'pending' => 'در انتظار پرداخت',
        'paid' => 'پرداخت‌شده',
        'failed' => 'ناموفق',
        'expired' => 'منقضی‌شده',
        'canceled' => 'لغوشده',
    ];

    public function getRouteKeyName(): string
    {
        return 'code';
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function session()
    {
        return $this->belongsTo(EventSession::class, 'event_session_id');
    }

    public function startsAt()
    {
        return $this->session?->starts_at ?? $this->event->starts_at;
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function statusLabel(): string
    {
        return self::STATUS[$this->status] ?? $this->status;
    }

    public function isPending(): bool
    {
        return $this->status === 'pending' && $this->expires_at && $this->expires_at->isFuture();
    }
}
