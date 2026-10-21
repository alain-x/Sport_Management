<?php

/**
 * Port of smoke_test3.ps1 (36 checks) to Laravel feature tests.
 *
 * Section A: manager scoping (manager@sport.test => Thunder FC, team 1).
 * Section B: administrator capabilities.
 */

use App\Models\EventModel;
use App\Models\Player;
use App\Models\Team;
use App\Models\User;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;

// =====================================================================
// SECTION A - Manager scoping
// =====================================================================

test('A01 manager login shows the team dashboard', function () {
    $mgr = manager();

    $this->actingAs($mgr)->get('/')
        ->assertStatus(200)
        ->assertSee('Thunder FC');
});

test('A02 manager navigation shows My Team', function () {
    $this->actingAs(manager())->get('/')->assertSee('My Team');
});

test('A03 /users is forbidden for a manager', function () {
    $this->actingAs(manager())->get('/users')->assertStatus(403);
});

test('A04 the players list is scoped to the manager own team', function () {
    $content = $this->actingAs(manager())->get('/players')->assertStatus(200)->getContent();

    expect($content)->toContain('James Carter')
        ->and($content)->not->toContain('Noah Bennett');
});

test('A05 a manager sees the Physical tab on their own player', function () {
    $this->actingAs(manager())->get('/players/1')->assertSee('bi-activity', false);
});

test('A06 other-team players hide the Physical/Mental tabs', function () {
    expect($this->actingAs(manager())->get('/players/4')->getContent())->not->toContain('bi-activity');
});

test('A07 editing an other-team player is forbidden', function () {
    $this->actingAs(manager())->get('/players/4/edit')->assertStatus(403);
});

test('A08 deleting an other-team player is forbidden', function () {
    $this->actingAs(manager())->post('/players/4/delete')->assertStatus(403);
});

test('A09 another team staff page is forbidden', function () {
    $this->actingAs(manager())->get('/teams/2/staff')->assertStatus(403);
});

test('A10 the own team staff page is allowed', function () {
    $this->actingAs(manager())->get('/teams/1/staff')->assertStatus(200);
});

test('A11 tournament creation is forbidden for a manager', function () {
    $this->actingAs(manager())->post('/tournaments', [
        'name' => 'Manager Cup',
        'sport' => 'Football',
        'start_date' => '2026-11-01',
        'end_date' => '2026-11-05',
    ])->assertStatus(403);
});

test('A12 a metric is saved and redirects with #physical', function () {
    $mgr = manager();

    $r = $this->actingAs($mgr)->post('/players/1/metrics', [
        'test_date' => '2026-10-19',
        'fitness' => 'excellent',
        'height_cm' => '181',
        'weight_kg' => '76',
        'top_speed' => '32.1',
        'stamina' => '88',
        'notes' => 'smoke metric',
    ]);

    $r->assertRedirect('/players/1#physical');
});

test('A13 posting a metric for an other-team player is forbidden', function () {
    $this->actingAs(manager())->post('/players/4/metrics', [
        'test_date' => '2026-10-19',
        'fitness' => 'good',
    ])->assertStatus(403);
});

test('A14 the saved metric renders on the player page', function () {
    $mgr = manager();

    $this->actingAs($mgr)->post('/players/1/metrics', [
        'test_date' => '2026-10-19',
        'fitness' => 'excellent',
        'notes' => 'smoke metric',
    ])->assertRedirect();

    $this->actingAs($mgr)->get('/players/1')->assertSee('smoke metric');
});

test('A15 welfare is saved and redirects with #mental', function () {
    $r = $this->actingAs(manager())->post('/players/1/welfare', [
        'assessment_date' => '2026-10-19',
        'confidence' => '8',
        'focus' => '7',
        'motivation' => '9',
        'stress' => '3',
        'wellbeing' => 'great',
        'notes' => 'smoke welfare',
    ]);

    $r->assertRedirect('/players/1#mental');
});

test('A16 the saved welfare entry renders on the player page', function () {
    $mgr = manager();

    $this->actingAs($mgr)->post('/players/1/welfare', [
        'assessment_date' => '2026-10-19',
        'wellbeing' => 'great',
        'notes' => 'smoke welfare',
    ])->assertRedirect();

    $this->actingAs($mgr)->get('/players/1')->assertSee('smoke welfare');
});

test('A17 an event is created locked to the manager own team', function () {
    $r = $this->actingAs(manager())->post('/events', [
        'title' => 'Smoke Team Session',
        'type' => 'training',
        'event_date' => '2026-10-22',
        'event_time' => '08:00',
        'team_id' => '1',
    ]);

    $r->assertRedirect();
    expect($r->headers->get('Location'))->toContain('/calendar');

    $event = EventModel::where('title', 'Smoke Team Session')->firstOrFail();
    expect($r->headers->get('Location'))->toContain('#event-'.$event->id);
});

test('A19 a spoofed team_id is forced to the manager own team', function () {
    $this->actingAs(manager())->post('/events', [
        'title' => 'Smoke Spoof Event',
        'type' => 'meeting',
        'event_date' => '2026-10-23',
        'event_time' => '09:00',
        'team_id' => '2',
    ])->assertRedirect();

    $event = EventModel::where('title', 'Smoke Spoof Event')->firstOrFail();
    expect((int) $event->team_id)->toBe(1);

    $this->actingAs(manager())->get('/events/'.$event->id.'/edit')->assertStatus(200);
});

test('A20 the calendar renders the month grid', function () {
    $this->actingAs(manager())->get('/calendar')
        ->assertStatus(200)
        ->assertSee('calendar-grid', false);
});

test('A21 the calendar shows event chips', function () {
    $this->actingAs(manager())->get('/calendar')->assertSee('cal-chip', false);
});

