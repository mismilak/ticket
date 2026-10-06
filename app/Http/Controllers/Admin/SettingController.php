<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function edit()
    {
        return view('admin.settings', ['groups' => config('ticket.groups')]);
    }

    public function update(Request $request)
    {
        foreach (config('ticket.groups') as $group) {
            foreach ($group['fields'] as $key => $f) {
                $type = $f['type'];
                if ($type === 'checkbox') {
                    Setting::put($key, $request->boolean($key) ? '1' : '0');
                } elseif ($type === 'image') {
                    if ($request->hasFile($key)) {
                        Setting::put($key, ResourceController::storeImage($request->file($key)));
                    } elseif ($request->boolean("remove_$key")) {
                        Setting::put($key, null);
                    }
                } elseif ($type === 'password') {
                    // مقدار خالی = بدون تغییر
                    if ($request->filled($key)) {
                        Setting::put($key, $request->input($key));
                    }
                } elseif ($request->has($key)) {
                    Setting::put($key, $request->input($key));
                }
            }
        }
        return back()->with('status', 'تنظیمات ذخیره شد.');
    }
}
