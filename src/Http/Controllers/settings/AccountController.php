<?php

namespace Optima\DepotStock\Http\Controllers\Settings;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AccountController extends Controller
{
    /**
     * Update the logged-in user's profile (currently just name).
     */
    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $user->name = $data['name'];
        $user->save();

        return back()->with('status', 'Profile updated.');
    }

    /**
     * Update the logged-in user's password.
     */
    public function updatePassword(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password'         => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        // Manually verify current password
        if (! Hash::check($data['current_password'], $user->password)) {
            return back()
                ->withErrors(['current_password' => 'Current password is incorrect.'])
                ->withInput($request->except(['current_password', 'password', 'password_confirmation']));
        }

        $user->password = Hash::make($data['password']);
        $user->setRememberToken(Str::random(60));
        $user->save();

        return back()->with('status', 'Password updated.');
    }
}