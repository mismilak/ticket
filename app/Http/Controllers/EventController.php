<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventSession;
use App\Services\OrderService;
use Illuminate\Http\Request;

class EventController extends Controller
{
    private function visible(Event $event): Event
    {
        abort_unless($event->status === 'published' || auth()->user()?->isAdmin(), 404);
        return $event->load(['category', 'hall.venue', 'ticketTypes', 'sessions']);
    }

    /** سانس انتخاب‌شده: ?session=ID، وگرنه نزدیک‌ترین سانس قابل فروش */
    private function session(Request $request, Event $event): EventSession
    {
        $all = $event->sessions;
        abort_if($all->isEmpty(), 404);
        $picked = $request->filled('session') ? $all->firstWhere('id', (int) $request->session) : null;
        return $picked ?: ($all->first(fn ($s) => $s->isOnSale()) ?: $all->first(fn ($s) => $s->starts_at->isFuture()) ?: $all->last());
    }

    public function show(Request $request, Event $event)
    {
        $this->visible($event);
        $session = $this->session($request, $event);
        $sold = OrderService::soldCounts($session);
        $general = $event->ticketTypes->where('is_active', true)->whereNull('seat_category_id')->values()
            ->map(function ($t) use ($sold) {
                $t->remaining = $t->capacity === null ? null : max(0, $t->capacity - ($sold[$t->id] ?? 0));
                return $t;
            });
        $seatTypes = $event->ticketTypes->where('is_active', true)->whereNotNull('seat_category_id')->values();
        return view('event', [
            'event' => $event, 'session' => $session, 'sessions' => $event->sessions, 'general' => $general, 'seatTypes' => $seatTypes,
            'seated' => $event->isSeated(),
            'onSale' => $event->isOnSale() && $session->isOnSale(),
            'max' => (int) setting('max_per_order', 10),
        ]);
    }

    /** JSON نقشه سالن + وضعیت صندلی‌ها */
    public function seatmap(Request $request, Event $event)
    {
        $this->visible($event);
        $session = $this->session($request, $event);
        abort_unless($event->hall, 404);
        $layout = $event->hall->layout();
        $prices = $event->ticketTypes->where('is_active', true)->whereNotNull('seat_category_id')
            ->mapWithKeys(fn ($t) => [$t->seat_category_id => ['price' => $t->price, 'name' => $t->name]]);
        $layout['categories'] = collect($layout['categories'])->map(fn ($c) => $c + [
            'price' => $prices[$c['id']]['price'] ?? null,
            'ticket' => $prices[$c['id']]['name'] ?? $c['name'],
        ])->all();
        $layout['status'] = (object) OrderService::seatStatuses($session);
        return response()->json($layout)->header('Cache-Control', 'no-store');
    }

    /** ذخیره انتخاب کاربر در سشن و رفتن به مرحله تایید */
    public function reserve(Request $request, Event $event)
    {
        $this->visible($event);
        $data = $request->validate([
            'seats' => 'nullable|array', 'seats.*' => 'integer',
            'general' => 'nullable|array', 'general.*' => 'nullable|integer|min:0|max:100',
            'session' => 'required|integer',
        ]);
        $session = $event->sessions->firstWhere('id', (int) $data['session']);
        abort_unless($session, 404);
        $cart = ['event' => $event->id, 'session' => $session->id, 'seats' => $data['seats'] ?? [], 'general' => array_filter($data['general'] ?? [])];
        if (! count($cart['seats']) && ! array_sum($cart['general'])) {
            return back()->with('error', 'لطفاً حداقل یک بلیط انتخاب کنید.');
        }
        session(['cart' => $cart]);
        return redirect()->route('checkout');
    }
}
