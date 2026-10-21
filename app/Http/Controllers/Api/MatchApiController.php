<?php

namespace App\Http\Controllers\Api;

use App\Models\MatchModel;
use App\Models\Team;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Matches (fixtures and results). Read access for everyone; create/update
 * is staff-only and scoped to the caller's team.
 */
class MatchApiController extends ApiController
{
    /** GET /api/matches?tournament_id=&status=&team_id=&page= */
    public function index(Request $request): JsonResponse
    {
        $perPage = min(50, max(1, (int) $request->input('per_page', 20)));
        $page = max(1, (int) $request->input('page', 1));
        $offset = ($page - 1) * $perPage;

        $filters = [
            'tournament_id' => $request->filled('tournament_id') ? (int) $request->input('tournament_id') : null,
            'status' => $request->filled('status') ? (string) $request->input('status') : null,
        ];

        $items = MatchModel::paginate($perPage, $offset, $filters);

        // Non-admins only ever see fixtures involving a team they belong to.
        $scope = $this->isAdmin() ? null : $this->user()->team_id;
        if ($scope !== null) {
            $scope = (int) $scope;
            $items = array_values(array_filter(
                $items,
                fn (array $m) => (int) $m['home_team_id'] === $scope || (int) $m['away_team_id'] === $scope
            ));
        }

        return $this->ok([
            'items' => $items,
            'meta' => [
                'total' => MatchModel::countFiltered($filters),
                'page' => $page,
                'per_page' => $perPage,
            ],
        ]);
    }

    /** GET /api/matches/{id} */
    public function show(string $id): JsonResponse
    {
        $match = MatchModel::withTeams((int) $id);
        if ($match === null) {
            abort(404, 'Match not found.');
        }

        if (! $this->isAdmin()) {
            $own = (int) $this->user()->team_id;
            if ((int) $match['home_team_id'] !== $own && (int) $match['away_team_id'] !== $own) {
                abort(404);
            }
        }

        return $this->ok($match);
    }

    /** POST /api/matches (staff only) */
    public function store(Request $request): JsonResponse
    {
        $this->requireStaff();

        $home = (int) $request->input('home_team_id');
        $away = (int) $request->input('away_team_id');

        if (! $this->isAdmin()) {
            $own = $this->teamId();
            if ($home !== $own && $away !== $own) {
                abort(403, 'You can only schedule matches for your own team.');
            }
        }

        $match = MatchModel::create($request->validate([
            'tournament_id' => ['nullable', 'integer', 'exists:tournaments,id'],
            'home_team_id' => ['required', 'integer', 'exists:teams,id'],
            'away_team_id' => ['required', 'integer', 'exists:teams,id', 'different:home_team_id'],
            'home_score' => ['nullable', 'integer', 'min:0'],
            'away_score' => ['nullable', 'integer', 'min:0'],
            'match_date' => ['nullable', 'date'],
            'venue' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', 'in:scheduled,live,finished'],
        ]));

        return $this->ok(MatchModel::withTeams((int) $match->id), 201);
    }
}
