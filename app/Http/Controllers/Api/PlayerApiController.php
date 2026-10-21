<?php

namespace App\Http\Controllers\Api;

use App\Models\Player;
use App\Models\PlayerMetric;
use App\Models\PlayerWelfare;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Players. Players may only see their own record and their own team's public
 * profiles (private tabs stay staff-only); other teams yield 404.
 */
class PlayerApiController extends ApiController
{
    /** GET /api/players?q=&team_id=&page= */
    public function index(Request $request): JsonResponse
    {
        $search = trim((string) $request->input('q', ''));
        $perPage = min(50, max(1, (int) $request->input('per_page', 20)));
        $page = max(1, (int) $request->input('page', 1));
        $offset = ($page - 1) * $perPage;

        if ($this->isPlayer()) {
            // A player only browses their own team's squad.
            $teamId = (int) $this->user()->team_id;
        } else {
            $teamId = $request->filled('team_id') ? (int) $request->input('team_id') : null;
            // Managers are pinned to their own team.
            if (! $this->isAdmin()) {
                $teamId = $this->teamId();
            }
        }

        $items = Player::paginate($perPage, $offset, $search, $teamId);

        return $this->ok([
            'items' => $items,
            'meta' => [
                'total' => Player::countFiltered($search, $teamId),
                'page' => $page,
                'per_page' => $perPage,
            ],
        ]);
    }

    /** GET /api/players/{id} */
    public function show(string $id): JsonResponse
    {
        $playerId = (int) $id;
        $player = Player::withTeam($playerId);

        if ($player === null) {
            abort(404, 'Player not found.');
        }

        $isSelf = $this->isPlayer() && (int) $this->user()->player_id === $playerId;

        if ($this->isPlayer()) {
            $ownTeam = (int) $this->user()->team_id;
            if (! $isSelf && $ownTeam !== (int) $player['team_id']) {
                abort(404);
            }
        } elseif (! $this->isAdmin()) {
            // Managers: own team only.
            $this->requireTeam((int) $player['team_id']);
        }

        // Private data (metrics/welfare) is visible to self and staff only.
        $canSeePrivate = ! $this->isPlayer() || $isSelf;

        $payload = $player;
        $payload['is_self'] = $isSelf;
        $payload['can_see_private'] = $canSeePrivate;

        $payload['metrics'] = $canSeePrivate ? PlayerMetric::forPlayer($playerId) : [];
        $payload['welfare'] = $canSeePrivate ? PlayerWelfare::forPlayer($playerId) : [];

        return $this->ok($payload);
    }
}
