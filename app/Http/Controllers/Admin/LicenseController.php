<?php

namespace App\Http\Controllers\Admin;

use App\Models\License;
use Illuminate\Database\Eloquent\Model;

class LicenseController extends ResourceController
{
    protected string $model = License::class;
    protected string $route = 'admin.licenses';
    protected string $title = 'مجوزها و نمادهای فوتر (اینماد، ساماندهی، ...)';
    protected string $singular = 'مجوز';
    protected string $orderBy = 'sort';

    protected function fields(): array
    {
        return [
            'title' => ['label' => 'عنوان (مثلاً نماد اعتماد الکترونیکی)', 'rules' => 'required|max:120'],
            'image' => ['label' => 'تصویر نماد', 'type' => 'image', 'rules' => 'nullable|image|max:2048'],
            'link' => ['label' => 'لینک نماد', 'rules' => 'nullable|max:500', 'dir' => 'ltr'],
            'embed_code' => ['label' => 'یا کد HTML نماد (اگر وارد شود به‌جای تصویر نمایش داده می‌شود؛ مناسب کد اینماد)', 'type' => 'textarea', 'rows' => 5, 'dir' => 'ltr'],
            'sort' => ['label' => 'ترتیب', 'type' => 'number', 'rules' => 'nullable|integer'],
            'is_active' => ['label' => 'فعال', 'type' => 'checkbox'],
        ];
    }

    protected function columns(): array { return ['title' => 'عنوان', 'link' => 'لینک']; }

    protected function beforeSave(array &$data, Model $item): void
    {
        $data['sort'] = $data['sort'] ?: 0;
        if (array_key_exists('image', $data) && $data['image'] === null && ! request()->boolean('remove_image')) {
            unset($data['image']);
        }
    }
}
