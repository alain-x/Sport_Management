<?php

/**
 * Port of smoke_v3.ps1 (60 checks) to Laravel feature tests.
 *
 * Section SETUP: admin creates a team-2 training event (cross-team checks).
 * Section P: player scoping (james@sport.test => team 1, player 1).
 * Section S: staff scoping (manager@sport.test => team 1).
 * Section A: administrator capabilities.
 */

use App\Models\Attendance;
use App\Models\ChatRoom;
use App\Models\EventModel;
use App\Models\Player;
use App\Models\User;

// =====================================================================
// SETUP
// =====================================================================

test('SETUP admin creates a team-2 training event', function () {
    $r = $this->actingAs(admin())->post('/events', [
        'title' => 'V3 Away Training',
        'type' => 'training',
        'event_date' => '2026-11-02',
        'event_time' => '09:00',
        'team_id' => '2',
    ]);

    $r->assertRedirect();
    $event = EventModel::where('title', 'V3 Away Training')->firstOrFail();
    expect((int) $event->team_id)->toBe(2);
    expect($r->headers->get('Location'))->toContain('#event-'.$event->id);
});

// =====================================================================
// SECTION P - Player
// =====================================================================

test('P01 player login shows the portal', function () {
    $this->actingAs(playerUser())->get('/')
        ->assertStatus(200)
        ->assertSee('My Portal');
});

test('P02 the portal shows the player own name', function () {
    $this->actingAs(playerUser())->get('/')->assertSee('James Carter');
});

test('P03 the player navigation is lean (Performance/Calendar/Chat)', function () {
    $content = $this->actingAs(playerUser())->get('/')->getContent();

    expect($content)->toContain('My Performance')
        ->and($content)->toContain('My Calendar')
        ->and($content)->toContain('Chat');
});

test('P04 the player navigation hides staff/admin links', function () {
    $content = $this->actingAs(playerUser())->get('/')->getContent();

    expect($content)->not->toContain('href="/players"')
        ->and($content)->not->toContain('href="/users"')
        ->and($content)->not->toContain('href="/tournaments"');
});

test('P05 own performance page renders with private tabs', function () {
    $this->actingAs(playerUser())->get('/players/1')
        ->assertStatus(200)
        ->assertSee('bi-activity', false);
});

test('P06 own performance page has no edit control', function () {
    expect($this->actingAs(playerUser())->get('/players/1')->getContent())
        ->not->toContain('/players/1/edit');
});

test('P07 own performance page has no photo upload form', function () {
    expect($this->actingAs(playerUser())->get('/players/1')->getContent())
        ->not->toContain('/players/1/photo');
});

test('P08 another team player is not visible at all', function () {
    // Player 4 (Noah Bennett) belongs to Iron Lions; James is on Thunder FC,
    // so the record is treated as non-existent rather than merely hidden.
    $this->actingAs(playerUser())->get('/players/4')->assertStatus(404);
});

test('P08b a team-mate public profile is visible but private tabs stay hidden', function () {
    // Player 2 (Liam Harrison) is a Thunder FC team-mate of James.
    $response = $this->actingAs(playerUser())->get('/players/2');

    $response->assertStatus(200);
    expect($response->getContent())->not->toContain('bi-activity');
});

test('P09 /users is forbidden for a player', function () {
    $this->actingAs(playerUser())->get('/users')->assertStatus(403);
});

test('P10 the players list is forbidden for a player', function () {
    $this->actingAs(playerUser())->get('/players')->assertStatus(403);
});

test('P11 /players/create is forbidden for a player', function () {
    $this->actingAs(playerUser())->get('/players/create')->assertStatus(403);
});

test('P12 /events/create is forbidden for a player', function () {
    $this->actingAs(playerUser())->get('/events/create')->assertStatus(403);
});

test('P13 /matches/create is forbidden for a player', function () {
    $this->actingAs(playerUser())->get('/matches/create')->assertStatus(403);
});

test('P14 tournament creation is forbidden for a player', function () {
    $this->actingAs(playerUser())->post('/tournaments', [
        'name' => 'Player Cup',
        'sport' => 'Football',
        'start_date' => '2026-12-01',
        'end_date' => '2026-12-05',
    ])->assertStatus(403);
});

