<?php

declare(strict_types=1);

/**
 * Front controller: every request enters the application here.
 */

define('BASE_PATH', dirname(__DIR__));
define('VIEWS_PATH', BASE_PATH . '/storage/views');

// URL prefix of this app (e.g. "" at document root, "/sport_management/public" under XAMPP).
define('BASE_URL', rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/'));

require BASE_PATH . '/vendor/autoload.php';

session_start();

$config = require BASE_PATH . '/config/config.php';

use App\Core\Request;
use App\Core\Router;

$request = new Request();
$router = new Router($request);

require BASE_PATH . '/routes/web.php';

$router->dispatch();
