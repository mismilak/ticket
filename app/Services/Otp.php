<?php

namespace App\Services;

use App\Models\OtpCode;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class Otp
{
    public const TTL = 120;      // ثانیه اعتبار کد
    public const RESEND = 60;    // ثانیه فاصله ارسال مجدد
    public const MAX_ATTEMPTS = 5;

    /** @return array{ok:bool,message?:string,dev_code?:string,wait?:int} */
    public static function send(string $mobile): array
    {
        $last = OtpCode::where('mobile', $mobile)->latest('id')->first();
        if ($last && $last->created_at->diffInSeconds(now(), true) < self::RESEND) {
            return ['ok' => false, 'wait' => self::RESEND - (int) $last->created_at->diffInSeconds(now(), true), 'message' => 'لطفاً کمی صبر کنید و دوباره تلاش کنید.'];
        }
        $recent = OtpCode::where('mobile', $mobile)->where('created_at', '>', now()->subHour())->count();
        if ($recent >= 8) {
            return ['ok' => false, 'message' => 'تعداد درخواست‌ها زیاد است. بعداً تلاش کنید.'];
        }

        $code = (string) random_int(10000, 99999);
        OtpCode::where('mobile', $mobile)->delete();
        OtpCode::create([
            'mobile' => $mobile,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addSeconds(self::TTL),
        ]);

        if (Sms::configured()) {
            $sent = Sms::lookup($mobile, setting('kavenegar_otp_template', 'verify'), $code);
            if (! $sent) {
                return ['ok' => false, 'message' => 'ارسال پیامک با خطا مواجه شد. دوباره تلاش کنید.'];
            }
            return ['ok' => true];
        }

        // محیط توسعه: کلید پیامک تنظیم نشده
        Log::info("[OTP-DEV] $mobile => $code");
        return ['ok' => true, 'dev_code' => config('app.debug') ? $code : null];
    }

    public static function check(string $mobile, string $code): bool
    {
        $otp = OtpCode::where('mobile', $mobile)->latest('id')->first();
        if (! $otp || $otp->expires_at->isPast() || $otp->attempts >= self::MAX_ATTEMPTS) {
            return false;
        }
        $otp->increment('attempts');
        if (Hash::check(en_digits(trim($code)), $otp->code_hash)) {
            $otp->delete();
            return true;
        }
        return false;
    }
}
