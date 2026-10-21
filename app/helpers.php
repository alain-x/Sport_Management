<?php

declare(strict_types=1);

/**
 * Global view helpers (autoloaded via composer "files").
 *
 * These mirror the helpers used by the original hand-rolled app so the ported
 * Blade views keep the exact same markup the smoke suites assert on.
 */

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ViewErrorBag;

if (! function_exists('flash')) {
    /**
     * Read and clear the current flash messages (assoc type => message), or
     * queue a new one when both arguments are given (legacy behaviour).
     *
     * @return array<string, string>|null
     */
    function flash(?string $type = null, ?string $message = null): ?array
    {
        if ($type !== null && $message !== null) {
            session()->flash($type, $message);

            return null;
        }

        $messages = [];
        foreach (['success', 'error', 'warning', 'info'] as $key) {
            $value = session()->get($key);
            if ($value !== null) {
                $messages[$key] = (string) $value;
                session()->forget($key);
            }
        }

        return $messages;
    }
}

if (! function_exists('old')) {
    /** Old input value from a failed validation, falling back to a default. */
    function old(string $key, mixed $default = null): mixed
    {
        return session()->getOldInput($key, $default);
    }
}

if (! function_exists('error')) {
    /** First validation error for a field, if any. */
    function error(string $key): ?string
    {
        /** @var ViewErrorBag|null $bag */
        $bag = session()->get('errors');
        if ($bag === null) {
            return null;
        }

        $messages = $bag->getBag('default')->get($key);

        return $messages[0] ?? null;
    }
}

if (! function_exists('csrf_field')) {
    /** Hidden CSRF field; mirrors the original helper's exact markup. */
    function csrf_field(): string
    {
        return '<input type="hidden" name="_token" value="'.e(csrf_token()).'">';
    }
}

if (! function_exists('auth_user')) {
    /** The signed-in user as a plain array (legacy shape), or null. */
    function auth_user(): ?array
    {
        /** @var User|null $user */
        $user = Auth::user();

        return $user?->toArray();
    }
}

if (! function_exists('is_admin')) {
    function is_admin(): bool
    {
        return Auth::check() && Auth::user()->isAdmin();
    }
}

if (! function_exists('is_player')) {
    function is_player(): bool
    {
        return Auth::check() && Auth::user()->isPlayer();
    }
}

if (! function_exists('is_staff')) {
    function is_staff(): bool
    {
        return Auth::check() && Auth::user()->isStaff();
    }
}

if (! function_exists('auth_player_id')) {
    /** Linked player record id for a player login (null otherwise). */
    function auth_player_id(): ?int
    {
        $user = Auth::user();
        if ($user === null || ! $user->isPlayer() || $user->player_id === null) {
            return null;
        }

        return (int) $user->player_id;
    }
}

if (! function_exists('auth_team_id')) {
    /** Team id the signed-in staff member or player belongs to (null for admins). */
    function auth_team_id(): ?int
    {
        $user = Auth::user();
        if ($user === null || ! $user->hasRole('manager', 'player') || $user->team_id === null) {
            return null;
        }

        return (int) $user->team_id;
    }
}

if (! function_exists('current_path')) {
    /** Current request path with the app base prefix stripped (legacy helper). */
    function current_path(): string
    {
        $uri = rawurldecode(request()->path());

        // Strip the app base (/sport_management or /sport_management/public).
        $uri = preg_replace('#^sport_management(/public)?#', '', $uri) ?: '';

        return trim($uri, '/');
    }
}

if (! function_exists('nav_active')) {
    /** "active" when the current path equals or is nested under $prefix. */
    function nav_active(string $prefix): string
    {
        $path = current_path();
        $prefix = trim($prefix, '/');
        $active = $path === $prefix || ($prefix !== '' && str_starts_with($path, $prefix.'/'));

        return $active ? 'active' : '';
    }
}

if (! function_exists('upload_url')) {
    /** URL of a stored upload ("players/abc.jpg" -> /uploads/players/abc.jpg). */
    function upload_url(?string $path): string
    {
        if ($path === null || $path === '') {
            return '';
        }

        return asset('uploads/'.ltrim($path, '/'));
    }
}

