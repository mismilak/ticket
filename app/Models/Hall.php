<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Hall extends Model
{
    protected $guarded = [];

    public function venue()
    {
        return $this->belongsTo(Venue::class);
    }

    public function levels()
    {
        return $this->hasMany(HallLevel::class)->orderBy('sort')->orderBy('id');
    }

    public function categories()
    {
        return $this->hasMany(SeatCategory::class);
    }

    public function fullName(): string
    {
        return $this->venue->name.' - '.$this->name;
    }

    /** ساختار کامل چیدمان برای نقشه (دیزاینر و خرید) */
    public function layout(): array
    {
        $levels = $this->levels()->with('seats')->get()->map(fn ($l) => [
            'id' => $l->id,
            'name' => $l->name,
            'width' => $l->width,
            'height' => $l->height,
            'shapes' => $l->shapes ?: [],
            'seats' => $l->seats->map(fn ($s) => [
                'id' => $s->id, 'x' => $s->x, 'y' => $s->y,
                'label' => $s->label, 'row' => $s->row_label, 'cat' => $s->seat_category_id,
            ])->values(),
        ])->values();

        return [
            'levels' => $levels,
            'categories' => $this->categories->map(fn ($c) => ['id' => $c->id, 'name' => $c->name, 'color' => $c->color])->values(),
        ];
    }
}
