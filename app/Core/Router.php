<?php

declare(strict_types=1);

namespace App\Core;

use Throwable;

class Router
{
    /** @var array<int, array{0:string,1:string,2:array{0:string,1:string},3:bool}> */
    private array $routes = [];

    public function __construct(private readonly Request $request)
    {
    }

    public function get(string $path, array $handler, bool $auth = true): void
    {
        $this->add('GET', $path, $handler, $auth);
    }

    public function post(string $path, array $handler, bool $auth = true): void
    {
        $this->add('POST', $path, $handler, $auth);
    }

    private function add(string $method, string $path, array $handler, bool $auth): void
    {
        $pattern = preg_replace_callback(
            '#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#',
            static fn (array $m): string => '(?P<' . $m[1] . '>' . ($m[1] === 'id' ? '\d+' : '[^/]+') . ')',
            trim($path, '/')
        );

        $this->routes[] = [$method, '#^' . $pattern . '$#', $handler, $auth];
    }

    public function dispatch(): void
    {
        $method = $this->request->method();
        $path = $this->request->path();

        foreach ($this->routes as [$routeMethod, $pattern, $handler, $auth]) {
            if ($routeMethod !== $method || !preg_match($pattern, $path, $matches)) {
                continue;
            }

            if ($auth && !Auth::check()) {
                flash('error', 'Please sign in to continue.');
                redirect('/login');
            }

            if ($method !== 'GET' && !Csrf::verify((string) ($_POST['_token'] ?? ''))) {
                http_response_code(419);
                echo '<h1>419 - Session expired</h1><p>Please go back, refresh the page and try again.</p>';

                return;
            }

            $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
            [$class, $action] = $handler;

            try {
                (new $class())->{$action}(...array_values($params));
            } catch (Throwable $e) {
                if ((bool) config('app.debug_force_log', true)) {
                    error_log($e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
                }
                http_response_code(500);
                echo '<h1>500 - Something went wrong</h1><p>' . htmlspecialchars($e->getMessage()) . '</p>';
            } finally {
                // Old input / validation errors are only meant for the response that set them.
                unset($_SESSION['old'], $_SESSION['errors']);
            }

            return;
        }

        http_response_code(404);
        View::render('errors/404', ['appName' => (string) config('app.name', 'Sport Manager')], null);
    }
}
