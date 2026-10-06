<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Admin;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\PageController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/events', [HomeController::class, 'events'])->name('events.index');
Route::get('/events/{event}', [EventController::class, 'show'])->name('events.show');
Route::get('/events/{event}/seatmap', [EventController::class, 'seatmap'])->name('events.seatmap');
Route::post('/events/{event}/reserve', [EventController::class, 'reserve'])->name('events.reserve');
Route::get('/page/{slug}', [PageController::class, 'show'])->name('page');

// ورود با OTP
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'sendCode'])->middleware('throttle:10,1')->name('login.send');
    Route::get('/login/verify', [AuthController::class, 'showVerify'])->name('login.verify');
    Route::post('/login/verify', [AuthController::class, 'verify'])->middleware('throttle:15,1')->name('login.check');
    Route::get('/admin/login', [AuthController::class, 'showPasswordLogin'])->name('login.password');
    Route::post('/admin/login', [AuthController::class, 'passwordLogin'])->middleware('throttle:10,1');
});
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [AccountController::class, 'profile'])->name('profile');
    Route::post('/profile', [AccountController::class, 'updateProfile']);
    Route::get('/orders', [AccountController::class, 'orders'])->name('orders');
    Route::get('/orders/{order}', [AccountController::class, 'order'])->name('orders.show');
    Route::get('/checkout', [CheckoutController::class, 'show'])->name('checkout');
    Route::post('/checkout', [CheckoutController::class, 'store'])->name('checkout.store');
    Route::post('/orders/{order}/pay', [CheckoutController::class, 'retry'])->name('orders.pay');
});

// بازگشت از درگاه (POST/GET) — خارج از CSRF
Route::match(['get', 'post'], '/payment/callback/{payment}', [CheckoutController::class, 'callback'])->name('payment.callback');
Route::get('/payment/fake/{payment}', [CheckoutController::class, 'fake'])->name('payment.fake');

// پنل مدیریت
Route::prefix('admin')->name('admin.')->middleware(['auth', 'admin'])->group(function () {
    Route::get('/', [Admin\DashboardController::class, 'index'])->name('dashboard');

    Route::resource('events', Admin\EventController::class)->except('show');
    Route::get('events/{event}/report', [Admin\EventController::class, 'report'])->name('events.report');

    Route::resource('venues', Admin\VenueController::class)->except('show');
    Route::get('venues/{venue}/halls', [Admin\HallController::class, 'index'])->name('halls.index');
    Route::post('venues/{venue}/halls', [Admin\HallController::class, 'store'])->name('halls.store');
    Route::delete('halls/{hall}', [Admin\HallController::class, 'destroy'])->name('halls.destroy');
    Route::get('halls/{hall}/designer', [Admin\HallController::class, 'designer'])->name('halls.designer');
    Route::post('halls/{hall}/designer', [Admin\HallController::class, 'save'])->name('halls.save');

    Route::get('orders', [Admin\OrderController::class, 'index'])->name('orders.index');
    Route::get('orders/{order}', [Admin\OrderController::class, 'show'])->name('orders.show');
    Route::post('orders/{order}/cancel', [Admin\OrderController::class, 'cancel'])->name('orders.cancel');

    Route::get('check', [Admin\TicketCheckController::class, 'index'])->name('check');
    Route::post('check/{item}', [Admin\TicketCheckController::class, 'checkIn'])->name('check.in');

    Route::get('users', [Admin\UserController::class, 'index'])->name('users.index');
    Route::put('users/{user}', [Admin\UserController::class, 'update'])->name('users.update');

    Route::resource('categories', Admin\CategoryController::class)->except('show');
    Route::resource('sliders', Admin\SliderController::class)->except('show');
    Route::resource('pages', Admin\PageController::class)->except('show');
    Route::resource('licenses', Admin\LicenseController::class)->except('show');

    Route::get('settings', [Admin\SettingController::class, 'edit'])->name('settings');
    Route::post('settings', [Admin\SettingController::class, 'update'])->name('settings.update');
});
