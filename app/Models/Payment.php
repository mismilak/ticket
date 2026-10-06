<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $guarded = [];
    protected $casts = ['raw' => 'array'];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
