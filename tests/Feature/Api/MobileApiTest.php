<?php

declare(strict_types=1);

use App\Models\User;

/*
|--------------------------------------------------------------------------
| Mobile API (Sanctum token) feature tests
|--------------------------------------------------------------------------
|
| Exercises the JSON API in routes/api.php exactly the way the Flutter app
| does: POST /api/login to obtain a bearer token, then send that token on
| every subsequent request. The database is the demo seed
| (database/seed_demo.sql), loaded into sport_management_test.
|
*/

/** Log in over the real endpoint and return the issued bearer token. */
function apiLogin(string $email, string $password, string $device = 'phpunit'): string
{
    $response = test()->postJson('/api/login', [
        'email' => $email,
        'password' => $password,
        'device_name' => $device,
    ]);

    $response->assertOk();

    return $response->json('token');
}

/** Standard bearer header for an authenticated request. */
function bearer(string $token): array
{
    return ['Authorization' => 'Bearer '.$token];
}

// ---------------------------------------------------------------------------
// Authentication
// ---------------------------------------------------------------------------

it('issues a token for valid credentials', function () {
    $response = $this->postJson('/api/login', [
        'email' => 'admin@sport.test',
        'password' => 'password',
        'device_name' => 'phpunit',
    ]);

    $response->assertOk()
        ->assertJsonStructure(['token', 'token_type', 'user' => ['id', 'name', 'email', 'role']])
        ->assertJsonPath('token_type', 'Bearer')
        ->assertJsonPath('user.role', 'admin');

    expect($response->json('token'))->toBeString()->not->toBeEmpty();
});

it('rejects a wrong password with a 422 and a field error', function () {
    $this->postJson('/api/login', [
        'email' => 'admin@sport.test',
        'password' => 'definitely-wrong',
    ])
        ->assertStatus(422)
        ->assertJsonStructure(['message', 'errors' => ['email']]);
});

it('rejects an unknown email', function () {
    $this->postJson('/api/login', [
        'email' => 'nobody@example.com',
        'password' => 'password',
    ])->assertStatus(422);
});

