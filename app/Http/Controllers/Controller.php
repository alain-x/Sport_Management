<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

abstract class Controller
{
    /** The signed-in user, or null. */
    protected function user(): ?User
    {
        /** @var User|null $user */
        $user = Auth::user();

        return $user;
    }

    /** Render a view inside the shared Bootstrap 5 layout. */
    protected function view(string $view, array $data = []): View
    {
        $data['appName'] = (string) config('app.name', 'Sport Manager');

        // Mirror the original View::render(): render the page fragment, then
        // hand it to the layout as $content (keeping the exact legacy markup).
        $content = view($view, $data)->render();

        return view('layouts.app', $data + ['content' => $content]);
    }

    /** Restrict a route/page to administrators. */
    protected function requireAdmin(): void
    {
        if (! is_admin()) {
            abort(403, 'This area is restricted to administrators.');
        }
    }

    /** Restrict a route/page to team staff (admin or manager) - players cannot manage records. */
    protected function requireStaff(): void
    {
        if (! is_staff()) {
            abort(403, 'This area is for team staff and administrators.');
        }
    }

    /**
     * Team scope for team-scoped pages: null for admins (no restriction),
     * the manager's own team id otherwise. Managers without a team are blocked.
     */
    protected function scopeTeamId(): ?int
    {
        if (is_admin()) {
            return null;
        }

        $teamId = auth_team_id();
        if ($teamId === null) {
            abort(403, 'Your account is not linked to a team. Please contact an administrator.');
        }

        return $teamId;
    }

    /** Abort unless the user may manage records belonging to the given team. */
    protected function requireTeam(?int $teamId): void
    {
        if (is_admin()) {
            return;
        }

        if (is_player()) {
            abort(403, 'Players cannot manage team records.');
        }

        $own = auth_team_id();
        if ($own === null || $teamId === null || $own !== $teamId) {
            abort(403, 'This record belongs to another team.');
        }
    }

    /** Redirect back to the referring page (or a fallback path), preserving the fragment. */
    protected function backTo(string $fallback = '/'): RedirectResponse
    {
        $referer = request()->headers->get('referer');
        $target = $fallback;

        if ($referer !== null && $referer !== '') {
            $path = parse_url($referer, PHP_URL_PATH) ?: '/';
            $path = rawurldecode($path);
            // Strip the app base (/sport_management or /sport_management/public).
            $path = preg_replace('#^/(sport_management)(/public)?#', '', $path) ?: '/';
            $trimmed = trim($path, '/');
            $fragment = parse_url($referer, PHP_URL_FRAGMENT);
            $target = '/'.$trimmed.($fragment !== null ? '#'.$fragment : '');
        }

        return redirect($target);
    }
}
