<?php

declare(strict_types=1);

/**
 * Application routes. Static paths (e.g. /teams/create) must be registered
 * before parameterised ones (e.g. /teams/{id}).
 *
 * Role/team gates (admin-only, team-scoped) live inside the controllers.
 */

use App\Controllers\AuthController;
use App\Controllers\CalendarController;
use App\Controllers\DashboardController;
use App\Controllers\MatchController;
use App\Controllers\PlayerController;
use App\Controllers\StandingController;
use App\Controllers\TeamController;
use App\Controllers\TournamentController;
use App\Controllers\UserController;

// Guests only.
$router->get('/login', [AuthController::class, 'showLogin'], false);
$router->post('/login', [AuthController::class, 'login'], false);
$router->post('/logout', [AuthController::class, 'logout']);

// Dashboard.
$router->get('/', [DashboardController::class, 'index']);

// Teams.
$router->get('/teams', [TeamController::class, 'index']);
$router->get('/teams/create', [TeamController::class, 'create']);
$router->post('/teams', [TeamController::class, 'store']);
$router->get('/teams/{id}', [TeamController::class, 'show']);
$router->get('/teams/{id}/edit', [TeamController::class, 'edit']);
$router->post('/teams/{id}', [TeamController::class, 'update']);
$router->post('/teams/{id}/delete', [TeamController::class, 'destroy']);

// Team staff.
$router->get('/teams/{id}/staff', [TeamController::class, 'staff']);
$router->post('/teams/{id}/staff', [TeamController::class, 'storeStaff']);
$router->get('/staff/{id}/edit', [UserController::class, 'editStaff']);
$router->post('/staff/{id}', [UserController::class, 'updateStaff']);
$router->post('/staff/{id}/delete', [UserController::class, 'destroyStaff']);

// Users (administrators).
$router->get('/users', [UserController::class, 'index']);
$router->get('/users/create', [UserController::class, 'create']);
$router->post('/users', [UserController::class, 'store']);
$router->get('/users/{id}/edit', [UserController::class, 'edit']);
$router->post('/users/{id}', [UserController::class, 'update']);
$router->post('/users/{id}/delete', [UserController::class, 'destroy']);

// Players (profile + statistics + physical/mental tracking).
$router->get('/players', [PlayerController::class, 'index']);
$router->get('/players/create', [PlayerController::class, 'create']);
$router->post('/players', [PlayerController::class, 'store']);
$router->get('/players/{id}', [PlayerController::class, 'show']);
$router->get('/players/{id}/edit', [PlayerController::class, 'edit']);
$router->post('/players/{id}', [PlayerController::class, 'update']);
$router->post('/players/{id}/delete', [PlayerController::class, 'destroy']);
$router->post('/players/{id}/stats', [PlayerController::class, 'updateStats']);
$router->post('/players/{id}/photo', [PlayerController::class, 'uploadPhoto']);
$router->post('/players/{id}/metrics', [PlayerController::class, 'storeMetric']);
$router->post('/players/{id}/welfare', [PlayerController::class, 'storeWelfare']);
$router->post('/metrics/{id}/delete', [PlayerController::class, 'destroyMetric']);
$router->post('/welfare/{id}/delete', [PlayerController::class, 'destroyWelfare']);

// Calendar + events.
$router->get('/calendar', [CalendarController::class, 'index']);
$router->get('/events/create', [CalendarController::class, 'create']);
$router->post('/events', [CalendarController::class, 'store']);
$router->get('/events/{id}/edit', [CalendarController::class, 'edit']);
$router->post('/events/{id}', [CalendarController::class, 'update']);
$router->post('/events/{id}/delete', [CalendarController::class, 'destroy']);

// Tournaments.
$router->get('/tournaments', [TournamentController::class, 'index']);
$router->post('/tournaments', [TournamentController::class, 'store']);
$router->get('/tournaments/{id}', [TournamentController::class, 'show']);
$router->get('/tournaments/{id}/edit', [TournamentController::class, 'edit']);
$router->post('/tournaments/{id}', [TournamentController::class, 'update']);
$router->post('/tournaments/{id}/delete', [TournamentController::class, 'destroy']);

// Matches.
$router->get('/matches', [MatchController::class, 'index']);
$router->get('/matches/create', [MatchController::class, 'create']);
$router->post('/matches', [MatchController::class, 'store']);
$router->get('/matches/{id}/edit', [MatchController::class, 'edit']);
$router->post('/matches/{id}', [MatchController::class, 'update']);
$router->post('/matches/{id}/delete', [MatchController::class, 'destroy']);

// Standings.
$router->get('/standings', [StandingController::class, 'index']);
$router->get('/standings/{id}', [StandingController::class, 'show']);
