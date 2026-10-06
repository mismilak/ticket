<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Services\OrderService;
use Illuminate\Http\Request;

class EventController extends Controller
{
    private function visible(Event $event): Event
    {
        abort_unless($event->status === 'published' || auth()->user()?->isAdmin(), 404);
        return $event->load(['category', 'hall.venue', 'ticketTypes']);
    }

    public function show(Event $event)
    {
        $this->visible($event);
        $sold = OrderService::soldCounts($event);
        $general = $event->ticketTypes->where('is_active', true)->whereNull('seat_category_id')->values()
            ->map(function ($t) use ($sold) {
                $t->remaining = $t->capacity === null ? null : max(0, $t->capacity - ($sold[$t->id] ?? 0));
                return $t;
            });
        $seatTypes = $event->ticketTypes->where('is_active', true)->whereNotNull('seat_category_id')->values();
        return view('event', [
            'event' => $event, 'general' => $general, 'seatTypes' => $seatTypes,
            'seated' => $event->isSeated(),
            'max' => (int) setting('max_per_order', 10),
        ]);
    }

    /** JSON نقشه سالن + وضعیت صندلی‌ها */
    public function seatmap(Event $event)
    {
        $this->visible($event);
        abort_unless($event->hall, 404);
        $layout = $event->hall->layout();
        $prices = $event->ticketTypes->where('is_active', true)->whereNotNull('seat_category_id')
            ->mapWithKeys(fn ($t) => [$t->seat_category_id => ['price' => $t->price, 'name' => $t->name]]);
        $layout['categories'] = collect($layout['categories'])->map(fn ($c) => $c + [
            'price' => $prices[$c['id']]['price'] ?? null,
            'ticket' => $prices[$c['id']]['name'] ?? $c['name'],
        ])->all();
        $layout['status'] = (object) OrderService::seatStatuses($event);
        return response()->json($layout)->header('Cache-Control', 'no-store');
    }

    /** ذخیره انتخاب کاربر در سشن و رفتن به مرحله تایید */
    public function reserve(Request $request, Event $event)
    {
        $this->visible($event);
        $data = $request->validate([
            'seats' => 'nullable|array', 'seats.*' => 'integer',
            'general' => 'nullable|array', 'general.*' => 'nullable|integer|min:0|max:100',
        ]);
        $cart = ['event' => $event->id, 'seats' => $data['seats'] ?? [], 'general' => array_filter($data['general'] ?? [])];
        if (! count($cart['seats']) && ! array_sum($cart['general'])) {
            return back()->with('error', 'لطفاً حداقل یک بلیط انتخاب کنید.');
        }
        session(['cart' => $cart]);
        return redirect()->route('checkout');
    }
}
