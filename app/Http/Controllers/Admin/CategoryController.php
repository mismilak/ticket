<?php

namespace App\Http\Controllers\Admin;

use App\Models\Category;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class CategoryController extends ResourceController
{
    protected string $model = Category::class;
    protected string $route = 'admin.categories';
    protected string $title = 'دسته‌بندی رویدادها';
    protected string $singular = 'دسته‌بندی';
    protected string $orderBy = 'sort';

    protected function fields(): array
    {
        return [
            'name' => ['label' => 'نام', 'rules' => 'required|max:60'],
            'slug' => ['label' => 'نشانی (انگلیسی، خالی = خودکار)', 'rules' => 'nullable|max:60', 'dir' => 'ltr'],
            'icon' => ['label' => 'آیکن (نام آیکن bootstrap-icons مثل mic, film, music-note-beamed)', 'rules' => 'nullable|max:40', 'dir' => 'ltr'],
            'sort' => ['label' => 'ترتیب', 'type' => 'number', 'rules' => 'nullable|integer'],
            'is_active' => ['label' => 'فعال', 'type' => 'checkbox'],
        ];
    }

    protected function columns(): array { return ['name' => 'نام', 'slug' => 'نشانی']; }

    protected function beforeSave(array &$data, Model $item): void
    {
        $data['slug'] = $data['slug'] ?: (slugify($data['name']) ?: 'cat-'.Str::random(5));
        $data['sort'] = $data['sort'] ?: 0;
    }
}
