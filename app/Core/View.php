<?php

declare(strict_types=1);

namespace App\Core;

class View
{
    /** Render a view, optionally wrapped in the shared layout. */
    public static function render(string $view, array $data = [], ?string $layout = 'layout'): void
    {
        $content = self::partial($view, $data);

        if ($layout === null) {
            echo $content;

            return;
        }

        $appName = (string) ($data['appName'] ?? config('app.name', 'Sport Manager'));
        $pageTitle = (string) ($data['pageTitle'] ?? 'Dashboard');
        include VIEWS_PATH . '/' . $layout . '.php';
    }

    /** Render only a view fragment and return it as a string. */
    public static function partial(string $view, array $data = []): string
    {
        extract($data, EXTR_SKIP);
        ob_start();
        include VIEWS_PATH . '/' . $view . '.php';

        return (string) ob_get_clean();
    }
}
