<?php

namespace App\Http\Controllers\Admin;

use App\Models\Venue;

class VenueController extends ResourceController
{
    protected string $model = Venue::class;
    protected string $route = 'admin.venues';
    protected string $title = 'سالن‌ها و مکان‌ها';
    protected string $singular = 'مکان';

    protected function fields(): array
    {
        return [
            'name' => ['label' => 'نام مجموعه / سالن', 'rules' => 'required|max:120'],
            'city' => ['label' => 'شهر', 'rules' => 'nullable|max:60'],
            'address' => ['label' => 'آدرس', 'rules' => 'nullable|max:255'],
            'map_url' => ['label' => 'لینک نقشه (نشان/بلد/گوگل)', 'rules' => 'nullable|max:500', 'dir' => 'ltr'],
        ];
    }

    protected function columns(): array { return ['name' => 'نام', 'city' => 'شهر']; }

    protected function extraActions(): array
    {
        return [['label' => 'مدیریت پلان‌ها', 'route' => 'admin.halls.index', 'icon' => 'grid-3x3-gap', 'param' => 'venue']];
    }
}
