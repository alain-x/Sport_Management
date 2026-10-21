<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\EventModel;
use App\Models\MatchModel;
use App\Models\Player;
use App\Models\PlayerMetric;
use App\Models\PlayerWelfare;
use App\Models\Team;
use App\Models\Tournament;
use App\Models\User;

class DashboardController extends Controller
{
    public function index()
    {
        $user = $this->user();

        if (is_player()) {
            return $this->renderPortal();
        }

        if (is_admin()) {
            return $this->view('dashboard.index', [
                'pageTitle' => 'Dashboard',
                'stats' => [
                    'teams' => Team::countAll(),
                    'players' => Player::countAll(),
                    'tournaments' => Tournament::countAll(),
                    'matches' => MatchModel::countAll(),
                ],
                'recentMatches' => MatchModel::recent(6),
                'upcomingMatches' => MatchModel::upcoming(5),
                'topScorers' => Player::topScorers(5),
                'ongoingTournaments' => Tournament::byStatus('ongoing', 3),
                'upcomingEvents' => EventModel::upcoming(5, null),
                'user' => $user,
            ]);
        }

        $teamId = $this->scopeTeamId();
        $team = Team::withPlayers($teamId);

        if ($team === null) {
            abort(403, 'Your account is linked to a team that no longer exists.');
        }

        return $this->view('dashboard.team', [
            'pageTitle' => 'Team Dashboard',
            'team' => $team,
            'staffCount' => User::countForTeam($teamId),
            'recentMatches' => MatchModel::recentForTeam($teamId, 5),
            'upcomingMatches' => MatchModel::upcomingForTeam($teamId, 5),
            'fitness' => Player::fitnessOverview($teamId),
            'alerts' => Player::alerts($teamId),
            'upcomingEvents' => EventModel::upcoming(5, $teamId),
            'topScorers' => Player::topScorersForTeam($teamId, 5),
            'user' => $user,
        ]);
    }

    /** Player-facing home: own profile, performance, attendance and upcoming sessions. */
    private function renderPortal()
    {
        $playerId = auth_player_id();
        $player = $playerId !== null ? Player::withTeam($playerId) : null;

        if ($player === null) {
            abort(403, 'Your player profile could not be found. Please contact an administrator.');
        }

        $teamId = (int) $player['team_id'];

        // Upcoming own-team sessions (training etc.) with the player's check-in status.
        $upcoming = EventModel::upcoming(8, $teamId, $teamId);
        $eventIds = array_map(static fn (array $event): int => (int) $event['id'], $upcoming);

        return $this->view('dashboard.portal', [
            'pageTitle' => 'My Portal',
            'player' => $player,
            'team' => Team::findArray($teamId),
            'upcoming' => $upcoming,
            'attendanceStatuses' => Attendance::statusesForPlayer((int) $player['id'], $eventIds),
            'attendanceSummary' => Attendance::summaryForPlayer((int) $player['id']),
            'latestMetric' => PlayerMetric::latestFor((int) $player['id']),
            'latestWelfare' => PlayerWelfare::latestFor((int) $player['id']),
            'user' => $this->user(),
        ]);
    }
}
