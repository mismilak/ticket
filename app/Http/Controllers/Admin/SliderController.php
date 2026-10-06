<?php

namespace App\Http\Controllers\Admin;

use App\Models\Slider;
use Illuminate\Database\Eloquent\Model;

class SliderController extends ResourceController
{
    protected string $model = Slider::class;
    protected string $route = 'admin.sliders';
    protected string $title = 'اسلایدر صفحه اصلی';
    protected string $singular = 'اسلاید';
    protected string $orderBy = 'sort';

    protected function fields(): array
    {
        return [
            'title' => ['label' => 'عنوان', 'rules' => 'nullable|max:120'],
            'subtitle' => ['label' => 'زیرعنوان', 'rules' => 'nullable|max:200'],
            'image' => ['label' => 'تصویر (پیشنهادی ۱۶۰۰×۶۰۰)', 'type' => 'image', 'rules' => 'nullable|image|max:4096'],
            'link' => ['label' => 'لینک', 'rules' => 'nullable|max:255', 'dir' => 'ltr'],
            'sort' => ['label' => 'ترتیب', 'type' => 'number', 'rules' => 'nullable|integer'],
            'is_active' => ['label' => 'فعال', 'type' => 'checkbox'],
        ];
    }

    protected function columns(): array { return ['title' => 'عنوان', 'link' => 'لینک']; }

    protected function beforeSave(array &$data, Model $item): void
    {
        $data['sort'] = $data['sort'] ?: 0;
        if (! $item->exists && empty($data['image'])) {
            abort(422, 'تصویر اسلاید الزامی است.');
        }
        if (array_key_exists('image', $data) && $data['image'] === null) {
            unset($data['image']);
        }
    }
}