it('validates the login payload', function () {
    $this->postJson('/api/login', ['email' => 'not-an-email'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['email', 'password']);
});

// ---------------------------------------------------------------------------
// Token protection
// ---------------------------------------------------------------------------

it('rejects requests without a token', function () {
    $this->getJson('/api/dashboard')->assertStatus(401);
    $this->getJson('/api/teams')->assertStatus(401);
    $this->getJson('/api/players')->assertStatus(401);
    $this->getJson('/api/me')->assertStatus(401);
});

it('rejects a bogus token', function () {
    $this->getJson('/api/me', bearer('not-a-real-token'))->assertStatus(401);
});

it('returns the signed-in user for /me', function () {
    $token = apiLogin('admin@sport.test', 'password');

    $this->getJson('/api/me', bearer($token))
        ->assertOk()
        ->assertJsonPath('data.email', 'admin@sport.test')
        ->assertJsonPath('data.role', 'admin');
});

it('revokes the token on logout', function () {
    $token = apiLogin('admin@sport.test', 'password');

    // Sanctum stores the hashed token; only its prefix is exposed in the API.
    $tokenId = (int) explode('|', $token)[0];

    $this->postJson('/api/logout', [], bearer($token))
        ->assertOk()
        ->assertJsonPath('message', 'Signed out successfully.');

    // The personal access token row is gone, so the token cannot be reused
    // by any later request (e.g. the next app launch).
    expect(\Laravel\Sanctum\PersonalAccessToken::find($tokenId))->toBeNull();
});

// ---------------------------------------------------------------------------
// Dashboard (role aware)
// ---------------------------------------------------------------------------

it('returns the admin dashboard with counts', function () {
    $token = apiLogin('admin@sport.test', 'password');

    $response = $this->getJson('/api/dashboard', bearer($token))->assertOk();
    $response->assertJsonPath('data.role', 'admin')
        ->assertJsonStructure(['data' => ['stats' => ['teams', 'players', 'tournaments', 'matches']]]);

    expect($response->json('data.stats.teams'))->toBeGreaterThan(0);
});

it('returns the player dashboard scoped to the player', function () {
    $token = apiLogin('james@sport.test', 'password');

    $this->getJson('/api/dashboard', bearer($token))
        ->assertOk()
        ->assertJsonPath('data.role', 'player')
        ->assertJsonStructure(['data' => ['player', 'team', 'attendance_summary']]);
});

it('returns the manager dashboard for a team', function () {
    $token = apiLogin('irl.staff@sport.test', 'password');

    $this->getJson('/api/dashboard', bearer($token))
        ->assertOk()
        ->assertJsonPath('data.role', 'manager')
        ->assertJsonStructure(['data' => ['team', 'recent_matches', 'upcoming_matches']]);
});

// ---------------------------------------------------------------------------
// Teams
// ---------------------------------------------------------------------------

it('lists teams for an admin', function () {
    $token = apiLogin('admin@sport.test', 'password');

    $response = $this->getJson('/api/teams', bearer($token))->assertOk();
    $response->assertJsonStructure(['data' => ['items', 'meta' => ['total', 'page', 'per_page']]]);

    expect($response->json('data.items'))->not->toBeEmpty();
});

it('restricts a manager to their own team', function () {
    $token = apiLogin('irl.staff@sport.test', 'password');
    $user = User::where('email', 'irl.staff@sport.test')->firstOrFail();

    $items = $this->getJson('/api/teams', bearer($token))->assertOk()->json('data.items');

    foreach ($items as $team) {
        expect((int) $team['id'])->toBe((int) $user->team_id);
    }
});

it('returns a team with its squad', function () {
    $token = apiLogin('admin@sport.test', 'password');
    $teamId = (int) \App\Models\Team::query()->value('id');

    $this->getJson("/api/teams/{$teamId}", bearer($token))
        ->assertOk()
        ->assertJsonStructure(['data' => ['id', 'name', 'players']]);
});

it('404s for a missing team', function () {
    $token = apiLogin('admin@sport.test', 'password');

    $this->getJson('/api/teams/999999', bearer($token))->assertStatus(404);
});

it('404s when a manager opens another team', function () {
    $token = apiLogin('irl.staff@sport.test', 'password');
    $user = User::where('email', 'irl.staff@sport.test')->firstOrFail();

    $otherTeamId = (int) \App\Models\Team::query()
        ->where('id', '!=', $user->team_id)
        ->value('id');

    if ($otherTeamId !== 0) {
        $this->getJson("/api/teams/{$otherTeamId}", bearer($token))->assertStatus(404);
    }
});

// ---------------------------------------------------------------------------
// Players
// ---------------------------------------------------------------------------

it('lists players for an admin', function () {
    $token = apiLogin('admin@sport.test', 'password');

    $response = $this->getJson('/api/players', bearer($token))->assertOk();
    $response->assertJsonStructure(['data' => ['items', 'meta']]);

    expect($response->json('data.items'))->not->toBeEmpty();
});

it('limits a player to their own team roster', function () {
    $token = apiLogin('james@sport.test', 'password');
    $user = User::where('email', 'james@sport.test')->firstOrFail();

    $items = $this->getJson('/api/players', bearer($token))->assertOk()->json('data.items');

    foreach ($items as $player) {
        expect((int) $player['team_id'])->toBe((int) $user->team_id);
    }
});

it('lets a player see their own private metrics', function () {
    $token = apiLogin('james@sport.test', 'password');
    $user = User::where('email', 'james@sport.test')->firstOrFail();

    $this->getJson("/api/players/{$user->player_id}", bearer($token))
        ->assertOk()
        ->assertJsonPath('data.is_self', true)
        ->assertJsonPath('data.can_see_private', true);
});

it('hides private data from a team-mate', function () {
    $token = apiLogin('james@sport.test', 'password');
    $user = User::where('email', 'james@sport.test')->firstOrFail();

    $teamMate = \App\Models\Player::query()
        ->where('team_id', $user->team_id)
        ->where('id', '!=', $user->player_id)
        ->first();

    if ($teamMate !== null) {
        $this->getJson("/api/players/{$teamMate->id}", bearer($token))
            ->assertOk()
            ->assertJsonPath('data.is_self', false)
            ->assertJsonPath('data.can_see_private', false);
    }
});

it('404s when a player opens another team player', function () {
    $token = apiLogin('james@sport.test', 'password');
    $user = User::where('email', 'james@sport.test')->firstOrFail();

    $other = \App\Models\Player::query()
        ->where('team_id', '!=', $user->team_id)
        ->first();

    if ($other !== null) {
        $this->getJson("/api/players/{$other->id}", bearer($token))->assertStatus(404);
    }
});

// ---------------------------------------------------------------------------
// Tournaments, standings, matches
// ---------------------------------------------------------------------------

it('lists tournaments and returns a standings table', function () {
    $token = apiLogin('admin@sport.test', 'password');

    $this->getJson('/api/tournaments', bearer($token))
        ->assertOk()
        ->assertJsonStructure(['data' => ['items']]);

    $this->getJson('/api/standings', bearer($token))
        ->assertOk()
        ->assertJsonStructure(['data' => ['tournament', 'standings']]);
});

it('returns standings for a specific tournament', function () {
    $token = apiLogin('admin@sport.test', 'password');
    $tournamentId = (int) \App\Models\Tournament::query()->value('id');

    if ($tournamentId !== 0) {
        $this->getJson("/api/tournaments/{$tournamentId}/standings", bearer($token))
            ->assertOk()
            ->assertJsonStructure(['data' => ['tournament', 'standings']]);
    }
});

it('lists matches and returns a single match', function () {
    $token = apiLogin('admin@sport.test', 'password');

    $matches = $this->getJson('/api/matches', bearer($token))
        ->assertOk()
        ->assertJsonStructure(['data' => ['items', 'meta']])
        ->json('data.items');

    expect($matches)->not->toBeEmpty();

    $this->getJson("/api/matches/{$matches[0]['id']}", bearer($token))
        ->assertOk()
        ->assertJsonStructure(['data' => ['id', 'home_name', 'away_name']]);
});

it('hides matches that do not involve a player team', function () {
    $token = apiLogin('james@sport.test', 'password');
    $user = User::where('email', 'james@sport.test')->firstOrFail();

    $items = $this->getJson('/api/matches', bearer($token))->assertOk()->json('data.items');

    foreach ($items as $match) {
        $involves = (int) $match['home_team_id'] === (int) $user->team_id
            || (int) $match['away_team_id'] === (int) $user->team_id;
        expect($involves)->toBeTrue();
    }
});

// ---------------------------------------------------------------------------
// Events + attendance
// ---------------------------------------------------------------------------

it('lists events and hides other teams events from a player', function () {
    $token = apiLogin('james@sport.test', 'password');
    $user = User::where('email', 'james@sport.test')->firstOrFail();

    $items = $this->getJson('/api/events', bearer($token))->assertOk()->json('data.items');

    foreach ($items as $event) {
        $teamId = $event['team_id'] ?? null;
        if ($teamId !== null) {
            expect((int) $teamId)->toBe((int) $user->team_id);
        }
    }
});

it('rejects a staff check-in on the attendance endpoint', function () {
    $token = apiLogin('admin@sport.test', 'password');
    $eventId = (int) \App\Models\EventModel::query()->value('id');

    if ($eventId !== 0) {
        // Admins are not players, so check-in is forbidden for them.
        $this->postJson("/api/events/{$eventId}/attendance", [
            'status' => 'present',
        ], bearer($token))->assertStatus(403);
    }
});

// ---------------------------------------------------------------------------
// Chat
// ---------------------------------------------------------------------------

it('lists chat rooms a player can access', function () {
    $token = apiLogin('james@sport.test', 'password');

    $this->getJson('/api/chat', bearer($token))
        ->assertOk()
        ->assertJsonStructure(['data' => ['items', 'previews']]);
});

it('lets a player post and read back a chat message', function () {
    $token = apiLogin('james@sport.test', 'password');

    $rooms = $this->getJson('/api/chat', bearer($token))->assertOk()->json('data.items');
    expect($rooms)->not->toBeEmpty();

    $roomId = (int) $rooms[0]['id'];

    $this->postJson("/api/chat/{$roomId}", ['body' => 'Hello from phpunit'], bearer($token))
        ->assertStatus(201);

    $messages = $this->getJson("/api/chat/{$roomId}/messages", bearer($token))
        ->assertOk()
        ->json('data.messages');

    expect(collect($messages)->pluck('body'))->toContain('Hello from phpunit');
});

it('rejects an empty chat message', function () {
    $token = apiLogin('james@sport.test', 'password');
    $rooms = $this->getJson('/api/chat', bearer($token))->assertOk()->json('data.items');
    $roomId = (int) $rooms[0]['id'];

    $this->postJson("/api/chat/{$roomId}", ['body' => '   '], bearer($token))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['body']);
});

it('404s for a chat room the player cannot access', function () {
    $token = apiLogin('james@sport.test', 'password');

    $this->getJson('/api/chat/999999/messages', bearer($token))->assertStatus(404);
});
