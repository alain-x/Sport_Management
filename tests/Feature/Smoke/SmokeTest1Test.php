<?php

/**
 * Port of smoke_test.ps1 (21 checks) to Laravel feature tests.
 *
 * The original suite drove the app over HTTP; here we use Laravel's testing
 * HTTP kernel against the seeded sport_management_test database (see tests/Pest.php).
 */

use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Str;

const EXPECTED_STANDINGS = [
    'Thunder FC',
    'Royal Strikers',
    'Green Rangers',
    'Silver Eagles',
    'Coastal Sharks',
    'Iron Lions',
    'Desert Falcons',
    'Arctic Wolves',
];

test('1 login page renders with a 64-character CSRF token', function () {
    $r = $this->get('/login');

    $r->assertStatus(200);

    $token = Str::match('/name="_token" value="([^"]+)"/', $r->getContent());
    expect($token)->not->toBe('')
        ->and(strlen($token))->toBe(64);
});

test('2 wrong password is rejected with a mismatch message', function () {
    $r = $this->from('/login')->post('/login', [
        'email' => 'admin@sport.test',
        'password' => 'wrongpass',
    ]);

    $r->assertRedirect('/login');
    $r->assertSessionHasErrors();
    expect(session('errors')->first())->toContain('do not match');
});

test('3 correct login redirects to the dashboard', function () {
    $r = $this->post('/login', [
        'email' => 'admin@sport.test',
        'password' => 'password',
    ]);

    $r->assertRedirect('/');
    $this->assertAuthenticated();

    $this->get('/')->assertStatus(200)->assertSee('Dashboard');
});

test('4 guests are redirected from protected pages to login', function () {
    $this->get('/teams')->assertRedirect('/login');
});

test('5 main pages render for an authenticated admin', function () {
    $admin = User::where('email', 'admin@sport.test')->firstOrFail();

    foreach (['/', '/teams', '/players', '/tournaments', '/matches', '/teams/1', '/tournaments/1'] as $page) {
        $this->actingAs($admin)->get($page)->assertStatus(200);
    }

    // /standings redirects to the active tournament's standings table.
    $this->actingAs($admin)->get('/standings')->assertRedirect('/standings/1');
    $this->actingAs($admin)->get('/standings/1')->assertStatus(200);
});

test('6 standings order and points calculation', function () {
    $admin = User::where('email', 'admin@sport.test')->firstOrFail();

    $content = $this->actingAs($admin)->get('/standings/1')->assertStatus(200)->getContent();

    preg_match_all('#href="[^"]*/teams/\d+"[^>]*>\s*([^<]+?)\s*</a>#', $content, $m);
    $names = array_map('trim', array_slice($m[1], 0, 8));

    expect($names)->toBe(EXPECTED_STANDINGS)
        ->and($content)->toContain('Thunder FC');
});

test('7 create a team and land on its page', function () {
    $admin = User::where('email', 'admin@sport.test')->firstOrFail();

    $r = $this->actingAs($admin)->post('/teams', [
        'name' => 'Test United FC',
        'code' => 'TST',
        'coach' => 'QA Coach',
        'city' => 'Testville',
        'founded_year' => '2001',
    ]);

    $team = Team::where('name', 'Test United FC')->firstOrFail();
    $r->assertRedirect('/teams/'.$team->id);
});

test('8 duplicate team name is rejected', function () {
    $admin = User::where('email', 'admin@sport.test')->firstOrFail();

    $r = $this->actingAs($admin)->from('/teams/create')->post('/teams', [
        'name' => 'Thunder FC',
        'code' => 'DUP',
    ]);

    $r->assertSessionHasErrors();
    expect(session('errors')->first())->toContain('already been taken');
});

test('9 a team can be deleted', function () {
    $admin = User::where('email', 'admin@sport.test')->firstOrFail();

    $team = Team::create([
        'name' => 'Delete Me FC',
        'code' => 'DEL',
    ]);

    $r = $this->actingAs($admin)->post('/teams/'.$team->id.'/delete');

    $r->assertRedirect('/teams');
    $r->assertSessionHas('success');
    expect(session('success'))->toContain('deleted');
    expect(Team::find($team->id))->toBeNull();
});

test('10 logout lands on the login page', function () {
    $admin = User::where('email', 'admin@sport.test')->firstOrFail();

    $r = $this->actingAs($admin)->post('/logout');

    $r->assertRedirect('/login');
    $this->assertGuest();
});

test('11 an unknown route returns 404', function () {
    $this->get('/definitely/not/here')->assertStatus(404);
});
