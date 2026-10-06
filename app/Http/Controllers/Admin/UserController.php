<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $q = User::withCount('orders')->latest();
        if ($s = trim((string) $request->q)) {
            $q->where(fn ($w) => $w->where('mobile', 'like', "%$s%")->orWhere('name', 'like', "%$s%"));
        }
        return view('admin.users.index', ['users' => $q->paginate(30)->withQueryString()]);
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate(['role' => 'required|in:admin,customer', 'password' => 'nullable|min:6']);
        if ($user->id === $request->user()->id && $data['role'] !== 'admin') {
            return back()->with('error', 'نمی‌توانید نقش خودتان را کاهش دهید.');
        }
        $user->role = $data['role'];
        if (! empty($data['password'])) {
            $user->password = $data['password'];
        }
        $user->save();
        return back()->with('status', 'کاربر ویرایش شد.');
    }
}
