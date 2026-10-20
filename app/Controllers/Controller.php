<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;

abstract class Controller
{
    /** Render a view inside the shared layout. */
    protected function render(string $view, array $data = []): void
    {
        $data['appName'] = (string) config('app.name', 'Sport Manager');
        View::render($view, $data);
    }

    /** Persist failed-validation input and redirect back (Post/Redirect/Get). */
    protected function failedValidation(array $input, array $errors, string $fallback): void
    {
        $_SESSION['old'] = $input;
        $_SESSION['errors'] = $errors;
        $this->back($fallback);
    }

    /** Redirect to the referring page (or a fallback path). */
    protected function back(string $fallback = '/'): void
    {
        $referer = $_SERVER['HTTP_REFERER'] ?? '';
        $path = $fallback;

        if ($referer !== '') {
            $refPath = parse_url($referer, PHP_URL_PATH) ?: '/';
            $refPath = strip_base_from_uri(rawurldecode($refPath));
            $trimmed = trim($refPath, '/');
            if ($trimmed !== '') {
                $path = '/' . $trimmed;
            }
        }

        redirect($path);
    }

    protected function notFound(): void
    {
        http_response_code(404);
        View::render('errors/404', [
            'appName' => (string) config('app.name', 'Sport Manager'),
        ], null);
    }

    /** Render the standalone 403 page and stop the request. */
    protected function abort403(string $message = 'You do not have permission to view this page.'): never
    {
        http_response_code(403);
        View::render('errors/403', [
            'appName' => (string) config('app.name', 'Sport Manager'),
            'errorMessage' => $message,
        ], null);
        exit;
    }

    /** Restrict a route/page to administrators. */
    protected function requireAdmin(): void
    {
        if (!is_admin()) {
            $this->abort403('This area is restricted to administrators.');
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
            $this->abort403('Your account is not linked to a team. Please contact an administrator.');
        }

        return $teamId;
    }

    /** Abort unless the user may manage records belonging to the given team. */
    protected function requireTeam(?int $teamId): void
    {
        if (is_admin()) {
            return;
        }

        $own = auth_team_id();
        if ($own === null || $teamId === null || $own !== $teamId) {
            $this->abort403('This record belongs to another team.');
        }
    }
}
