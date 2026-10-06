<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        OrderService::releaseExpired();
        $q = Order::with(['user', 'event'])->latest();
        if ($request->filled('status')) {
            $q->where('status', $request->status);
        }
        if ($s = trim((string) $request->q)) {
            $q->where(fn ($w) => $w->where('code', 'like', "%$s%")->orWhereHas('user', fn ($u) => $u->where('mobile', 'like', "%$s%")->orWhere('name', 'like', "%$s%")));
        }
        return view('admin.orders.index', ['orders' => $q->paginate(30)->withQueryString()]);
    }

    public function show(Order $order)
    {
        $order->load(['user', 'event', 'session', 'items.ticketType', 'items.seat', 'payments']);
        return view('admin.orders.show', compact('order'));
    }

    public function cancel(Order $order)
    {
        if ($order->status === 'paid') {
            OrderService::release($order, 'canceled');
            $order->items()->update(['ticket_code' => null]);
            $msg = 'سفارش لغو و بلیط‌ها باطل شد. (بازگشت وجه دستی از درگاه انجام شود)';
        } else {
            OrderService::release($order, 'canceled');
            $msg = 'سفارش لغو شد.';
        }
        return back()->with('status', $msg);
    }
}
