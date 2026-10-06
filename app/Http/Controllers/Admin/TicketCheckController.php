<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\OrderItem;
use Illuminate\Http\Request;

/** اعتبارسنجی و ورود بلیط (Check-in) */
class TicketCheckController extends Controller
{
    public function index(Request $request)
    {
        $item = null;
        if ($code = strtoupper(trim((string) $request->code))) {
            $item = OrderItem::with(['order.user', 'order.event', 'order.session', 'seat', 'ticketType'])->where('ticket_code', $code)->first();
            if (! $item || $item->order->status !== 'paid') {
                $item = null;
                session()->now('error', 'بلیطی با این کد معتبر نیست.');
            }
        }
        return view('admin.check', ['item' => $item, 'code' => $code]);
    }

    public function checkIn(OrderItem $item)
    {
        abort_unless($item->order->status === 'paid' && $item->ticket_code, 404);
        if ($item->checked_in_at) {
            return back()->with('error', 'این بلیط قبلاً استفاده شده است ('.jdate($item->checked_in_at).').');
        }
        $item->update(['checked_in_at' => now()]);
        return redirect()->route('admin.check', ['code' => $item->ticket_code])->with('status', 'ورود ثبت شد.');
    }
}
