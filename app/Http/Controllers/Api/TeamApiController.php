<?php

namespace App\Http\Controllers\Api;

use App\Models\Player;
use App\Models\Team;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Teams (list / detail with squad and staff).
 *
 * Team-scoped users only ever see their own team; admins see everything.
 */
class TeamApiController extends ApiController
{
    /** GET /api/teams?q=&page= */
    public function index(Request $request): JsonResponse
    {
        $search = trim((string) $request->input('q', ''));
        $perPage = min(50, max(1, (int) $request->input('per_page', 20)));
        $page = max(1, (int) $request->input('page', 1));
        $offset = ($page - 1) * $perPage;

        $scope = $this->teamId(); // null for admins
        $query = Team::searchQuery($search)->withCount('players');
        if ($scope !== null) {
            $query->whereKey($scope);
        }

        $total = (clone $query)->count();
        $teams = $query->orderBy('name')->skip($offset)->take($perPage)->get()
            ->map(fn (Team $t) => $t->toArray() + ['players_count' => (int) $t->players_count])
            ->all();

        return $this->ok([
            'items' => $teams,
            'meta' => [
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'pages' => max(1, (int) ceil($total / $perPage)),
            ],
        ]);
    }

    /** GET /api/teams/{id} */
    public function show(string $id): JsonResponse
    {
        $teamId = (int) $id;

        // Players and managers may only open their own team.
        if (! $this->isAdmin()) {
            $own = $this->teamId();
            if ($own !== null && $own !== $teamId) {
                abort(404);
            }
        }

        $team = Team::withPlayers($teamId);
        if ($team === null) {
            abort(404, 'Team not found.');
        }

        $team['staff'] = Team::withStaff($teamId)['staff'] ?? [];

        return $this->ok($team);
    }
}