test('A22 a manager schedules a match involving their own team', function () {
    $this->actingAs(manager())->post('/matches', [
        'home_team_id' => '1',
        'away_team_id' => '2',
        'match_date' => '2026-10-28 15:00',
        'venue' => 'Smoke Arena',
        'status' => 'scheduled',
    ])->assertRedirect();
});

test('A23 a match between two other teams is forbidden', function () {
    $this->actingAs(manager())->post('/matches', [
        'home_team_id' => '3',
        'away_team_id' => '4',
        'match_date' => '2026-10-28 15:00',
        'venue' => 'Neutral',
        'status' => 'scheduled',
    ])->assertStatus(403);
});

test('A24 a CSRF mismatch answers with status 419', function () {
    // Feature tests skip CSRF (runningUnitTests()), so we assert that a
    // TokenMismatchException resolves to a 419 response. The live smoke run
    // exercises the real middleware path (bare "419 Session Expired" body).
    $handler = app(ExceptionHandler::class);

    $request = Request::create('/players/1/metrics', 'POST');

    $response = $handler->render($request, new TokenMismatchException('CSRF token mismatch.'));

    expect($response->getStatusCode())->toBe(419);
});

// =====================================================================
// SECTION B - Administrator
// =====================================================================

test('B01 admin login shows the dashboard', function () {
    $this->actingAs(admin())->get('/')->assertSee('Dashboard');
});

test('B02 /users is accessible for an admin', function () {
    $this->actingAs(admin())->get('/users')->assertStatus(200);
});

test('B03 an admin can create a user', function () {
    $r = $this->actingAs(admin())->post('/users', [
        'name' => 'Smoke Manager',
        'email' => 'smoke.manager@sport.test',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'role' => 'manager',
        'team_id' => '3',
    ]);

    $r->assertRedirect('/users');
    expect(User::where('email', 'smoke.manager@sport.test')->exists())->toBeTrue();
});

test('B04 the created user appears in the list', function () {
    User::create([
        'name' => 'Smoke Manager',
        'email' => 'smoke.manager@sport.test',
        'password' => 'password123',
        'role' => 'manager',
        'team_id' => 3,
    ]);

    $this->actingAs(admin())->get('/users')->assertSee('smoke.manager@sport.test');
});

test('B05 an admin creates a team with a logo', function () {
    $r = $this->actingAs(admin())->post('/teams', [
        'name' => 'Smoke Test United',
        'code' => 'SMT',
        'coach' => 'Smoke Coach',
        'city' => 'Smoketon',
        'founded_year' => '2020',
        'logo' => pngUpload(),
    ]);

    $r->assertRedirect();
    $team = Team::where('name', 'Smoke Test United')->firstOrFail();
    expect($r->headers->get('Location'))->toContain('/teams/'.$team->id);
});

test('B06 the team logo renders as an image', function () {
    $this->actingAs(admin())->post('/teams', [
        'name' => 'Smoke Test United',
        'code' => 'SMT',
        'logo' => pngUpload(),
    ])->assertRedirect();

    $team = Team::where('name', 'Smoke Test United')->firstOrFail();

    $content = $this->actingAs(admin())->get('/teams/'.$team->id)->getContent();
    expect($content)->toMatch('#uploads/teams/[a-z0-9_]+\.png#');
});

test('B07 an admin creates a player with a photo', function () {
    $team = Team::create(['name' => 'Smoke FC', 'code' => 'SMK']);

    $r = $this->actingAs(admin())->post('/players', [
        'team_id' => $team->id,
        'first_name' => 'Smoke',
        'last_name' => 'Striker',
        'jersey_number' => '10',
        'position' => 'Forward',
        'goals' => '3',
        'assists' => '2',
        'appearances' => '5',
        'rating' => '7.0',
        'photo' => pngUpload(),
    ]);

    $r->assertRedirect();
    expect($r->headers->get('Location'))->toContain('q=Striker');
});

test('B08 the player photo renders as an image', function () {
    $team = Team::create(['name' => 'Smoke FC', 'code' => 'SMK']);

    $this->actingAs(admin())->post('/players', [
        'team_id' => $team->id,
        'first_name' => 'Smoke',
        'last_name' => 'Striker',
        'photo' => pngUpload(),
    ])->assertRedirect();

    $player = Player::where('last_name', 'Striker')->firstOrFail();

    $content = $this->actingAs(admin())->get('/players/'.$player->id)->getContent();
    expect($content)->toMatch('#uploads/players/[a-z0-9_]+\.png#');
});

test('B09 an admin creates a league-wide event', function () {
    $r = $this->actingAs(admin())->post('/events', [
        'title' => 'Smoke League Day',
        'type' => 'media',
        'event_date' => '2026-10-29',
        'event_time' => '12:00',
        'team_id' => '',
        'description' => 'league-wide smoke event',
    ]);

    $r->assertRedirect();
    expect($r->headers->get('Location'))->toContain('/calendar');
});

test('B10 a league-wide event appears on the calendar', function () {
    $this->actingAs(admin())->post('/events', [
        'title' => 'Smoke League Day',
        'type' => 'media',
        'event_date' => '2026-10-29',
        'event_time' => '12:00',
        'team_id' => '',
    ])->assertRedirect();

    $this->actingAs(admin())->get('/calendar?month=2026-10')->assertSee('Smoke League Day');
});

test('B11 an admin can edit any event', function () {
    $this->actingAs(admin())->get('/events/1/edit')->assertStatus(200);
});

test('B12 an admin can open another team staff page', function () {
    $this->actingAs(admin())->get('/teams/2/staff')->assertStatus(200);
});
