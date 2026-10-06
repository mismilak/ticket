<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    public function profile()
    {
        return view('account.profile', ['user' => auth()->user()]);
    }

    public function updateProfile(Request $request)
    {
        $data = $request->validate(['name' => 'required|string|max:80', 'email' => 'nullable|email|max:120']);
        $request->user()->update($data);
        return redirect()->intended(route('orders'))->with('status', 'اطلاعات ذخیره شد.');
    }

    public function orders()
    {
        $orders = auth()->user()->orders()->with('event')->latest()->paginate(10);
        return view('account.orders', compact('orders'));
    }

    public function order(Order $order)
    {
        abort_unless($order->user_id === auth()->id() || auth()->user()->isAdmin(), 404);
        $order->load(['event.hall.venue', 'items.ticketType', 'items.seat.level', 'payments']);
        return view('account.order', compact('order'));
    }
}
