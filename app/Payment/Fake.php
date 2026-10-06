<?php

namespace App\Payment;

use App\Models\Payment;
use Illuminate\Http\Request;

/** درگاه آزمایشی داخلی برای تست بدون اتصال به بانک */
class Fake extends Gateway
{
    public function key(): string { return 'fake'; }

    public function start(Payment $payment, string $callbackUrl): array
    {
        $payment->update(['authority' => 'FAKE'.$payment->id]);
        return ['type' => 'redirect', 'url' => route('payment.fake', $payment)];
    }

    public function verify(Payment $payment, Request $request): array
    {
        if ($this->enabled() && $request->query('result') === 'ok') {
            return ['ok' => true, 'ref' => 'TEST'.$payment->id, 'card' => '6037-****-****-1234'];
        }
        return ['ok' => false, 'message' => 'پرداخت آزمایشی لغو شد.'];
    }
}