test('P15 /teams/1/staff is forbidden for a player', function () {
    $this->actingAs(playerUser())->get('/teams/1/staff')->assertStatus(403);
});

test('P16 a player can check in with a photo on their own session', function () {
    $r = $this->actingAs(playerUser())->post('/events/7/attendance', [
        'status' => 'present',
        'notes' => 'v3 smoke checkin',
        'photo' => pngUpload(),
    ]);

    $r->assertRedirect();
});

test('P17 the check-in is stored as present with a photo', function () {
    $this->actingAs(playerUser())->post('/events/7/attendance', [
        'status' => 'present',
        'notes' => 'v3 smoke checkin',
        'photo' => pngUpload(),
    ])->assertRedirect();

    $row = Attendance::where('event_id', 7)->where('player_id', 1)->firstOrFail();
    expect($row->status)->toBe('present')
        ->and((string) $row->photo)->toMatch('#^attendance/#');
});

test('P18 a player cannot view the staff roster', function () {
    $this->actingAs(playerUser())->get('/events/7/attendance')->assertStatus(403);
});

test('P19 checking in to another team session is forbidden', function () {
    $away = EventModel::create([
        'title' => 'V3 Away Training',
        'type' => 'training',
        'starts_at' => '2026-11-02 09:00:00',
        'team_id' => 2,
    ]);

    $this->actingAs(playerUser())->post('/events/'.$away->id.'/attendance', [
        'status' => 'present',
        'photo' => pngUpload(),
    ])->assertStatus(403);
});

test('P20 the portal reflects the recorded attendance', function () {
    $this->actingAs(playerUser())->post('/events/7/attendance', [
        'status' => 'present',
        'notes' => 'v3 smoke checkin',
        'photo' => pngUpload(),
    ])->assertRedirect();

    $content = $this->actingAs(playerUser())->get('/')->getContent();
    expect($content)->toMatch('#Attendance recorded|Present#');
});

test('P21 a player can open their own team chat', function () {
    $this->actingAs(playerUser())->get('/chat/8')->assertStatus(200);
});

test('P22 a player cannot open a group they are not a member of', function () {
    $this->actingAs(playerUser())->get('/chat/16')->assertStatus(404);
});

test('P23 a player cannot post to a group they are not a member of', function () {
    $this->actingAs(playerUser())->post('/chat/16', ['body' => 'nope'])->assertStatus(404);
});

test('P24 a player cannot open the group-create form', function () {
    $this->actingAs(playerUser())->get('/chat/create')->assertStatus(403);
});

test('P25 a player cannot store a group chat', function () {
    $this->actingAs(playerUser())->post('/chat', ['name' => 'Player Hack Group'])->assertStatus(403);
});

// =====================================================================
// SECTION S - Staff
// =====================================================================

test('S01 the staff attendance roster renders', function () {
    $this->actingAs(manager())->get('/events/7/attendance')
        ->assertStatus(200)
        ->assertSee('Squad attendance');
});

test('S02 the roster lists squad players', function () {
    $this->actingAs(manager())->get('/events/7/attendance')->assertSee('James Carter');
});

test('S03 the roster shows the check-in photo thumbnail', function () {
    $this->actingAs(playerUser())->post('/events/7/attendance', [
        'status' => 'present',
        'photo' => pngUpload(),
    ])->assertRedirect();

    $this->actingAs(manager())->get('/events/7/attendance')->assertSee('uploads/attendance/');
});

test('S04 staff can set an attendance status', function () {
    $this->actingAs(manager())->post('/events/7/attendance/1', ['status' => 'late'])
        ->assertRedirect();
});

test('S05 the roster shows the updated status', function () {
    $this->actingAs(manager())->post('/events/7/attendance/1', ['status' => 'late'])->assertRedirect();

    $this->actingAs(manager())->get('/events/7/attendance')->assertSee('Late');
});

