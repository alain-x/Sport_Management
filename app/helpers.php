<?php

declare(strict_types=1);

/**
 * Global helper functions available everywhere (loaded by Composer "files" autoload).
 */

use App\Core\Auth;

function e(mixed $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function config(string $key, mixed $default = null): mixed
{
    static $config = null;
    if ($config === null) {
        $config = require BASE_PATH . '/config/config.php';
    }

    $segments = explode('.', $key);
    $value = $config;
    foreach ($segments as $segment) {
        if (!is_array($value) || !array_key_exists($segment, $value)) {
            return $default;
        }
        $value = $value[$segment];
    }

    return $value;
}

function url(string $path = ''): string
{
    return BASE_URL . '/' . ltrim($path, '/');
}

/**
 * Remove the application's URL prefix from a raw URI.
 *
 * The app can be entered either under BASE_URL (e.g. /sport_management/public/...)
 * or via the project root .htaccess (e.g. /sport_management/...), so both
 * prefixes are recognised.
 */
function strip_base_from_uri(string $uri): string
{
    $candidates = [];
    if (BASE_URL !== '') {
        $candidates[] = BASE_URL;
        $parent = rtrim(str_replace('\\', '/', dirname(BASE_URL)), '/');
        if ($parent !== '' && $parent !== '.') {
            $candidates[] = $parent;
        }
    }

    foreach ($candidates as $prefix) {
        if ($uri === $prefix || str_starts_with($uri, $prefix . '/')) {
            return substr($uri, strlen($prefix));
        }
    }

    return $uri;
}

function redirect(string $path): never
{
    header('Location: ' . (str_starts_with($path, 'http') ? $path : url($path)));
    exit;
}

function current_path(): string
{
    $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $uri = rawurldecode($uri);

    return trim(strip_base_from_uri($uri), '/');
}

function nav_active(string $prefix): string
{
    $path = current_path();
    $active = $path === $prefix || str_starts_with($path, $prefix . '/');

    return $active ? 'active' : '';
}

/** Store (with args) or pull (without args) flash messages. */
function flash(?string $type = null, ?string $message = null): mixed
{
    if ($type !== null && $message !== null) {
        $_SESSION['flash'][$type] = $message;

        return null;
    }

    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);

    return $messages;
}

/** Old input value from a failed validation, falling back to a default. */
function old(string $key, mixed $default = null): mixed
{
    return $_SESSION['old'][$key] ?? $default;
}

/** First validation error for a field, if any. */
function error(string $key): ?string
{
    $errors = $_SESSION['errors'] ?? [];

    return $errors[$key][0] ?? null;
}

function csrf_field(): string
{
    return '<input type="hidden" name="_token" value="' . e(\App\Core\Csrf::token()) . '">';
}

function auth_user(): ?array
{
    return Auth::user();
}

/** Basic pagination metadata used by index views. */
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

/** Render Bootstrap pagination links for a paginate() array. */
function pagination_links(array $pg): string
{
    if ($pg['pages'] <= 1) {
        return '';
    }

    $link = function (int $page, string $label, bool $disabled = false, bool $active = false) use ($pg): string {
        $classes = 'page-item' . ($disabled ? ' disabled' : '') . ($active ? ' active' : '');
        if ($disabled) {
            return '<li class="' . $classes . '"><span class="page-link">' . $label . '</span></li>';
        }
        $query = array_merge($pg['query'], ['page' => $page]);
        $href = e(url($pg['path'] . '?' . http_build_query($query)));

        return '<li class="' . $classes . '"><a class="page-link" href="' . $href . '">' . $label . '</a></li>';
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

/** Friendly date/time formatting; returns "-" for empty values. */
function format_date(?string $value, bool $withTime = false): string
{
    if ($value === null || $value === '' || $value === '0000-00-00') {
        return '-';
    }
    $ts = strtotime($value);
    if ($ts === false) {
        return e($value);
    }

    return date($withTime ? 'd M Y, H:i' : 'd M Y', $ts);
}

/** True when the signed-in user is an administrator. */
function is_admin(): bool
{
    $user = auth_user();

    return $user !== null && ($user['role'] ?? '') === 'admin';
}

/** Team id the signed-in team staff member belongs to (null for admins/unscoped users). */
function auth_team_id(): ?int
{
    $user = auth_user();
    if ($user === null || ($user['role'] ?? '') !== 'manager' || ($user['team_id'] ?? null) === null) {
        return null;
    }

    return (int) $user['team_id'];
}

/** URL of a stored upload ("players/abc.jpg" -> /uploads/players/abc.jpg). */
function upload_url(?string $path): string
{
    if ($path === null || $path === '') {
        return '';
    }

    return BASE_URL . '/uploads/' . ltrim($path, '/');
}

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

/**
 * Circle avatar: uploaded photo when present, initials fallback otherwise.
 *
 * @param string $extraClass e.g. "avatar-sm", "avatar-lg".
 */
function avatar_html(?string $photoPath, string $name, string $extraClass = ''): string
{
    $class = trim('avatar ' . $extraClass);
    if ($photoPath !== null && $photoPath !== '' && upload_url($photoPath) !== '') {
        return '<span class="' . e($class) . '"><img src="' . e(upload_url($photoPath))
            . '" alt="' . e($name) . '"></span>';
    }

    return '<span class="' . e($class) . '">' . e(initials($name)) . '</span>';
}

/** Team crest: uploaded logo when present, short code fallback otherwise. */
function crest_html(?string $logoPath, string $code, string $extraClass = ''): string
{
    $class = trim('team-crest ' . $extraClass);
    if ($logoPath !== null && $logoPath !== '' && upload_url($logoPath) !== '') {
        return '<span class="' . e($class) . '"><img src="' . e(upload_url($logoPath))
            . '" alt="' . e($code) . '"></span>';
    }

    return '<span class="' . e($class) . '">' . e($code) . '</span>';
}
