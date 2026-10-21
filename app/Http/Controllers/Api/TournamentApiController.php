<?php

namespace App\Http\Controllers\Api;

use App\Models\MatchModel;
use App\Models\Team;
use App\Models\Tournament;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Tournaments and their league tables.
 */
class TournamentApiController extends ApiController
{
    /** GET /api/tournaments */
    public function index(): JsonResponse
    {
        return $this->ok([
            'items' => Tournament::allWithCounts(),
        ]);
    }

    /** GET /api/tournaments/{id} (with its matches) */
    public function show(string $id): JsonResponse
    {
        $tournament = Tournament::withMatches((int) $id);
        if ($tournament === null) {
            abort(404, 'Tournament not found.');
        }

        return $this->ok($tournament);
    }

    /** GET /api/tournaments/{id}/standings */
    public function standings(string $id): JsonResponse
    {
        $tournament = Tournament::findArray((int) $id);
        if ($tournament === null) {
            abort(404, 'Tournament not found.');
        }

        return $this->ok([
            'tournament' => $tournament,
            'standings' => MatchModel::standings((int) $id),
        ]);
    }

    /** GET /api/standings -> the active tournament's table (mirrors the web app). */
    public function activeStandings(): JsonResponse
    {
        $ongoing = Tournament::byStatus('ongoing', 1);
        $tournament = $ongoing[0] ?? Tournament::allWithCounts()[0] ?? null;

        if ($tournament === null) {
            return $this->ok(['tournament' => null, 'standings' => []]);
        }

        return $this->ok([
            'tournament' => $tournament,
            'standings' => MatchModel::standings((int) $tournament['id']),
        ]);
    }

    /** GET /api/teams/{id}/matches -> fixtures/results for one team. */
    public function teamMatches(string $id): JsonResponse
    {
        $teamId = (int) $id;

        if (! $this->isAdmin() && $this->user()?->team_id !== null && (int) $this->user()->team_id !== $teamId) {
            abort(403, 'This team belongs to another account.');
        }

        return $this->ok([
            'recent' => MatchModel::recentForTeam($teamId, 5),
            'upcoming' => MatchModel::upcomingForTeam($teamId, 5),
        ]);
    }
}