test('S06 staff cannot use the player check-in endpoint', function () {
    $this->actingAs(manager())->post('/events/7/attendance', [
        'status' => 'present',
        'photo' => pngUpload(),
    ])->assertStatus(403);
});

test('S07 another team roster is forbidden', function () {
    $this->actingAs(manager())->get('/events/4/attendance')->assertStatus(403);
});

test('S08 staff can open their own team chat', function () {
    $this->actingAs(manager())->get('/chat/8')->assertStatus(200);
});

test('S09 staff can open a group they belong to', function () {
    $this->actingAs(manager())->get('/chat/16')->assertStatus(200);
});

test('S10 staff cannot open the group-create form', function () {
    $this->actingAs(manager())->get('/chat/create')->assertStatus(403);
});

test('S11 staff cannot store a group chat', function () {
    $this->actingAs(manager())->post('/chat', ['name' => 'Staff Hack Group'])->assertStatus(403);
});

test('S12 the staff calendar links to training attendance', function () {
    $this->actingAs(manager())->get('/calendar')->assertSee('/events/7/attendance');
});

test('S13 staff creates a player with a login', function () {
    $r = $this->actingAs(manager())->post('/players', [
        'first_name' => 'V3',
        'last_name' => 'Player',
        'team_id' => '1',
        'position' => 'Forward',
        'create_login' => '1',
        'login_email' => 'v3.player@sport.test',
        'login_password' => 'secret123',
    ]);

    $r->assertRedirect();
    expect(Player::where('first_name', 'V3')->where('last_name', 'Player')->exists())->toBeTrue();
});

test('S14 the player login is linked to the right role/team/player', function () {
    $this->actingAs(manager())->post('/players', [
        'first_name' => 'V3',
        'last_name' => 'Player',
        'team_id' => '1',
        'create_login' => '1',
        'login_email' => 'v3.player@sport.test',
        'login_password' => 'secret123',
    ])->assertRedirect();

    $player = Player::where('first_name', 'V3')->where('last_name', 'Player')->firstOrFail();
    $user = User::where('email', 'v3.player@sport.test')->firstOrFail();

    expect($user->role.'|'.$user->team_id.'|'.$user->player_id)
        ->toBe('player|1|'.$player->id);
});

test('S15 a duplicate login email blocks player creation', function () {
    $before = Player::where('first_name', 'Dup')->count();

    $this->actingAs(manager())->post('/players', [
        'first_name' => 'Dup',
        'last_name' => 'V3',
        'team_id' => '1',
        'create_login' => '1',
        'login_email' => 'james@sport.test',
        'login_password' => 'secret123',
    ])->assertSessionHasErrors();

    expect(Player::where('first_name', 'Dup')->count())->toBe($before);
});

test('S15b a duplicate email re-shows the form with the submitted value', function () {
    $this->actingAs(manager())->post('/players', [
        'first_name' => 'Dup',
        'last_name' => 'V3',
        'team_id' => '1',
        'create_login' => '1',
        'login_email' => 'james@sport.test',
        'login_password' => 'secret123',
    ])->assertSessionHasErrors();

    $this->actingAs(manager())->get('/players/create')
        ->assertSee('value="james@sport.test"', false);
});

test('S16 staff is still blocked from /users', function () {
    $this->actingAs(manager())->get('/users')->assertStatus(403);
});

// =====================================================================
// SECTION A - Administrator
// =====================================================================

test('A01 the admin group form renders', function () {
    $this->actingAs(admin())->get('/chat/create')->assertStatus(200);
});

test('A02 an admin creates a group chat', function () {
    $r = $this->actingAs(admin())->post('/chat', [
        'name' => 'V3 Group',
        'members' => ['5'],
    ]);

    $r->assertRedirect();
    $room = ChatRoom::where('name', 'V3 Group')->firstOrFail();
    expect($r->headers->get('Location'))->toContain('/chat/'.$room->id);
});

test('A03 the new group appears in the admin chat list', function () {
    $this->actingAs(admin())->post('/chat', ['name' => 'V3 Group', 'members' => ['5']])->assertRedirect();

    $this->actingAs(admin())->get('/chat')->assertSee('V3 Group');
});

