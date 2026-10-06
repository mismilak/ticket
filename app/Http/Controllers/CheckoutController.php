<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventSession;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Seat;
use App\Models\TicketType;
use App\Payment\Gateway;
use App\Services\OrderService;
use Illuminate\Http\Request;

class CheckoutController extends Controller
{
    /** مرحله تایید سبد و انتخاب درگاه */
    public function show()
    {
        $cart = session('cart');
        if (! $cart) {
            return redirect()->route('home')->with('error', 'سبد خرید خالی است.');
        }
        if (! auth()->user()->name) {
            return redirect()->route('profile')->with('status', 'برای ادامه خرید نام خود را وارد کنید.');
        }
        $event = Event::with('ticketTypes')->findOrFail($cart['event']);
        $session = EventSession::where('event_id', $event->id)->findOrFail($cart['session']);
        $typesByCat = $event->ticketTypes->whereNotNull('seat_category_id')->keyBy('seat_category_id');
        $lines = [];
        $total = 0;
        foreach (Seat::with('level')->whereIn('id', $cart['seats'] ?? [])->get() as $seat) {
            $t = $typesByCat[$seat->seat_category_id] ?? null;
            $lines[] = ['title' => ($t?->name ?? 'صندلی').' — '.($seat->row_label ? 'ردیف '.$seat->row_label.' ' : '').'صندلی '.$seat->label.' ('.$seat->level->name.')', 'price' => $t?->price ?? 0];
            $total += $t?->price ?? 0;
        }
        foreach ($cart['general'] ?? [] as $id => $qty) {
            $t = $event->ticketTypes->firstWhere('id', $id);
            if ($t) {
                $lines[] = ['title' => $t->name.' × '.fa_digits($qty), 'price' => $t->price * $qty];
                $total += $t->price * $qty;
            }
        }
        $count = count($cart['seats'] ?? []) + array_sum($cart['general'] ?? []);
        $fee = (int) setting('service_fee', 0) * $count;

        return view('checkout', [
            'event' => $event, 'session' => $session, 'lines' => $lines, 'fee' => $fee, 'total' => $total + $fee,
            'gateways' => Gateway::active(),
        ]);
    }

    /** ساخت سفارش + شروع پرداخت */
    public function store(Request $request)
    {
        $cart = session('cart');
        abort_unless($cart, 404);
        $gateways = Gateway::active();
        $request->validate(['gateway' => 'required|in:'.implode(',', array_keys($gateways) ?: ['-'])], ['gateway.*' => 'درگاه پرداخت را انتخاب کنید.']);
        if (setting('terms') && ! $request->boolean('accept')) {
            return back()->with('error', 'برای ادامه باید قوانین را بپذیرید.');
        }

        try {
            $order = OrderService::create($request->user(), Event::findOrFail($cart['event']), EventSession::findOrFail($cart['session']), $cart);
        } catch (\RuntimeException $e) {
            return redirect()->route('events.show', [Event::find($cart['event']), 'session' => $cart['session']])->with('error', $e->getMessage());
        }
        session()->forget('cart');
        $order->update(['gateway' => $request->gateway]);
        return $this->startPayment($order, $request->gateway);
    }

    /** تلاش مجدد پرداخت برای سفارش در انتظار */
    public function retry(Request $request, Order $order)
    {
        abort_unless($order->user_id === $request->user()->id && $order->isPending(), 404);
        $gateways = Gateway::active();
        $key = $request->input('gateway', $order->gateway);
        abort_unless(isset($gateways[$key]), 422);
        $order->update(['gateway' => $key]);
        return $this->startPayment($order, $key);
    }

    private function startPayment(Order $order, string $key)
    {
        $payment = Payment::create(['order_id' => $order->id, 'gateway' => $key, 'amount' => $order->total]);
        try {
            $go = Gateway::make($key)->start($payment, route('payment.callback', $payment));
        } catch (\Throwable $e) {
            $payment->update(['status' => 'failed', 'message' => $e->getMessage()]);
            return redirect()->route('orders.show', $order)->with('error', $e->getMessage());
        }
        if ($go['type'] === 'redirect') {
            return redirect()->away($go['url']);
        }
        return view('payment.redirect', $go);
    }

    /** بازگشت از درگاه (GET/POST) */
    public function callback(Request $request, Payment $payment)
    {
        $order = $payment->order()->with('items', 'user', 'event', 'session')->first();

        if ($payment->status === 'paid' || $order->status === 'paid') {
            return redirect()->route('orders.show', $order);
        }
        try {
            $r = Gateway::make($payment->gateway)->verify($payment, $request);
        } catch (\Throwable $e) {
            $r = ['ok' => false, 'message' => 'خطا در تایید پرداخت: '.$e->getMessage()];
        }

        if ($r['ok']) {
            $payment->update(['status' => 'paid', 'ref_id' => $r['ref'] ?? null, 'card_pan' => $r['card'] ?? null, 'raw' => $request->all()]);
            // اگر در فاصله پرداخت سفارش منقضی شده باشد صندلی‌ها ممکن است از دست رفته باشند
            if ($order->status === 'expired' || $order->status === 'canceled') {
                $order->update(['status' => 'failed']);
                return redirect()->route('orders.show', $order)->with('error', 'پرداخت انجام شد اما مهلت سفارش تمام شده بود. برای بازگشت وجه با پشتیبانی تماس بگیرید. کد پیگیری: '.($r['ref'] ?? ''));
            }
            OrderService::markPaid($order);
            return redirect()->route('orders.show', $order)->with('status', 'پرداخت با موفقیت انجام شد.');
        }

        $payment->update(['status' => 'failed', 'message' => $r['message'] ?? null, 'raw' => $request->all()]);
        return redirect()->route('orders.show', $order)->with('error', $r['message'] ?? 'پرداخت ناموفق بود.');
    }

    /** صفحه درگاه آزمایشی */
    public function fake(Payment $payment)
    {
        abort_unless($payment->gateway === 'fake' && $payment->status === 'pending', 404);
        return view('payment.fake', compact('payment'));
    }
}
