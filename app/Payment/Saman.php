<?php

namespace App\Payment;

use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/** سامان کیش — درگاه اینترنتی SEP (OnlinePG) */
class Saman extends Gateway
{
    public function key(): string { return 'saman'; }

    public function start(Payment $payment, string $callbackUrl): array
    {
        $res = Http::timeout(20)->acceptJson()->post('https://sep.shaparak.ir/onlinepg/onlinepg', [
            'action' => 'token',
            'TerminalId' => setting('saman_terminal'),
            'Amount' => $this->rial($payment),
            'ResNum' => (string) $payment->id,
            'RedirectUrl' => $callbackUrl,
            'CellNumber' => $payment->order->user->mobile,
        ]);
        $token = data_get($res->json(), 'token');
        if (data_get($res->json(), 'status') != 1 || ! $token) {
            throw new \RuntimeException('خطا از سامان کیش: '.data_get($res->json(), 'errorDesc', $res->status()));
        }
        $payment->update(['authority' => $token]);
        return ['type' => 'redirect', 'url' => 'https://sep.shaparak.ir/OnlinePG/SendToken?token='.urlencode($token)];
    }

    public function verify(Payment $payment, Request $request): array
    {
        $state = $request->input('State', $request->input('Status'));
        if ($request->input('State') !== 'OK' && $request->input('Status') != 2) {
            return ['ok' => false, 'message' => 'پرداخت ناموفق بود ('.$state.')'];
        }
        $refNum = $request->input('RefNum');
        if (! $refNum || (string) $request->input('ResNum') !== (string) $payment->id) {
            return ['ok' => false, 'message' => 'اطلاعات بازگشتی نامعتبر است.'];
        }
        if (Payment::where('ref_id', $refNum)->where('id', '!=', $payment->id)->exists()) {
            return ['ok' => false, 'message' => 'این تراکنش قبلاً استفاده شده است.'];
        }
        $res = Http::timeout(30)->acceptJson()->post('https://sep.shaparak.ir/verifyTxnRandomSessionkey/ipg/VerifyTransaction', [
            'RefNum' => $refNum,
            'TerminalNumber' => setting('saman_terminal'),
        ]);
        $j = $res->json();
        if (data_get($j, 'Success') === true && data_get($j, 'ResultCode') == 0
            && (int) data_get($j, 'TransactionDetail.OrginalAmount', $this->rial($payment)) === $this->rial($payment)) {
            return ['ok' => true, 'ref' => (string) $refNum, 'card' => $request->input('SecurePan')];
        }
        return ['ok' => false, 'message' => 'تایید پرداخت ناموفق بود: '.data_get($j, 'ResultDescription', 'خطای ناشناخته')];
    }
}
