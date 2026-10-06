<?php

use App\Models\Setting;
use Carbon\Carbon;

if (! function_exists('setting')) {
    function setting(string $key, $default = null)
    {
        return Setting::get($key, $default);
    }
}

if (! function_exists('fa_digits')) {
    function fa_digits($value): string
    {
        return strtr((string) $value, ['0'=>'۰','1'=>'۱','2'=>'۲','3'=>'۳','4'=>'۴','5'=>'۵','6'=>'۶','7'=>'۷','8'=>'۸','9'=>'۹']);
    }
}

if (! function_exists('en_digits')) {
    function en_digits($value): string
    {
        return strtr((string) $value, [
            '۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9',
            '٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9',
        ]);
    }
}

if (! function_exists('price')) {
    function price($amount): string
    {
        return fa_digits(number_format((int) $amount)).' '.setting('currency_label', 'تومان');
    }
}

if (! function_exists('gregorian_to_jalali')) {
    function gregorian_to_jalali(int $gy, int $gm, int $gd): array
    {
        $g_d_m = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
        $gy2 = ($gm > 2) ? ($gy + 1) : $gy;
        $days = 355666 + (365 * $gy) + (int) (($gy2 + 3) / 4) - (int) (($gy2 + 99) / 100)
            + (int) (($gy2 + 399) / 400) + $gd + $g_d_m[$gm - 1];
        $jy = -1595 + (33 * (int) ($days / 12053));
        $days %= 12053;
        $jy += 4 * (int) ($days / 1461);
        $days %= 1461;
        if ($days > 365) {
            $jy += (int) (($days - 1) / 365);
            $days = ($days - 1) % 365;
        }
        if ($days < 186) {
            $jm = 1 + (int) ($days / 31);
            $jd = 1 + ($days % 31);
        } else {
            $jm = 7 + (int) (($days - 186) / 30);
            $jd = 1 + (($days - 186) % 30);
        }
        return [$jy, $jm, $jd];
    }
}

if (! function_exists('jalali_to_gregorian')) {
    function jalali_to_gregorian(int $jy, int $jm, int $jd): array
    {
        $jy += 1595;
        $days = -355668 + (365 * $jy) + ((int) ($jy / 33) * 8) + (int) ((($jy % 33) + 3) / 4) + $jd
            + (($jm < 7) ? ($jm - 1) * 31 : (($jm - 7) * 30) + 186);
        $gy = 400 * (int) ($days / 146097);
        $days %= 146097;
        if ($days > 36524) {
            $gy += 100 * (int) (--$days / 36524);
            $days %= 36524;
            if ($days >= 365) {
                $days++;
            }
        }
        $gy += 4 * (int) ($days / 1461);
        $days %= 1461;
        if ($days > 365) {
            $gy += (int) (($days - 1) / 365);
            $days = ($days - 1) % 365;
        }
        $gd = $days + 1;
        $sal_a = [0, 31, (($gy % 4 == 0 && $gy % 100 != 0) || ($gy % 400 == 0)) ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
        for ($gm = 0; $gm < 13 && $gd > $sal_a[$gm]; $gm++) {
            $gd -= $sal_a[$gm];
        }
        return [$gy, $gm, $gd];
    }
}

if (! function_exists('jalali_months')) {
    function jalali_months(): array
    {
        return ['', 'فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'];
    }
}

if (! function_exists('jdate')) {
    /** فرمت: full | date | time | input | short */
    function jdate($date, string $format = 'full'): string
    {
        if (! $date) {
            return '';
        }
        $c = $date instanceof Carbon ? $date : Carbon::parse($date);
        [$y, $m, $d] = gregorian_to_jalali($c->year, $c->month, $c->day);
        $days = ['یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنجشنبه', 'جمعه', 'شنبه'];
        $time = $c->format('H:i');
        return match ($format) {
            'input' => sprintf('%04d/%02d/%02d %s', $y, $m, $d, $time),
            'date' => fa_digits(sprintf('%04d/%02d/%02d', $y, $m, $d)),
            'time' => fa_digits($time),
            'short' => fa_digits($d).' '.jalali_months()[$m],
            default => $days[$c->dayOfWeek].' '.fa_digits($d).' '.jalali_months()[$m].' '.fa_digits($y).' - '.fa_digits($time),
        };
    }
}

if (! function_exists('parse_jdate')) {
    /** "1405/07/20 21:30" → Carbon (null if invalid) */
    function parse_jdate(?string $value): ?Carbon
    {
        $value = trim(en_digits((string) $value));
        if (! preg_match('#^(\d{4})[/-](\d{1,2})[/-](\d{1,2})(?:\s+(\d{1,2}):(\d{2}))?$#', $value, $m)) {
            return null;
        }
        if ($m[1] < 1300) {
            return null;
        }
        [$gy, $gm, $gd] = jalali_to_gregorian((int) $m[1], (int) $m[2], (int) $m[3]);
        return Carbon::create($gy, $gm, $gd, (int) ($m[4] ?? 0), (int) ($m[5] ?? 0));
    }
}

if (! function_exists('upload_url')) {
    function upload_url(?string $path): ?string
    {
        return $path ? asset('uploads/'.$path) : null;
    }
}

if (! function_exists('normalize_mobile')) {
    function normalize_mobile(?string $m): ?string
    {
        $m = preg_replace('/\D/', '', en_digits((string) $m));
        if (str_starts_with($m, '0098')) {
            $m = '0'.substr($m, 4);
        } elseif (str_starts_with($m, '98') && strlen($m) === 12) {
            $m = '0'.substr($m, 2);
        } elseif (strlen($m) === 10 && $m[0] === '9') {
            $m = '0'.$m;
        }
        return preg_match('/^09\d{9}$/', $m) ? $m : null;
    }
}

if (! function_exists('slugify')) {
    /** اسلاگ سازگار با حروف فارسی */
    function slugify(?string $s): string
    {
        $s = en_digits((string) $s);
        $s = preg_replace('/[^\p{L}\p{N}]+/u', '-', mb_strtolower($s));
        return trim($s, '-');
    }
}
