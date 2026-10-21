<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
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

        if ($errors === [] && ! $this->credentialsMatch($email, $password)) {
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

    /**
     * Attempt to sign the user in.
     *
     * A stored value that is not a valid hash (for example a plaintext password
     * inserted straight into the database) would make BcryptHasher::verify()
     * throw "This password does not use the Bcrypt algorithm" and surface as a
     * 500. Treat such a row as a non-matching credential instead of crashing.
     */
    private function credentialsMatch(string $email, string $password): bool
    {
        $user = User::where('email', $email)->first();

        if ($user === null) {
            return false;
        }

        $hash = (string) $user->getRawOriginal('password');

        if (! $this->isUsableHash($hash)) {
            return false;
        }

        return Auth::attempt(['email' => $email, 'password' => $password]);
    }

    /** True when the value looks like a hash the configured hasher can verify. */
    private function isUsableHash(string $hash): bool
    {
        // bcrypt ($2y$ / $2a$ / $2b$), argon2i and argon2id.
        return (bool) preg_match('/^\$(2[aby]|argon2i|argon2id)\$/', $hash);
    }
}
