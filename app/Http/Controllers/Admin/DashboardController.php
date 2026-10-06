<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Services\OrderService;

class DashboardController extends Controller
{
    public function index()
    {
        OrderService::releaseExpired();
        $paid = Order::where('status', 'paid');
        return view('admin.dashboard', [
            'stats' => [
                'فروش کل' => price((clone $paid)->sum('total')),
                'فروش امروز' => price((clone $paid)->whereDate('paid_at', today())->sum('total')),
                'بلیط‌های فروخته‌شده' => fa_digits(OrderItem::whereHas('order', fn ($q) => $q->where('status', 'paid'))->count()),
                'کاربران' => fa_digits(User::count()),
                'رویدادهای فعال' => fa_digits(Event::published()->upcoming()->count()),
                'سفارش در انتظار' => fa_digits(Order::where('status', 'pending')->count()),
            ],
            'latest' => Order::with(['user', 'event'])->latest()->limit(10)->get(),
        ]);
    }
}
