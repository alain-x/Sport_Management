<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Support being served straight from the project root (see the root .htaccess),
// where SCRIPT_NAME points at /<app>/public/index.php while REQUEST_URI is the
// public URL (/<app>/...). Rewriting SCRIPT_NAME back to the project root makes
// Laravel derive the correct base path and routes resolve normally.
if (isset($_SERVER['SCRIPT_NAME'], $_SERVER['REQUEST_URI'])) {
    $scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');

    if (str_ends_with($scriptDir, '/public')) {
        $root = substr($scriptDir, 0, -strlen('/public'));

        // Only when the request did NOT arrive through /public/ directly.
        if ($root !== ''
            && str_starts_with($_SERVER['REQUEST_URI'], $root.'/')
            && ! str_starts_with($_SERVER['REQUEST_URI'], $scriptDir.'/')) {
            $_SERVER['SCRIPT_NAME'] = $root.'/index.php';
        }
    }
}

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__.'/../vendor/autoload.php';

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

$app->handleRequest(Request::capture());
