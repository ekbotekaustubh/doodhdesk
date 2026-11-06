<?php
declare(strict_types=1);

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/vendor/autoload.php';

use App\Core\Auth;
use App\Core\Database;
use App\Core\Request;
use App\Core\Router;

Auth::startSession();

$request = new Request();
$path = $request->path();

$authPaths = ['/login', '/register', '/admin/login', '/logout'];
$isAuthPath = in_array($path, $authPaths, true) || str_starts_with($path, '/demo');

// Auto-seed or auto-login to Tenant 1 for frictionless preview if no session exists yet,
// but ONLY when accessing the root dashboard directly, NEVER on auth or demo routes
if (!Auth::isTenant() && !Auth::isAdmin() && empty($_GET['msg']) && !$isAuthPath && ($path === '/' || $path === '')) {
    try {
        $db = Database::getInstance();
        $defaultTenant = $db->fetchOne("SELECT * FROM tenants WHERE id = 1 AND status = 'active'");
        if ($defaultTenant) {
            Auth::loginTenant($defaultTenant);
        }
    } catch (\Throwable $e) {
        // Fallback gracefully if db error
    }
}

$router = new Router();

$routes = require __DIR__ . '/routes.php';
$routes($router);

$router->dispatch($request);
