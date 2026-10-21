<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Base class for the JSON API consumed by the mobile (Flutter) app.
 *
 * Every endpoint is stateless and authenticated with a Sanctum personal
 * access token (Authorization: Bearer <token>). Responses use a small,
 * predictable envelope:
 *
 *   { "data": ... }             on success
 *   { "message": "...",         on failure
 *     "errors": { field: [..] } }
 */
abstract class ApiController extends Controller
{
    /** Current authenticated user's role, e.g. "admin" / "manager" / "player". */
    protected function role(): string
    {
        return (string) ($this->user()?->role ?? '');
    }

    protected function isAdmin(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    protected function isPlayer(): bool
    {
        return $this->user()?->isPlayer() ?? false;
    }

    protected function isStaff(): bool
    {
        return $this->user()?->isStaff() ?? false;
    }

    /** Team id the caller belongs to (null for admins), aborting when unset. */
    protected function teamId(): ?int
    {
        if ($this->isAdmin()) {
            return null;
        }

        $teamId = $this->user()?->team_id;
        if ($teamId === null) {
            abort(403, 'Your account is not linked to a team. Please contact an administrator.');
        }

        return (int) $teamId;
    }

    /** Restrict to administrators. */
    protected function requireAdmin(): void
    {
        if (! $this->isAdmin()) {
            abort(403, 'This action is restricted to administrators.');
        }
    }

    /** Restrict to team staff (admin or manager) - players cannot manage records. */
    protected function requireStaff(): void
    {
        if (! $this->isStaff()) {
            abort(403, 'This action is for team staff and administrators.');
        }
    }

    /** Abort unless the caller may manage records belonging to the given team. */
    protected function requireTeam(?int $teamId): void
    {
        if ($this->isAdmin()) {
            return;
        }

        if ($this->isPlayer()) {
            abort(403, 'Players cannot manage team records.');
        }

        $own = $this->user()?->team_id;
        if ($own === null || $teamId === null || (int) $own !== (int) $teamId) {
            abort(403, 'This record belongs to another team.');
        }
    }

    /** Success envelope. */
    protected function ok(mixed $data = null, int $status = 200): JsonResponse
    {
        return response()->json(['data' => $data], $status);
    }

    /** Message-only success envelope (for write actions). */
    protected function message(string $message, array $extra = [], int $status = 200): JsonResponse
    {
        return response()->json(['message' => $message] + $extra, $status);
    }

    /** Validation failure envelope (422). */
    protected function validationError(array $errors, string $message = 'The given data was invalid.'): JsonResponse
    {
        return response()->json([
            'message' => $message,
            'errors' => $errors,
        ], 422);
    }
}
