<?php

namespace App\Payment;

use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/** زرین‌پال — API نسخه 4 */
class Zarinpal extends Gateway
{
    public function key(): string { return 'zarinpal'; }

    private function host(): string
    {
        return setting('zarinpal_sandbox') ? 'sandbox.zarinpal.com' : 'payment.zarinpal.com';
    }

    public function start(Payment $payment, string $callbackUrl): array
    {
        $res = Http::timeout(20)->acceptJson()->post("https://{$this->host()}/pg/v4/payment/request.json", [
            'merchant_id' => setting('zarinpal_merchant'),
            'amount' => $payment->amount,
            'currency' => 'IRT',
            'callback_url' => $callbackUrl,
            'description' => 'سفارش '.$payment->order->code,
            'metadata' => ['mobile' => $payment->order->user->mobile],
        ]);
        $authority = data_get($res->json(), 'data.authority');
        if (data_get($res->json(), 'data.code') != 100 || ! $authority) {
            throw new \RuntimeException('خطا در اتصال به زرین‌پال (کد '.data_get($res->json(), 'errors.code', $res->status()).')');
        }
        $payment->update(['authority' => $authority]);
        return ['type' => 'redirect', 'url' => "https://{$this->host()}/pg/StartPay/{$authority}"];
    }

    public function verify(Payment $payment, Request $request): array
    {
        if ($request->query('Status') !== 'OK' || $request->query('Authority') !== $payment->authority) {
            return ['ok' => false, 'message' => 'پرداخت توسط کاربر لغو شد یا ناموفق بود.'];
        }
        $res = Http::timeout(20)->acceptJson()->post("https://{$this->host()}/pg/v4/payment/verify.json", [
            'merchant_id' => setting('zarinpal_merchant'),
            'amount' => $payment->amount,
            'authority' => $payment->authority,
        ]);
        $code = data_get($res->json(), 'data.code');
        if (in_array($code, [100, 101])) {
            return ['ok' => true, 'ref' => (string) data_get($res->json(), 'data.ref_id'), 'card' => data_get($res->json(), 'data.card_pan')];
        }
        return ['ok' => false, 'message' => 'تایید پرداخت ناموفق بود (کد '.($code ?? data_get($res->json(), 'errors.code')).')'];
    }
}
