<?php

declare(strict_types=1);

use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MatchController;
use App\Http\Controllers\PlayerController;
use App\Http\Controllers\StandingController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\TournamentController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Application routes
|--------------------------------------------------------------------------
|
| Static paths (e.g. /teams/create) are registered before parameterised ones
| (e.g. /teams/{id}). Role/team gates (admin-only, team-scoped) live inside
| the controllers. Authentication uses Laravel's auth middleware; the login
| routes live in routes/auth.php (Breeze).
|
*/

// Dashboard (all signed-in users; guests are redirected to login).
Route::middleware('auth')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
});

Route::middleware('auth')->group(function () {
    // Teams.
    Route::get('/teams', [TeamController::class, 'index'])->name('teams.index');
    Route::get('/teams/create', [TeamController::class, 'create'])->name('teams.create');
    Route::post('/teams', [TeamController::class, 'store'])->name('teams.store');
    Route::get('/teams/{id}', [TeamController::class, 'show'])->name('teams.show')->whereNumber('id');
    Route::get('/teams/{id}/edit', [TeamController::class, 'edit'])->name('teams.edit')->whereNumber('id');
    Route::post('/teams/{id}', [TeamController::class, 'update'])->name('teams.update')->whereNumber('id');
    Route::post('/teams/{id}/delete', [TeamController::class, 'destroy'])->name('teams.destroy')->whereNumber('id');

    // Team staff.
    Route::get('/teams/{id}/staff', [TeamController::class, 'staff'])->name('teams.staff')->whereNumber('id');
    Route::post('/teams/{id}/staff', [TeamController::class, 'storeStaff'])->name('teams.staff.store')->whereNumber('id');
    Route::get('/staff/{id}/edit', [UserController::class, 'editStaff'])->name('staff.edit')->whereNumber('id');
    Route::post('/staff/{id}', [UserController::class, 'updateStaff'])->name('staff.update')->whereNumber('id');
    Route::post('/staff/{id}/delete', [UserController::class, 'destroyStaff'])->name('staff.destroy')->whereNumber('id');

    // Users (administrators).
    Route::get('/users', [UserController::class, 'index'])->name('users.index');
    Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
    Route::post('/users', [UserController::class, 'store'])->name('users.store');
    Route::get('/users/{id}/edit', [UserController::class, 'edit'])->name('users.edit')->whereNumber('id');
    Route::post('/users/{id}', [UserController::class, 'update'])->name('users.update')->whereNumber('id');
    Route::post('/users/{id}/delete', [UserController::class, 'destroy'])->name('users.destroy')->whereNumber('id');

    // Players (profile + statistics + physical/mental tracking).
    Route::get('/players', [PlayerController::class, 'index'])->name('players.index');
    Route::get('/players/create', [PlayerController::class, 'create'])->name('players.create');
    Route::post('/players', [PlayerController::class, 'store'])->name('players.store');
    Route::get('/players/{id}', [PlayerController::class, 'show'])->name('players.show')->whereNumber('id');
    Route::get('/players/{id}/edit', [PlayerController::class, 'edit'])->name('players.edit')->whereNumber('id');
    Route::post('/players/{id}', [PlayerController::class, 'update'])->name('players.update')->whereNumber('id');
    Route::post('/players/{id}/delete', [PlayerController::class, 'destroy'])->name('players.destroy')->whereNumber('id');
    Route::post('/players/{id}/stats', [PlayerController::class, 'updateStats'])->name('players.stats')->whereNumber('id');
    Route::post('/players/{id}/photo', [PlayerController::class, 'uploadPhoto'])->name('players.photo')->whereNumber('id');
    Route::post('/players/{id}/metrics', [PlayerController::class, 'storeMetric'])->name('players.metrics.store')->whereNumber('id');
    Route::post('/players/{id}/welfare', [PlayerController::class, 'storeWelfare'])->name('players.welfare.store')->whereNumber('id');
    Route::post('/metrics/{id}/delete', [PlayerController::class, 'destroyMetric'])->name('metrics.destroy')->whereNumber('id');
    Route::post('/welfare/{id}/delete', [PlayerController::class, 'destroyWelfare'])->name('welfare.destroy')->whereNumber('id');

    // Calendar + events.
    Route::get('/calendar', [CalendarController::class, 'index'])->name('calendar.index');
    Route::get('/events/create', [CalendarController::class, 'create'])->name('events.create');
    Route::post('/events', [CalendarController::class, 'store'])->name('events.store');
    Route::get('/events/{id}/edit', [CalendarController::class, 'edit'])->name('events.edit')->whereNumber('id');
    Route::post('/events/{id}', [CalendarController::class, 'update'])->name('events.update')->whereNumber('id');
    Route::post('/events/{id}/delete', [CalendarController::class, 'destroy'])->name('events.destroy')->whereNumber('id');

    // Training attendance (player check-in with photo + staff roster).
    Route::get('/events/{id}/attendance', [AttendanceController::class, 'roster'])->name('attendance.roster')->whereNumber('id');
    Route::post('/events/{id}/attendance', [AttendanceController::class, 'store'])->name('attendance.store')->whereNumber('id');
    Route::post('/events/{id}/attendance/{playerId}', [AttendanceController::class, 'update'])->name('attendance.update')->whereNumber('id')->whereNumber('playerId');

    // Chat (team rooms + admin-created group rooms).
    Route::get('/chat', [ChatController::class, 'index'])->name('chat.index');
    Route::get('/chat/create', [ChatController::class, 'create'])->name('chat.create');
    Route::post('/chat', [ChatController::class, 'store'])->name('chat.store');
    Route::get('/chat/{id}/edit', [ChatController::class, 'edit'])->name('chat.edit')->whereNumber('id');
    Route::post('/chat/{id}/update', [ChatController::class, 'update'])->name('chat.update')->whereNumber('id');
    Route::get('/chat/{id}/messages', [ChatController::class, 'messages'])->name('chat.messages')->whereNumber('id');
    Route::post('/chat/{id}', [ChatController::class, 'post'])->name('chat.post')->whereNumber('id');
    Route::get('/chat/{id}', [ChatController::class, 'show'])->name('chat.show')->whereNumber('id');

    // Tournaments.
    Route::get('/tournaments', [TournamentController::class, 'index'])->name('tournaments.index');
    Route::post('/tournaments', [TournamentController::class, 'store'])->name('tournaments.store');
    Route::get('/tournaments/{id}', [TournamentController::class, 'show'])->name('tournaments.show')->whereNumber('id');
    Route::get('/tournaments/{id}/edit', [TournamentController::class, 'edit'])->name('tournaments.edit')->whereNumber('id');
    Route::post('/tournaments/{id}', [TournamentController::class, 'update'])->name('tournaments.update')->whereNumber('id');
    Route::post('/tournaments/{id}/delete', [TournamentController::class, 'destroy'])->name('tournaments.destroy')->whereNumber('id');

    // Matches.
    Route::get('/matches', [MatchController::class, 'index'])->name('matches.index');
    Route::get('/matches/create', [MatchController::class, 'create'])->name('matches.create');
    Route::post('/matches', [MatchController::class, 'store'])->name('matches.store');
    Route::get('/matches/{id}/edit', [MatchController::class, 'edit'])->name('matches.edit')->whereNumber('id');
    Route::post('/matches/{id}', [MatchController::class, 'update'])->name('matches.update')->whereNumber('id');
    Route::post('/matches/{id}/delete', [MatchController::class, 'destroy'])->name('matches.destroy')->whereNumber('id');

    // Standings.
    Route::get('/standings', [StandingController::class, 'index'])->name('standings.index');
    Route::get('/standings/{id}', [StandingController::class, 'show'])->name('standings.show')->whereNumber('id');
});
