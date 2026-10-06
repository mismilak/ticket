<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Seat;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrderService
{
    /** آزاد کردن سفارش‌های منقضی‌شده (صندلی‌ها دوباره قابل خرید می‌شوند) */
    public static function releaseExpired(): void
    {
        $ids = Order::where('status', 'pending')->where('expires_at', '<', now())->pluck('id');
        if ($ids->isEmpty()) {
            return;
        }
        DB::transaction(function () use ($ids) {
            Order::whereIn('id', $ids)->update(['status' => 'expired']);
            OrderItem::whereIn('order_id', $ids)->update(['active' => false, 'lock_key' => null]);
        });
    }

    /** وضعیت صندلی‌های رویداد: [seat_id => 'sold'|'held'] */
    public static function seatStatuses(Event $event): array
    {
        self::releaseExpired();
        return OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('order_items.event_id', $event->id)
            ->where('order_items.active', true)
            ->whereNotNull('order_items.seat_id')
            ->pluck('orders.status', 'order_items.seat_id')
            ->map(fn ($s) => $s === 'paid' ? 'sold' : 'held')
            ->all();
    }

    /** تعداد فروخته‌شده/رزروشده هر نوع بلیط عمومی */
    public static function soldCounts(Event $event): array
    {
        return OrderItem::where('event_id', $event->id)->where('active', true)
            ->selectRaw('ticket_type_id, count(*) c')->groupBy('ticket_type_id')->pluck('c', 'ticket_type_id')->all();
    }

    /**
     * ساخت سفارش از سبد.
     * $cart = ['seats' => [seat_id,...], 'general' => [ticket_type_id => qty]]
     * @throws \RuntimeException با پیام فارسی
     */
    public static function create(User $user, Event $event, array $cart): Order
    {
        self::releaseExpired();
        $event->load('ticketTypes');
        if (! $event->isOnSale()) {
            throw new \RuntimeException('فروش بلیط این رویداد فعال نیست.');
        }

        $seatIds = array_values(array_unique(array_map('intval', $cart['seats'] ?? [])));
        $general = array_filter(array_map('intval', $cart['general'] ?? []), fn ($q) => $q > 0);
        $count = count($seatIds) + array_sum($general);
        $max = (int) setting('max_per_order', 10);
        if ($count < 1) {
            throw new \RuntimeException('هیچ بلیطی انتخاب نشده است.');
        }
        if ($count > $max) {
            throw new \RuntimeException("حداکثر $max بلیط در هر سفارش مجاز است.");
        }

        try {
            return DB::transaction(function () use ($user, $event, $seatIds, $general, $count) {
                $order = Order::create([
                    'code' => strtoupper(Str::random(8)),
                    'user_id' => $user->id,
                    'event_id' => $event->id,
                    'status' => 'pending',
                    'expires_at' => now()->addMinutes((int) setting('hold_minutes', 10)),
                ]);
                $total = 0;
                $typesByCat = $event->ticketTypes->where('is_active', true)->whereNotNull('seat_category_id')->keyBy('seat_category_id');

                if ($seatIds) {
                    $seats = Seat::with('level')->whereIn('id', $seatIds)->get();
                    if ($seats->count() !== count($seatIds) || $seats->contains(fn ($s) => $s->level->hall_id != $event->hall_id)) {
                        throw new \RuntimeException('صندلی انتخاب‌شده معتبر نیست.');
                    }
                    foreach ($seats as $seat) {
                        $type = $typesByCat[$seat->seat_category_id] ?? null;
                        if (! $type) {
                            throw new \RuntimeException('صندلی '.$seat->label.' برای فروش در دسترس نیست.');
                        }
                        OrderItem::create([
                            'order_id' => $order->id, 'event_id' => $event->id, 'ticket_type_id' => $type->id,
                            'seat_id' => $seat->id, 'price' => $type->price,
                            'lock_key' => $event->id.':'.$seat->id, // unique → جلوگیری از فروش مضاعف
                        ]);
                        $total += $type->price;
                    }
                }

                foreach ($general as $typeId => $qty) {
                    $type = $event->ticketTypes->where('is_active', true)->whereNull('seat_category_id')->firstWhere('id', $typeId);
                    if (! $type) {
                        throw new \RuntimeException('نوع بلیط معتبر نیست.');
                    }
                    if ($type->capacity !== null) {
                        $taken = OrderItem::where('ticket_type_id', $type->id)->where('active', true)->count();
                        if ($taken + $qty > $type->capacity) {
                            throw new \RuntimeException('ظرفیت «'.$type->name.'» کافی نیست.');
                        }
                    }
                    for ($i = 0; $i < $qty; $i++) {
                        OrderItem::create([
                            'order_id' => $order->id, 'event_id' => $event->id, 'ticket_type_id' => $type->id, 'price' => $type->price,
                        ]);
                        $total += $type->price;
                    }
                }

                $total += (int) setting('service_fee', 0) * $count;
                $order->update(['total' => $total]);
                return $order;
            });
        } catch (QueryException $e) {
            // نقض unique روی lock_key یعنی کس دیگری همزمان صندلی را گرفته
            throw new \RuntimeException('متاسفانه یکی از صندلی‌های انتخابی هم‌اکنون توسط شخص دیگری رزرو شد.');
        }
    }

    public static function markPaid(Order $order): void
    {
        if ($order->status === 'paid') {
            return;
        }
        DB::transaction(function () use ($order) {
            foreach ($order->items as $item) {
                if (! $item->ticket_code) {
                    do {
                        $code = strtoupper(Str::random(10));
                    } while (OrderItem::where('ticket_code', $code)->exists());
                    $item->update(['ticket_code' => $code, 'active' => true]);
                }
            }
            $order->update(['status' => 'paid', 'paid_at' => now()]);
        });

        if (setting('ticket_sms') && setting('kavenegar_ticket_template')) {
            Sms::lookup($order->user->mobile, setting('kavenegar_ticket_template'), $order->code, $order->event->title);
        }
    }

    public static function release(Order $order, string $status): void
    {
        if ($order->status === 'paid') {
            return;
        }
        DB::transaction(function () use ($order, $status) {
            $order->update(['status' => $status]);
            $order->items()->update(['active' => false, 'lock_key' => null]);
        });
    }
}
