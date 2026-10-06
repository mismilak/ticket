<?php

namespace App\Payment;

use App\Models\Payment;
use Illuminate\Http\Request;

abstract class Gateway
{
    /** کلید در تنظیمات و config('ticket.gateways') */
    abstract public function key(): string;

    public function enabled(): bool
    {
        return (bool) setting($this->key().'_enabled');
    }

    /**
     * شروع پرداخت.
     * @return array{type:'redirect',url:string}|array{type:'post',url:string,fields:array}
     * @throws \RuntimeException
     */
    abstract public function start(Payment $payment, string $callbackUrl): array;

    /**
     * تایید پرداخت پس از بازگشت از درگاه.
     * @return array{ok:bool,ref?:string,card?:string,message?:string}
     */
    abstract public function verify(Payment $payment, Request $request): array;

    /** مبلغ ریالی */
    protected function rial(Payment $p): int
    {
        return $p->amount * 10;
    }

    public static function make(string $key): ?self
    {
        $class = config("ticket.gateways.$key.class");
        return $class ? new $class() : null;
    }

    /** درگاه‌های فعال */
    public static function active(): array
    {
        $out = [];
        foreach (config('ticket.gateways') as $key => $cfg) {
            $g = new $cfg['class']();
            if ($g->enabled()) {
                $out[$key] = $cfg['title'];
            }
        }
        return $out;
    }
}
