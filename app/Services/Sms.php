<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/** ارسال پیامک با وب‌سرویس کاوه‌نگار (verify/lookup) */
class Sms
{
    public static function configured(): bool
    {
        return (bool) setting('kavenegar_api_key');
    }

    public static function lookup(string $receptor, string $template, string $token, ?string $token2 = null, ?string $token3 = null): bool
    {
        $key = setting('kavenegar_api_key');
        if (! $key || ! $template) {
            Log::info("[SMS-DEV] to=$receptor template=$template token=$token");
            return false;
        }
        $params = ['receptor' => $receptor, 'template' => $template, 'token' => $token];
        // کاوه‌نگار در توکن‌ها فاصله مجاز نمی‌داند
        if ($token2) $params['token2'] = str_replace(' ', '_', $token2);
        if ($token3) $params['token3'] = str_replace(' ', '_', $token3);

        try {
            $res = Http::timeout(15)->get("https://api.kavenegar.com/v1/{$key}/verify/lookup.json", $params);
            $ok = $res->ok() && data_get($res->json(), 'return.status') == 200;
            if (! $ok) {
                Log::warning('Kavenegar failed', ['body' => $res->body()]);
            }
            return $ok;
        } catch (\Throwable $e) {
            Log::error('Kavenegar error: '.$e->getMessage());
            return false;
        }
    }
}
