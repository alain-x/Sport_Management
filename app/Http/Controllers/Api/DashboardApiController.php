<?php

namespace App\Http\Controllers\Api;

use App\Models\Attendance;
use App\Models\EventModel;
use App\Models\MatchModel;
use App\Models\Player;
use App\Models\PlayerMetric;
use App\Models\PlayerWelfare;
use App\Models\Team;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Http\JsonResponse;

/**
 * Role-aware home summary for the mobile app, mirroring the web dashboard.
 */
class DashboardApiController extends ApiController
{
    /** GET /api/dashboard */
    public function index(): JsonResponse
    {
        if ($this->isPlayer()) {
            return $this->ok($this->playerDashboard());
        }

        if ($this->isAdmin()) {
            return $this->ok($this->adminDashboard());
        }

        return $this->ok($this->teamDashboard());
    }

    private function adminDashboard(): array
    {
        return [
            'role' => 'admin',
            'stats' => [
                'teams' => Team::countAll(),
                'players' => Player::countAll(),
                'tournaments' => Tournament::countAll(),
                'matches' => MatchModel::countAll(),
            ],
            'recent_matches' => MatchModel::recent(6),
            'upcoming_matches' => MatchModel::upcoming(5),
            'top_scorers' => Player::topScorers(5),
            'ongoing_tournaments' => Tournament::byStatus('ongoing', 3),
            'upcoming_events' => EventModel::upcoming(5, null),
        ];
    }

    private function teamDashboard(): array
    {
        $teamId = $this->teamId();
        $team = Team::withPlayers($teamId);

        if ($team === null) {
            abort(403, 'Your account is linked to a team that no longer exists.');
        }

        return [
            'role' => 'manager',
            'team' => $team,
            'staff_count' => User::countForTeam($teamId),
            'recent_matches' => MatchModel::recentForTeam($teamId, 5),
            'upcoming_matches' => MatchModel::upcomingForTeam($teamId, 5),
            'fitness' => Player::fitnessOverview($teamId),
            'alerts' => Player::alerts($teamId),
            'upcoming_events' => EventModel::upcoming(5, $teamId),
            'top_scorers' => Player::topScorersForTeam($teamId, 5),
        ];
    }

    private function playerDashboard(): array
    {
        $playerId = $this->user()->player_id;
        $player = $playerId !== null ? Player::withTeam((int) $playerId) : null;

        if ($player === null) {
            abort(403, 'Your player profile could not be found. Please contact an administrator.');
        }

        $teamId = (int) $player['team_id'];
        $upcoming = EventModel::upcoming(8, $teamId, $teamId);
        $eventIds = array_map(static fn (array $e): int => (int) $e['id'], $upcoming);

        return [
            'role' => 'player',
            'player' => $player,
            'team' => Team::findArray($teamId),
            'upcoming' => $upcoming,
            'attendance_statuses' => Attendance::statusesForPlayer((int) $player['id'], $eventIds),
            'attendance_summary' => Attendance::summaryForPlayer((int) $player['id']),
            'latest_metric' => PlayerMetric::latestFor((int) $player['id']),
            'latest_welfare' => PlayerWelfare::latestFor((int) $player['id']),
        ];
    }
}
