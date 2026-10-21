<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /** Display the login view. */
    public function create(): View
    {
        return view('auth.login', [
            'appName' => config('app.name', 'Sport Manager'),
        ]);
    }

    /**
     * Handle an incoming authentication request.
     *
     * Mirrors the original app's behaviour + messages so the smoke suites keep
     * matching ("These credentials do not match our records.").
     */
    public function store(Request $request): RedirectResponse
    {
        $email = trim((string) $request->input('email', ''));
        $password = (string) $request->input('password', '');

        $errors = [];
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'][] = 'Please enter a valid email address.';
        }
        if ($password === '') {
            $errors['password'][] = 'Password is required.';
        }

        if ($errors === [] && ! Auth::attempt(['email' => $email, 'password' => $password])) {
            $errors['email'][] = 'These credentials do not match our records.';
        }

        if ($errors !== []) {
            return redirect('/login')->withInput(['email' => $email])->withErrors($errors);
        }

        $request->session()->regenerate();

        $name = Auth::user()?->name ?? 'Admin';

        return redirect('/')->with('success', 'Welcome back, '.$name.'!');
    }

    /** Destroy an authenticated session. */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login')->with('success', 'You have been signed out. See you soon!');
    }
}
