<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Hall;
use App\Models\HallLevel;
use App\Models\OrderItem;
use App\Models\Seat;
use App\Models\SeatCategory;
use App\Models\Venue;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HallController extends Controller
{
    public function index(Venue $venue)
    {
        return view('admin.halls.index', ['venue' => $venue, 'halls' => $venue->halls()->withCount('levels')->get()]);
    }

    public function store(Request $request, Venue $venue)
    {
        $data = $request->validate(['name' => 'required|max:120']);
        $hall = $venue->halls()->create($data);
        $hall->levels()->create(['name' => 'طبقه همکف', 'sort' => 0]);
        $hall->categories()->create(['name' => 'عمومی', 'color' => '#3b82f6']);
        return redirect()->route('admin.halls.designer', $hall);
    }

    public function destroy(Hall $hall)
    {
        if (\App\Models\Event::where('hall_id', $hall->id)->exists()) {
            return back()->with('error', 'برای این پلان رویداد ثبت شده و قابل حذف نیست.');
        }
        $hall->delete();
        return back()->with('status', 'پلان حذف شد.');
    }

    public function designer(Hall $hall)
    {
        $hall->load('venue');
        return view('admin.halls.designer', ['hall' => $hall, 'layout' => $hall->layout()]);
    }

    public function save(Request $request, Hall $hall)
    {
        $payload = $request->validate([
            'name' => 'nullable|string|max:120',
            'categories' => 'required|array|min:1',
            'categories.*.name' => 'required|string|max:60',
            'categories.*.color' => 'required|string|max:9',
            'levels' => 'required|array|min:1',
            'levels.*.name' => 'required|string|max:60',
            'levels.*.width' => 'required|integer|min:200|max:6000',
            'levels.*.height' => 'required|integer|min:200|max:6000',
            'levels.*.shapes' => 'nullable|array',
            'levels.*.seats' => 'nullable|array',
            'levels.*.seats.*.x' => 'required|numeric',
            'levels.*.seats.*.y' => 'required|numeric',
            'levels.*.seats.*.label' => 'required|string|max:30',
            'levels.*.seats.*.row' => 'nullable|string|max:20',
        ]);

        try {
            DB::transaction(function () use ($payload, $hall) {
                if (! empty($payload['name'])) {
                    $hall->update(['name' => $payload['name']]);
                }
                // --- دسته‌بندی صندلی‌ها ---
                $catMap = [];
                $keepCats = [];
                foreach ($payload['categories'] as $c) {
                    $cat = (! empty($c['id']) && is_numeric($c['id'])) ? SeatCategory::where('hall_id', $hall->id)->find($c['id']) : null;
                    $cat = $cat ?: new SeatCategory(['hall_id' => $hall->id]);
                    $cat->fill(['name' => $c['name'], 'color' => $c['color']])->save();
                    $catMap[(string) ($c['id'] ?? $cat->id)] = $cat->id;
                    $keepCats[] = $cat->id;
                }
                SeatCategory::where('hall_id', $hall->id)->whereNotIn('id', $keepCats)->delete();

                // --- طبقات و صندلی‌ها ---
                $before = Seat::whereIn('hall_level_id', HallLevel::where('hall_id', $hall->id)->pluck('id'))->pluck('id');
                $keepLevels = [];
                $keepSeats = [];
                foreach ($payload['levels'] as $i => $l) {
                    $level = (! empty($l['id']) && is_numeric($l['id'])) ? HallLevel::where('hall_id', $hall->id)->find($l['id']) : null;
                    $level = $level ?: new HallLevel(['hall_id' => $hall->id]);
                    $level->fill([
                        'name' => $l['name'], 'sort' => $i, 'width' => $l['width'], 'height' => $l['height'],
                        'shapes' => $l['shapes'] ?? [],
                    ])->save();
                    $keepLevels[] = $level->id;

                    $existing = [];
                    $new = [];
                    $now = now();
                    foreach ($l['seats'] ?? [] as $s) {
                        $row = [
                            'hall_level_id' => $level->id,
                            'seat_category_id' => $catMap[(string) ($s['cat'] ?? '')] ?? null,
                            'row_label' => $s['row'] ?? null,
                            'label' => $s['label'],
                            'x' => round($s['x'], 1), 'y' => round($s['y'], 1),
                            'updated_at' => $now,
                        ];
                        if (! empty($s['id']) && is_numeric($s['id'])) {
                            $existing[] = $row + ['id' => (int) $s['id'], 'created_at' => $now];
                            $keepSeats[] = (int) $s['id'];
                        } else {
                            $new[] = $row + ['created_at' => $now];
                        }
                    }
                    // فقط صندلی‌های متعلق به همین سالن قابل ویرایش هستند
                    if ($existing) {
                        $owned = Seat::whereIn('id', array_column($existing, 'id'))
                            ->whereIn('hall_level_id', HallLevel::where('hall_id', $hall->id)->pluck('id'))->pluck('id')->all();
                        $existing = array_values(array_filter($existing, fn ($r) => in_array($r['id'], $owned)));
                        foreach (array_chunk($existing, 300) as $chunk) {
                            Seat::upsert($chunk, ['id'], ['hall_level_id', 'seat_category_id', 'row_label', 'label', 'x', 'y', 'updated_at']);
                        }
                    }
                    foreach (array_chunk($new, 300) as $chunk) {
                        Seat::insert($chunk);
                    }
                }

                // --- حذف طبقات/صندلی‌های حذف‌شده (اگر فروخته نشده باشند) ---
                $removedSeatIds = $before->diff($keepSeats);
                if ($removedSeatIds->isNotEmpty() && OrderItem::whereIn('seat_id', $removedSeatIds)->exists()) {
                    throw new \RuntimeException('برخی از صندلی‌های حذف‌شده قبلاً بلیط فروخته‌اند و قابل حذف نیستند.');
                }
                Seat::whereIn('id', $removedSeatIds)->delete();
                HallLevel::where('hall_id', $hall->id)->whereNotIn('id', $keepLevels)->delete();
            });
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['ok' => true, 'layout' => $hall->fresh()->layout()]);
    }
}
