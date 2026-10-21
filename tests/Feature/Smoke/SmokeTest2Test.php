<?php

/**
 * Port of smoke_test2.ps1 (11 checks) to Laravel feature tests.
 */

use App\Models\MatchModel;
use App\Models\Player;

test('player search finds Carter with the team filter applied', function () {
    $r = $this->actingAs(admin())->get('/players?q=carter&team_id=1');

    $r->assertStatus(200);
    $r->assertSee('James Carter');
});

test('player search renders the empty state', function () {
    $r = $this->actingAs(admin())->get('/players?q=zzzzz');

    $r->assertStatus(200);
    $r->assertSee('No players match your filters');
});

test('team search finds Desert Falcons', function () {
    $this->actingAs(admin())->get('/teams?q=falcons')
        ->assertStatus(200)
        ->assertSee('Desert Falcons');
});

test('a player can be created, shown on the team page and then deleted', function () {
    $this->actingAs(admin())->get('/players/create')->assertStatus(200);

    $r = $this->actingAs(admin())->post('/players', [
        'team_id' => '1',
        'first_name' => 'Temp',
        'last_name' => 'Scorer',
        'jersey_number' => '14',
        'position' => 'Forward',
        'goals' => '1',
        'assists' => '0',
    ]);
    $r->assertRedirect();

    $player = Player::where('last_name', 'Scorer')->firstOrFail();

    $this->actingAs(admin())->get('/players?page=1&q=Scorer')
        ->assertStatus(200)
        ->assertSee('Temp Scorer');

    $this->actingAs(admin())->get('/teams/1')
        ->assertStatus(200)
        ->assertSee('Temp Scorer');

    $del = $this->actingAs(admin())->post('/players/'.$player->id.'/delete');
    $del->assertRedirect('/players');
    expect(session('success'))->toContain('Player deleted');
    expect(Player::find($player->id))->toBeNull();
});

test('a finished match updates the standings and can be deleted', function () {
    $this->actingAs(admin())->get('/matches/create')->assertStatus(200);

    $r = $this->actingAs(admin())->post('/matches', [
        'home_team_id' => '2',
        'away_team_id' => '3',
        'tournament_id' => '1',
        'home_score' => '5',
        'away_score' => '0',
        'status' => 'finished',
        'match_date' => '2026-10-19 18:00',
        'venue' => 'QA Ground',
    ]);
    $r->assertRedirect();

    $match = MatchModel::where('venue', 'QA Ground')->firstOrFail();

    $content = $this->actingAs(admin())->get('/standings/1')->assertStatus(200)->getContent();
    expect($content)->toContain('Iron Lions');

    // Iron Lions started on 1 point; the 5-0 win lifts them to 4.
    preg_match('#(?s)Iron Lions.*?</tr>#', $content, $m);
    expect($m[0])->toContain('<td class="text-center fw-bold fs-6">4</td>');

    $del = $this->actingAs(admin())->post('/matches/'.$match->id.'/delete');
    $del->assertRedirect('/matches');
    expect(session('success'))->toContain('Match deleted');
});

test('the team search page renders', function () {
    $this->actingAs(admin())->get('/teams?q=united')->assertStatus(200);
});
