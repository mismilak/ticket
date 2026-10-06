<?php

namespace App\Payment;

use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/** بانک ملت — به‌پرداخت (SOAP؛ بدون نیاز به افزونه soap) */
class Mellat extends Gateway
{
    private const ENDPOINT = 'https://bpm.shaparak.ir/pgwchannel/services/pgw';
    private const NS = 'http://interfaces.core.sw.bps.com/';

    public function key(): string { return 'mellat'; }

    private function call(string $method, array $params): string
    {
        $xml = '<?xml version="1.0" encoding="utf-8"?><soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:int="'.self::NS.'"><soapenv:Body><int:'.$method.'>';
        foreach ($params as $k => $v) {
            $xml .= "<$k>".htmlspecialchars((string) $v, ENT_XML1)."</$k>";
        }
        $xml .= '</int:'.$method.'></soapenv:Body></soapenv:Envelope>';

        $res = Http::timeout(30)->withBody($xml, 'text/xml; charset=utf-8')
            ->withHeaders(['SOAPAction' => ''])->post(self::ENDPOINT);
        if (! preg_match('#<return[^>]*>(.*?)</return>#s', $res->body(), $m)) {
            throw new \RuntimeException('پاسخ نامعتبر از درگاه بانک ملت.');
        }
        return trim($m[1]);
    }

    private function auth(): array
    {
        return [
            'terminalId' => setting('mellat_terminal'),
            'userName' => setting('mellat_username'),
            'userPassword' => setting('mellat_password'),
        ];
    }

    public function start(Payment $payment, string $callbackUrl): array
    {
        $out = $this->call('bpPayRequest', $this->auth() + [
            'orderId' => $payment->id,
            'amount' => $this->rial($payment),
            'localDate' => now()->format('Ymd'),
            'localTime' => now()->format('His'),
            'additionalData' => $payment->order->code,
            'callBackUrl' => $callbackUrl,
            'payerId' => 0,
        ]);
        $parts = explode(',', $out);
        if (($parts[0] ?? '') !== '0' || empty($parts[1])) {
            throw new \RuntimeException('خطا از بانک ملت (کد '.$parts[0].')');
        }
        $payment->update(['authority' => $parts[1]]);
        return ['type' => 'post', 'url' => 'https://bpm.shaparak.ir/pgwchannel/startpay.mellat', 'fields' => ['RefId' => $parts[1]]];
    }

    public function verify(Payment $payment, Request $request): array
    {
        if ((string) $request->input('ResCode') !== '0') {
            return ['ok' => false, 'message' => 'پرداخت ناموفق بود (کد '.$request->input('ResCode').')'];
        }
        $saleRef = $request->input('SaleReferenceId');
        $common = $this->auth() + ['orderId' => $payment->id, 'saleOrderId' => $payment->id, 'saleReferenceId' => $saleRef];

        $v = $this->call('bpVerifyRequest', $common);
        if ($v !== '0') {
            $this->call('bpInquiryRequest', $common);
            return ['ok' => false, 'message' => 'تایید پرداخت ناموفق بود (کد '.$v.')'];
        }
        $this->call('bpSettleRequest', $common); // تسویه؛ کد 0 یا 45 یعنی موفق
        return ['ok' => true, 'ref' => (string) $saleRef, 'card' => $request->input('CardHolderPan')];
    }
}
