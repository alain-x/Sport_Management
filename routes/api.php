<?php

declare(strict_types=1);

use App\Http\Controllers\Api\AuthApiController;
use App\Http\Controllers\Api\ChatApiController;
use App\Http\Controllers\Api\DashboardApiController;
use App\Http\Controllers\Api\EventApiController;
use App\Http\Controllers\Api\MatchApiController;
use App\Http\Controllers\Api\PlayerApiController;
use App\Http\Controllers\Api\TeamApiController;
use App\Http\Controllers\Api\TournamentApiController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Mobile API (JSON, token-authenticated)
|--------------------------------------------------------------------------
|
| Consumed by the Flutter app in sport_management_android/. All routes are
| prefixed with /api (see bootstrap/app.php). Authentication uses Sanctum
| personal access tokens: send "Authorization: Bearer <token>".
|
|   POST /api/login           { email, password, device_name? } -> { token, user }
|   POST /api/logout          (auth) revoke current token
|   GET  /api/me              (auth) current user
|
| Everything below the auth group requires a valid token.
|
*/

// ----- Public -----
Route::post('/login', [AuthApiController::class, 'login']);

// ----- Authenticated -----
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthApiController::class, 'logout']);
    Route::get('/me', [AuthApiController::class, 'me']);

    Route::get('/dashboard', [DashboardApiController::class, 'index']);

    // Teams.
    Route::get('/teams', [TeamApiController::class, 'index']);
    Route::get('/teams/{id}', [TeamApiController::class, 'show'])->whereNumber('id');
    Route::get('/teams/{id}/matches', [TournamentApiController::class, 'teamMatches'])->whereNumber('id');

    // Players.
    Route::get('/players', [PlayerApiController::class, 'index']);
    Route::get('/players/{id}', [PlayerApiController::class, 'show'])->whereNumber('id');

    // Tournaments + standings.
    Route::get('/tournaments', [TournamentApiController::class, 'index']);
    Route::get('/tournaments/{id}', [TournamentApiController::class, 'show'])->whereNumber('id');
    Route::get('/tournaments/{id}/standings', [TournamentApiController::class, 'standings'])->whereNumber('id');
    Route::get('/standings', [TournamentApiController::class, 'activeStandings']);

    // Matches.
    Route::get('/matches', [MatchApiController::class, 'index']);
    Route::get('/matches/{id}', [MatchApiController::class, 'show'])->whereNumber('id');
    Route::post('/matches', [MatchApiController::class, 'store']);

    // Events + attendance.
    Route::get('/events', [EventApiController::class, 'index']);
    Route::get('/events/{id}', [EventApiController::class, 'show'])->whereNumber('id');
    Route::get('/events/{id}/attendance', [EventApiController::class, 'roster'])->whereNumber('id');
    Route::post('/events/{id}/attendance', [EventApiController::class, 'checkIn'])->whereNumber('id');

    // Chat.
    Route::get('/chat', [ChatApiController::class, 'index']);
    Route::get('/chat/{id}/messages', [ChatApiController::class, 'messages'])->whereNumber('id');
    Route::post('/chat/{id}', [ChatApiController::class, 'post'])->whereNumber('id');
});