test('A04 the admin opens the group', function () {
    $room = ChatRoom::create(['name' => 'V3 Group', 'type' => 'group', 'created_by' => 1]);

    $this->actingAs(admin())->get('/chat/'.$room->id)->assertStatus(200);
});

test('A05 non-member staff cannot open the group', function () {
    $room = ChatRoom::create(['name' => 'V3 Group', 'type' => 'group', 'created_by' => 1]);

    $this->actingAs(manager())->get('/chat/'.$room->id)->assertStatus(404);
});

test('A06 a member player can open the group', function () {
    $room = ChatRoom::create(['name' => 'V3 Group', 'type' => 'group', 'created_by' => 1]);
    $room->members()->attach(5);

    $this->actingAs(playerUser())->get('/chat/'.$room->id)->assertStatus(200);
});

test('A07 a member posts a group message', function () {
    $room = ChatRoom::create(['name' => 'V3 Group', 'type' => 'group', 'created_by' => 1]);
    $room->members()->attach(5);

    $this->actingAs(playerUser())->post('/chat/'.$room->id, ['body' => 'v3 group message'])
        ->assertRedirect();
});

test('A08 the JSON feed returns the new message', function () {
    $room = ChatRoom::create(['name' => 'V3 Group', 'type' => 'group', 'created_by' => 1]);
    $room->members()->attach(5);

    $this->actingAs(playerUser())->post('/chat/'.$room->id, ['body' => 'v3 group message'])->assertRedirect();

    $this->actingAs(playerUser())->get('/chat/'.$room->id.'/messages?after=0')
        ->assertStatus(200)
        ->assertSee('v3 group message');
});

test('A09 the admin group edit form renders', function () {
    $room = ChatRoom::create(['name' => 'V3 Group', 'type' => 'group', 'created_by' => 1]);

    $this->actingAs(admin())->get('/chat/'.$room->id.'/edit')->assertStatus(200);
});

test('A10 the admin renames the group', function () {
    $room = ChatRoom::create(['name' => 'V3 Group', 'type' => 'group', 'created_by' => 1]);

    $this->actingAs(admin())->post('/chat/'.$room->id.'/update', [
        'name' => 'V3 Group Renamed',
        'members' => ['5'],
    ])->assertRedirect();
});

test('A11 the renamed group shows in the admin chat list', function () {
    $room = ChatRoom::create(['name' => 'V3 Group', 'type' => 'group', 'created_by' => 1]);

    $this->actingAs(admin())->post('/chat/'.$room->id.'/update', [
        'name' => 'V3 Group Renamed',
        'members' => ['5'],
    ])->assertRedirect();

    $this->actingAs(admin())->get('/chat')->assertSee('V3 Group Renamed');
});

test('A12 the admin users list includes the player login', function () {
    $this->actingAs(admin())->get('/users')
        ->assertStatus(200)
        ->assertSee('james@sport.test');
});

test('A13 the player account edit form renders', function () {
    $this->actingAs(admin())->get('/users/5/edit')
        ->assertStatus(200)
        ->assertSee('Player login');
});

test('A14 the admin updates the player account', function () {
    $this->actingAs(admin())->post('/users/5', [
        'name' => 'James Carter',
        'email' => 'james@sport.test',
        'phone' => '07123456789',
    ])->assertRedirect();
});

test('A15 the player account phone is updated', function () {
    $this->actingAs(admin())->post('/users/5', [
        'name' => 'James Carter',
        'email' => 'james@sport.test',
        'phone' => '07123456789',
    ])->assertRedirect();

    expect(User::find(5)->phone)->toBe('07123456789');
});

test('A16 the admin can view any team roster', function () {
    $away = EventModel::create([
        'title' => 'V3 Away Training',
        'type' => 'training',
        'starts_at' => '2026-11-02 09:00:00',
        'team_id' => 2,
    ]);

    $this->actingAs(admin())->get('/events/'.$away->id.'/attendance')->assertStatus(200);
});

test('A17 the admin sees private tabs on any player', function () {
    $this->actingAs(admin())->get('/players/4')->assertSee('bi-activity', false);
});
