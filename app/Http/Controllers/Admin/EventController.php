<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Event;
use App\Models\EventSession;
use App\Models\Hall;
use App\Models\OrderItem;
use App\Services\OrderService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class EventController extends Controller
{
    public function index()
    {
        $events = Event::with('category')->withCount(['orders as paid_orders' => fn ($q) => $q->where('status', 'paid')])
            ->latest('starts_at')->paginate(25);
        return view('admin.events.index', compact('events'));
    }

    public function create()
    {
        return $this->form(new Event(['status' => 'draft', 'sales_open' => true]));
    }

    public function edit(Event $event)
    {
        return $this->form($event->load('ticketTypes', 'sessions'));
    }

    private function form(Event $event)
    {
        $halls = Hall::with(['venue', 'categories'])->get();
        return view('admin.events.form', [
            'event' => $event,
            'categories' => Category::orderBy('sort')->get(),
            'halls' => $halls,
            'hallCats' => $halls->mapWithKeys(fn ($h) => [$h->id => $h->categories->map(fn ($c) => ['id' => $c->id, 'name' => $c->name, 'color' => $c->color])->values()]),
            'seatPrices' => $event->exists ? $event->ticketTypes->whereNotNull('seat_category_id')->where('is_active', true)->pluck('price', 'seat_category_id') : collect(),
            'generalData' => $event->exists ? $event->ticketTypes->whereNull('seat_category_id')->where('is_active', true)->map(fn ($t) => $t->only('id', 'name', 'price', 'capacity'))->values() : [],
            'sessionRows' => $event->exists ? $event->sessions->map(fn ($s) => ['id' => $s->id, 'starts_at' => jdate($s->starts_at, 'input'), 'ends_at' => $s->ends_at ? jdate($s->ends_at, 'input') : '', 'sales_open' => $s->sales_open])->values() : [],
            'general' => $event->exists ? $event->ticketTypes->whereNull('seat_category_id')->where('is_active', true)->values() : collect(),
        ]);
    }

    public function store(Request $request)
    {
        $event = new Event();
        $this->save($request, $event);
        return redirect()->route('admin.events.edit', $event)->with('status', 'رویداد ذخیره شد.');
    }

    public function update(Request $request, Event $event)
    {
        $this->save($request, $event);
        return back()->with('status', 'تغییرات ذخیره شد.');
    }

    private function save(Request $request, Event $event): void
    {
        $data = $request->validate([
            'title' => 'required|max:200',
            'subtitle' => 'nullable|max:200',
            'description' => 'nullable',
            'category_id' => 'nullable|exists:categories,id',
            'hall_id' => 'nullable|exists:halls,id',
            'venue_name' => 'nullable|max:200',
            'venue_address' => 'nullable|max:255',
            'sessions' => 'required|array|min:1',
            'status' => 'required|in:draft,published',
            'poster' => 'nullable|image|max:4096',
            'slug' => 'nullable|max:150',
        ]);
        // سانس‌ها: هر ردیف یک تاریخ/ساعت شمسی
        $rows = [];
        foreach ($request->input('sessions', []) as $n => $r) {
            if (trim((string) ($r['starts_at'] ?? '')) === '') {
                continue;
            }
            $start = parse_jdate($r['starts_at']);
            if (! $start) {
                back()->withErrors(['sessions' => 'تاریخ سانس '.($n + 1).' نامعتبر است. نمونه: ۱۴۰۵/۰۷/۲۰ ۲۱:۳۰'])->withInput()->throwResponse();
            }
            $rows[] = ['id' => $r['id'] ?? null, 'starts_at' => $start, 'ends_at' => ! empty($r['ends_at']) ? parse_jdate($r['ends_at']) : null, 'sales_open' => ! empty($r['sales_open'])];
        }
        if (! $rows) {
            back()->withErrors(['sessions' => 'حداقل یک سانس لازم است.'])->withInput()->throwResponse();
        }
        $data['starts_at'] = $rows[0]['starts_at'];
        $data['ends_at'] = null;
        unset($data['sessions']);
        $data['sales_open'] = $request->boolean('sales_open');
        $data['is_featured'] = $request->boolean('is_featured');
        $base = slugify($data["slug"] ?: $data["title"]) ?: 'event';
        $slug = $base;
        $i = 1;
        while (Event::where('slug', $slug)->where('id', '!=', $event->id ?? 0)->exists()) {
            $slug = $base.'-'.(++$i);
        }
        $data['slug'] = $slug;
        unset($data['poster']);
        if ($request->hasFile('poster')) {
            $data['poster'] = ResourceController::storeImage($request->file('poster'));
        }
        $event->fill($data)->save();
        $this->syncSessions($event, $rows);
        $this->syncTicketTypes($request, $event);
    }

    private function syncSessions(Event $event, array $rows): void
    {
        $keep = [];
        foreach ($rows as $r) {
            $attrs = ['starts_at' => $r['starts_at'], 'ends_at' => $r['ends_at'], 'sales_open' => $r['sales_open']];
            $s = $r['id'] ? $event->sessions()->find($r['id']) : null;
            $s ? $s->update($attrs) : ($s = $event->sessions()->create($attrs));
            $keep[] = $s->id;
        }
        // سانسی که سفارش دارد حذف نمی‌شود، فقط فروشش بسته می‌شود
        foreach ($event->sessions()->whereNotIn('id', $keep)->get() as $s) {
            \App\Models\Order::where('event_session_id', $s->id)->exists() ? $s->update(['sales_open' => false]) : $s->delete();
        }
        $event->syncDatesFromSessions();
    }

    private function syncTicketTypes(Request $request, Event $event): void
    {
        $keep = [];
        // قیمت صندلی‌ها به تفکیک دسته‌بندی سالن
        if ($event->hall_id) {
            $catIds = Hall::find($event->hall_id)->categories->pluck('id', 'id');
            foreach ((array) $request->input('seat_price', []) as $catId => $price) {
                if (! isset($catIds[$catId]) || $price === null || $price === '') {
                    continue;
                }
                $cat = $event->hall->categories->firstWhere('id', $catId);
                $t = $event->ticketTypes()->updateOrCreate(
                    ['seat_category_id' => $catId],
                    ['name' => $cat->name, 'price' => (int) en_digits($price), 'is_active' => true, 'capacity' => null]
                );
                $keep[] = $t->id;
            }
        }
        // بلیط عمومی (بدون صندلی)
        foreach ((array) $request->input('general', []) as $row) {
            if (empty($row['name'])) {
                continue;
            }
            $attrs = [
                'name' => $row['name'], 'price' => (int) en_digits($row['price'] ?? 0),
                'capacity' => ($row['capacity'] ?? '') === '' ? null : (int) en_digits($row['capacity']),
                'is_active' => true, 'seat_category_id' => null,
            ];
            $t = ! empty($row['id']) ? $event->ticketTypes()->whereNull('seat_category_id')->find($row['id']) : null;
            $t ? $t->update($attrs) : ($t = $event->ticketTypes()->create($attrs));
            $keep[] = $t->id;
        }
        // حذف یا غیرفعال‌سازی بقیه
        foreach ($event->ticketTypes()->whereNotIn('id', $keep)->get() as $t) {
            OrderItem::where('ticket_type_id', $t->id)->exists() ? $t->update(['is_active' => false]) : $t->delete();
        }
    }

    public function destroy(Event $event)
    {
        if ($event->orders()->exists()) {
            return back()->with('error', 'برای این رویداد سفارش ثبت شده است؛ به‌جای حذف آن را پیش‌نویس کنید.');
        }
        $event->delete();
        return redirect()->route('admin.events.index')->with('status', 'رویداد حذف شد.');
    }

    /** گزارش فروش و لیست بلیط‌ها */
    public function report(Request $request, Event $event)
    {
        OrderService::releaseExpired();
        $items = OrderItem::with(['order.user', 'order.session', 'order.event', 'ticketType', 'seat'])->where('event_id', $event->id)
            ->whereHas('order', fn ($q) => $q->where('status', 'paid'))
            ->when($request->filled('session'), fn ($q) => $q->where('event_session_id', $request->session))->orderBy('id')->get();
        $event->load('sessions');
        return view('admin.events.report', compact('event', 'items'));
    }
}
