<?php
declare(strict_types=1);

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\Auth;
use App\Core\Request;
use App\Core\Router;

Auth::startSession();

$request = new Request();
$router = new Router();

$routes = require __DIR__ . '/../routes.php';
$routes($router);

$router->dispatch($request);
