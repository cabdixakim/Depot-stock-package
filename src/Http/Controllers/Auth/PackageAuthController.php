<?php

namespace Optima\DepotStock\Http\Controllers\Auth;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

class PackageAuthController extends Controller
{
    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->to('/login'); // or your app's main login route
    }

   public function login(Request $request)
{
    $credentials = $request->only('email', 'password');

    if (Auth::attempt($credentials)) {
        $request->session()->regenerate();

        // One central decision point
        return redirect()->route('dashboard');
    }

    return back()->withErrors(['email' => 'Invalid credentials']);
}

}