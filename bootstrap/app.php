<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        apiPrefix: 'api',
        then: function () {
            Route::middleware('web')
                ->group(base_path('routes/auth.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Guests hitting a protected page are sent to the login screen.
        $middleware->redirectGuestsTo('/login');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // API requests always receive JSON, never an HTML redirect to /login.
        $exceptions->shouldRenderJsonWhen(function (Request $request) {
            return $request->is('api/*') || $request->expectsJson();
        });

        // A missing/expired CSRF token must answer with a bare 419 so the
        // smoke suite (and the original app's contract) sees the same status.
        $exceptions->render(function (TokenMismatchException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'CSRF token mismatch.'], 419);
            }

            return response('419 Session Expired', 419);
        });

        // 403 pages render the app's standalone forbidden screen when they
        // come from an authenticated web request.
        $exceptions->render(function (HttpExceptionInterface $e, Request $request) {
            $status = $e->getStatusCode();
            if (! $request->expectsJson() && $status === 403 && auth()->check()) {
                return response()->view('errors.403', [
                    'appName' => config('app.name', 'Sport Manager'),
                    'errorMessage' => $e->getMessage() ?: 'You do not have permission to view this page.',
                ], 403);
            }

            return null;
        });

        // 404 pages render the app's standalone not-found screen.
        $exceptions->render(function (HttpExceptionInterface $e, Request $request) {
            if (! $request->expectsJson() && $e->getStatusCode() === 404) {
                return response()->view('errors.404', [
                    'appName' => config('app.name', 'Sport Manager'),
                ], 404);
            }

            return null;
        });
    })->create();
