<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Event;
use App\Models\Venue;
use Illuminate\Database\Seeder;

/** داده نمونه: php artisan db:seed --class=DemoSeeder */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $venue = Venue::firstOrCreate(['name' => 'تالار وحدت'], ['city' => 'تهران', 'address' => 'میدان امام حسین، خیابان ایرانشهر']);
        $hall = $venue->halls()->firstOrCreate(['name' => 'سالن اصلی']);
        if ($hall->levels()->exists()) {
            return;
        }
        $vip = $hall->categories()->create(['name' => 'VIP', 'color' => '#e11d48']);
        $gold = $hall->categories()->create(['name' => 'طلایی', 'color' => '#f59e0b']);
        $std = $hall->categories()->create(['name' => 'معمولی', 'color' => '#3b82f6']);
        $balc = $hall->categories()->create(['name' => 'بالکن', 'color' => '#10b981']);

        $ground = $hall->levels()->create(['name' => 'همکف', 'sort' => 0, 'width' => 1000, 'height' => 700,
            'shapes' => [['type' => 'stage', 'x' => 300, 'y' => 30, 'w' => 400, 'h' => 60, 'text' => 'صحنه']]]);
        $up = $hall->levels()->create(['name' => 'بالکن', 'sort' => 1, 'width' => 1000, 'height' => 400,
            'shapes' => [['type' => 'text', 'x' => 500, 'y' => 30, 'text' => 'طبقه بالا (بالکن)', 'size' => 18]]]);

        $rows = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'];
        foreach ($rows as $r => $label) {
            $c = $r < 2 ? $vip : ($r < 5 ? $gold : $std);
            for ($i = 0; $i < 16; $i++) {
                $ground->seats()->create(['seat_category_id' => $c->id, 'row_label' => $label, 'label' => (string) (16 - $i), 'x' => 190 + $i * 38, 'y' => 140 + $r * 40]);
            }
        }
        foreach (['A', 'B', 'C'] as $r => $label) {
            for ($i = 0; $i < 20; $i++) {
                $up->seats()->create(['seat_category_id' => $balc->id, 'row_label' => $label, 'label' => (string) (20 - $i), 'x' => 130 + $i * 38, 'y' => 100 + $r * 40]);
            }
        }

        $event = Event::create([
            'category_id' => Category::where('slug', 'concert')->value('id'), 'hall_id' => $hall->id,
            'title' => 'کنسرت نمونه (طراحی صندلی)', 'slug' => 'demo-concert', 'subtitle' => 'اجرای زنده',
            'description' => "این یک رویداد نمونه است.\nبرای ویرایش به پنل مدیریت بروید.",
            'starts_at' => now()->addDays(20)->setTime(21, 0), 'status' => 'published', 'is_featured' => true,
        ]);
        foreach ([now()->addDays(20)->setTime(21, 0), now()->addDays(21)->setTime(18, 0), now()->addDays(21)->setTime(21, 30)] as $d) {
            $event->sessions()->create(['starts_at' => $d]);
        }
        $event->syncDatesFromSessions();
        foreach ([[$vip, 1500000], [$gold, 900000], [$std, 500000], [$balc, 350000]] as [$c, $p]) {
            $event->ticketTypes()->create(['seat_category_id' => $c->id, 'name' => $c->name, 'price' => $p]);
        }

        $conf = Event::create([
            'category_id' => Category::where('slug', 'conference')->value('id'), 'title' => 'همایش نمونه (بلیط تعدادی)', 'slug' => 'demo-conference',
            'venue_name' => 'برج میلاد', 'description' => 'رویداد بدون پلان صندلی؛ فروش تعدادی.',
            'starts_at' => now()->addDays(10)->setTime(9, 0), 'status' => 'published',
        ]);
        $conf->sessions()->create(['starts_at' => $conf->starts_at]);
        $conf->ticketTypes()->createMany([
            ['name' => 'بلیط عادی', 'price' => 200000, 'capacity' => 100],
            ['name' => 'دانشجویی', 'price' => 100000, 'capacity' => 30],
        ]);
    }
}
