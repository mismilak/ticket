<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\License;
use App\Models\Page;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $mobile = env('ADMIN_MOBILE', '09120000000');
        User::updateOrCreate(['mobile' => $mobile], [
            'name' => 'مدیر سایت', 'role' => 'admin', 'password' => env('ADMIN_PASSWORD', 'admin1234'),
        ]);

        foreach ([['کنسرت', 'concert', 'mic'], ['تئاتر', 'theater', 'stars'], ['سینما', 'cinema', 'film'], ['همایش', 'conference', 'people'], ['کودک', 'kids', 'balloon']] as $i => [$n, $s, $ic]) {
            Category::firstOrCreate(['slug' => $s], ['name' => $n, 'icon' => $ic, 'sort' => $i]);
        }
        Page::firstOrCreate(['slug' => 'about'], ['title' => 'درباره ما', 'body' => '<p>متن درباره ما را از پنل مدیریت ویرایش کنید.</p>', 'sort' => 1]);
        Page::firstOrCreate(['slug' => 'terms'], ['title' => 'قوانین و مقررات', 'body' => '<p>قوانین و مقررات خرید بلیط را اینجا بنویسید.</p>', 'sort' => 2]);
        Page::firstOrCreate(['slug' => 'faq'], ['title' => 'سوالات متداول', 'body' => '<p>سوالات متداول را اینجا بنویسید.</p>', 'sort' => 3]);
        if (! License::count()) {
            License::create(['title' => 'نماد اعتماد الکترونیکی (اینماد)', 'embed_code' => '', 'sort' => 1, 'is_active' => false]);
            License::create(['title' => 'نماد ساماندهی', 'embed_code' => '', 'sort' => 2, 'is_active' => false]);
        }
    }
}
