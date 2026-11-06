<?php
declare(strict_types=1);

define('DOODHDESK_TEST_RUNNER', true);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Controllers\AuthController;
use App\Controllers\CustomerController;
use App\Controllers\DashboardController;
use App\Controllers\DeliveryController;
use App\Controllers\InvoiceController;
use App\Controllers\SettingsController;
use App\Controllers\SuperAdminController;
use App\Core\Auth;
use App\Core\Request;

echo "========================================================\n";
echo "   DOODHDESK WEB-TO-API INTEGRATION TEST SUITE          \n";
echo "========================================================\n\n";

$passed = 0;
$failed = 0;

function runWebTest(string $name, callable $testFn, int &$passed, int &$failed): void
{
    echo "• Running: {$name}... ";
    try {
        $testFn();
        echo "\033[32m[PASS]\033[0m\n";
        $passed++;
    } catch (\Throwable $e) {
        echo "\033[31m[FAIL]\033[0m\n";
        echo "  Error: " . $e->getMessage() . "\n";
        echo "  File: " . $e->getFile() . ":" . $e->getLine() . "\n";
        $failed++;
    }
}

Auth::startSession();

// TEST 1: Dashboard Controller API Delegation
runWebTest("DashboardController: Fetches KPIs & Shifts via API and Renders", function () {
    $_SESSION['tenant'] = [
        'id' => 1,
        'business_name' => 'Krushna Dairy Farm',
        'owner_name' => 'Ramesh',
        'phone' => '9822000001',
        'status' => 'active'
    ];

    ob_start();
    $controller = new DashboardController();
    $controller->index(new Request());
    $html = ob_get_clean();

    if (!str_contains($html, 'Krushna Dairy Farm') || !str_contains($html, 'Dashboard')) {
        throw new \Exception("Dashboard failed to render data fetched from API");
    }
}, $passed, $failed);

// TEST 2: Delivery Controller API Delegation
runWebTest("DeliveryController: Run-Sheet Rendering & API Toggle", function () {
    ob_start();
    $controller = new DeliveryController();
    $controller->index(new Request());
    $html = ob_get_clean();

    if (!str_contains($html, 'Run-Sheet')) {
        throw new \Exception("Delivery run-sheet failed to render data from /api/deliveries");
    }
}, $passed, $failed);

// TEST 3: Customer Controller API Delegation
runWebTest("CustomerController: Customer Roster & Routes via API", function () {
    ob_start();
    $controller = new CustomerController();
    $controller->index(new Request());
    $html = ob_get_clean();

    if (!str_contains($html, 'Customer')) {
        throw new \Exception("Customer roster failed to render data from /api/customers");
    }
}, $passed, $failed);

// TEST 4: Invoice Controller API Delegation
runWebTest("InvoiceController: Invoices List & Show via API", function () {
    ob_start();
    $controller = new InvoiceController();
    $controller->index(new Request());
    $html = ob_get_clean();

    if (!str_contains($html, 'Invoices')) {
        throw new \Exception("Invoice list failed to render data from /api/invoices");
    }

    // Test invoice show
    $db = \App\Core\Database::getInstance();
    $invRow = $db->fetchOne("SELECT id FROM invoices WHERE tenant_id = 1 LIMIT 1");
    $invId = (int)($invRow['id'] ?? 19);

    ob_start();
    $controller->show(new Request(), ['id' => $invId]);
    $showHtml = ob_get_clean();
    if (!str_contains($showHtml, 'Invoice #') && !str_contains($showHtml, 'Tax Invoice')) {
        throw new \Exception("Invoice show failed to render invoice details from /api/invoices/{$invId}");
    }
}, $passed, $failed);

// TEST 5: Settings Controller API Delegation
runWebTest("SettingsController: Settings, Taxes & Charges via API", function () {
    ob_start();
    $controller = new SettingsController();
    $controller->index(new Request());
    $html = ob_get_clean();

    if (!str_contains($html, 'Settings')) {
        throw new \Exception("Settings failed to render data from /api/settings");
    }
}, $passed, $failed);

// TEST 6: SuperAdmin Controller API Delegation
runWebTest("SuperAdminController: Platform Governance via API", function () {
    $_SESSION['admin'] = [
        'id' => 1,
        'name' => 'Super Admin',
        'email' => 'admin@dairyflow.com'
    ];

    ob_start();
    $controller = new SuperAdminController();
    $controller->index(new Request());
    $html = ob_get_clean();

    if (!str_contains($html, 'Admin') && !str_contains($html, 'Platform')) {
        throw new \Exception("SuperAdmin portal failed to render data from /api/admin/*");
    }
}, $passed, $failed);

// TEST 7: Zero Direct Database References Verification
runWebTest("Architecture Audit: Ensure ZERO Direct SQL/Database in Web Controllers", function () {
    $webControllers = [
        dirname(__DIR__) . '/src/Controllers/AuthController.php',
        dirname(__DIR__) . '/src/Controllers/CustomerController.php',
        dirname(__DIR__) . '/src/Controllers/DashboardController.php',
        dirname(__DIR__) . '/src/Controllers/DeliveryController.php',
        dirname(__DIR__) . '/src/Controllers/InvoiceController.php',
        dirname(__DIR__) . '/src/Controllers/SettingsController.php',
        dirname(__DIR__) . '/src/Controllers/SuperAdminController.php',
    ];

    foreach ($webControllers as $filePath) {
        $content = file_get_contents($filePath);
        if (str_contains($content, 'Database::getInstance')) {
            throw new \Exception("Direct Database::getInstance found in " . basename($filePath));
        }
        if (str_contains($content, '$this->db')) {
            throw new \Exception("Direct \$this->db reference found in " . basename($filePath));
        }
        if (str_contains($content, '->fetchAll(') || str_contains($content, '->fetchOne(') || str_contains($content, '->execute(')) {
            throw new \Exception("Direct SQL query execution found in " . basename($filePath));
        }
    }
}, $passed, $failed);

echo "\n========================================================\n";
echo "   INTEGRATION TEST RESULTS: {$passed} PASSED, {$failed} FAILED\n";
echo "========================================================\n";

if ($failed > 0) {
    exit(1);
}
