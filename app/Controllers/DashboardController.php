<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Models\EventModel;
use App\Models\MatchModel;
use App\Models\Player;
use App\Models\Team;
use App\Models\Tournament;
use App\Models\User;

class DashboardController extends Controller
{
    public function index(): void
    {
        $user = Auth::user();

        if (is_admin()) {
            $this->render('dashboard/index', [
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

            return;
        }

        $teamId = $this->scopeTeamId();
        $team = Team::withPlayers($teamId);

        if ($team === null) {
            $this->abort403('Your account is linked to a team that no longer exists.');
        }

        $this->render('dashboard/team', [
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
}