if (! function_exists('initials')) {
    /** Initials for avatar/crest fallbacks ("James Carter" -> "JC"). */
    function initials(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        if ($parts === [] || $parts === false) {
            return '?';
        }

        $letters = mb_strtoupper(mb_substr($parts[0], 0, 1));
        if (count($parts) > 1) {
            $letters .= mb_strtoupper(mb_substr((string) end($parts), 0, 1));
        }

        return $letters !== '' ? $letters : '?';
    }
}

if (! function_exists('avatar_html')) {
    /** Circle avatar: uploaded photo when present, initials fallback otherwise. */
    function avatar_html(?string $photoPath, string $name, string $extraClass = ''): string
    {
        $class = trim('avatar '.$extraClass);
        if ($photoPath !== null && $photoPath !== '' && upload_url($photoPath) !== '') {
            return '<span class="'.e($class).'"><img src="'.e(upload_url($photoPath))
                .'" alt="'.e($name).'"></span>';
        }

        return '<span class="'.e($class).'">'.e(initials($name)).'</span>';
    }
}

if (! function_exists('crest_html')) {
    /** Team crest: uploaded logo when present, short code fallback otherwise. */
    function crest_html(?string $logoPath, string $code, string $extraClass = ''): string
    {
        $class = trim('team-crest '.$extraClass);
        if ($logoPath !== null && $logoPath !== '' && upload_url($logoPath) !== '') {
            return '<span class="'.e($class).'"><img src="'.e(upload_url($logoPath))
                .'" alt="'.e($code).'"></span>';
        }

        return '<span class="'.e($class).'">'.e($code).'</span>';
    }
}

if (! function_exists('format_date')) {
    /** Friendly date/time formatting; returns "-" for empty values. */
    function format_date(mixed $value, bool $withTime = false): string
    {
        if ($value === null || $value === '' || $value === '0000-00-00') {
            return '-';
        }

        try {
            $date = $value instanceof DateTimeInterface
                ? Carbon::instance($value)
                : Carbon::parse((string) $value);
        } catch (Throwable) {
            return e((string) $value);
        }

        return $date->format($withTime ? 'd M Y, H:i' : 'd M Y');
    }
}

if (! function_exists('paginate')) {
    /** Basic pagination metadata used by index views (legacy shape). */
    function paginate(int $total, int $perPage, int $page, string $path, array $query = []): array
    {
        $pages = max(1, (int) ceil($total / $perPage));
        $page = max(1, min($page, $pages));

        return [
            'total' => $total,
            'perPage' => $perPage,
            'page' => $page,
            'pages' => $pages,
            'path' => $path,
            'query' => $query,
        ];
    }
}

if (! function_exists('pagination_links')) {
    /** Render Bootstrap pagination links for a paginate() array (legacy shape). */
    function pagination_links(array $pg): string
    {
        if (($pg['pages'] ?? 1) <= 1) {
            return '';
        }

        $link = function (int $page, string $label, bool $disabled = false, bool $active = false) use ($pg): string {
            $classes = 'page-item'.($disabled ? ' disabled' : '').($active ? ' active' : '');
            if ($disabled) {
                return '<li class="'.$classes.'"><span class="page-link">'.$label.'</span></li>';
            }
            $query = array_merge($pg['query'], ['page' => $page]);
            $href = e(url($pg['path'].'?'.http_build_query($query)));

            return '<li class="'.$classes.'"><a class="page-link" href="'.$href.'">'.$label.'</a></li>';
        };

        $html = '<nav><ul class="pagination pagination-sm mb-0">';
        $html .= $link(max(1, $pg['page'] - 1), '&laquo;', $pg['page'] <= 1);

        $start = max(1, $pg['page'] - 2);
        $end = min($pg['pages'], $pg['page'] + 2);
        if ($start > 1) {
            $html .= $link(1, '1');
            if ($start > 2) {
                $html .= '<li class="page-item disabled"><span class="page-link">&hellip;</span></li>';
            }
        }
        for ($i = $start; $i <= $end; $i++) {
            $html .= $link($i, (string) $i, false, $i === $pg['page']);
        }
        if ($end < $pg['pages']) {
            if ($end < $pg['pages'] - 1) {
                $html .= '<li class="page-item disabled"><span class="page-link">&hellip;</span></li>';
            }
            $html .= $link($pg['pages'], (string) $pg['pages']);
        }

        $html .= $link(min($pg['pages'], $pg['page'] + 1), '&raquo;', $pg['page'] >= $pg['pages']);
        $html .= '</ul></nav>';

        return $html;
    }
}
