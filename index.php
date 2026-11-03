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

// Auto-seed or auto-login to Tenant 1 for frictionless preview if no session exists yet
if (!Auth::isTenant() && !Auth::isAdmin() && empty($_GET['msg']) && !in_array($_SERVER['REQUEST_URI'] ?? '', ['/login', '/register', '/admin/login'])) {
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

$request = new Request();
$router = new Router();

$routes = require __DIR__ . '/routes.php';
$routes($router);

$router->dispatch($request);
