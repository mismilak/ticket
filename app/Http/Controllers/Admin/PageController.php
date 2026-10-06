<?php

namespace App\Http\Controllers\Admin;

use App\Models\Page;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class PageController extends ResourceController
{
    protected string $model = Page::class;
    protected string $route = 'admin.pages';
    protected string $title = 'صفحات (درباره ما، قوانین، ...)';
    protected string $singular = 'صفحه';
    protected string $orderBy = 'sort';

    protected function fields(): array
    {
        return [
            'title' => ['label' => 'عنوان', 'rules' => 'required|max:120'],
            'slug' => ['label' => 'نشانی (خالی = خودکار)', 'rules' => 'nullable|max:80', 'dir' => 'ltr'],
            'body' => ['label' => 'محتوا (HTML مجاز است)', 'type' => 'textarea', 'rows' => 14],
            'show_in_footer' => ['label' => 'نمایش لینک در فوتر', 'type' => 'checkbox'],
            'sort' => ['label' => 'ترتیب', 'type' => 'number', 'rules' => 'nullable|integer'],
            'is_active' => ['label' => 'فعال', 'type' => 'checkbox'],
        ];
    }

    protected function columns(): array { return ['title' => 'عنوان', 'slug' => 'نشانی']; }

    protected function beforeSave(array &$data, Model $item): void
    {
        $data['slug'] = $data['slug'] ?: (slugify($data['title']) ?: 'page-'.Str::random(5));
        $data['sort'] = $data['sort'] ?: 0;
    }
}
