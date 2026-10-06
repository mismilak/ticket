<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Otp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function showLogin(Request $request)
    {
        if ($request->filled('next')) {
            session(['url.intended' => $request->query('next')]);
        }
        return view('auth.login');
    }

    public function sendCode(Request $request)
    {
        $mobile = normalize_mobile($request->input('mobile'));
        if (! $mobile) {
            return back()->withInput()->withErrors(['mobile' => 'شماره موبایل معتبر نیست (مثال: 09123456789)']);
        }
        $r = Otp::send($mobile);
        if (! $r['ok'] && empty($r['wait'])) {
            return back()->withInput()->withErrors(['mobile' => $r['message']]);
        }
        session(['otp_mobile' => $mobile, 'otp_dev' => $r['dev_code'] ?? null]);
        return redirect()->route('login.verify')->with($r['ok'] ? 'status' : 'error', $r['ok'] ? 'کد تایید ارسال شد.' : $r['message']);
    }

    public function showVerify()
    {
        if (! session('otp_mobile')) {
            return redirect()->route('login');
        }
        return view('auth.verify', ['mobile' => session('otp_mobile'), 'dev' => session('otp_dev')]);
    }

    public function verify(Request $request)
    {
        $mobile = session('otp_mobile');
        if (! $mobile) {
            return redirect()->route('login');
        }
        $request->validate(['code' => 'required']);
        if (! Otp::check($mobile, $request->code)) {
            return back()->withErrors(['code' => 'کد وارد شده نادرست یا منقضی است.']);
        }
        $user = User::firstOrCreate(['mobile' => $mobile], ['role' => 'customer']);
        Auth::login($user, true);
        $request->session()->regenerate();
        session()->forget(['otp_mobile', 'otp_dev']);

        if (! $user->name) {
            return redirect()->route('profile')->with('status', 'خوش آمدید! لطفاً نام خود را وارد کنید.');
        }
        return redirect()->intended($user->isAdmin() ? route('admin.dashboard') : route('home'));
    }

    public function showPasswordLogin()
    {
        return view('auth.password');
    }

    /** ورود مدیر با رمز عبور */
    public function passwordLogin(Request $request)
    {
        $data = $request->validate(['mobile' => 'required', 'password' => 'required']);
        $mobile = normalize_mobile($data['mobile']);
        if ($mobile && Auth::attempt(['mobile' => $mobile, 'password' => $data['password'], 'role' => 'admin'], true)) {
            $request->session()->regenerate();
            return redirect()->intended(route('admin.dashboard'));
        }
        return back()->withErrors(['mobile' => 'اطلاعات ورود نادرست است.'])->withInput();
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('home');
    }
}
