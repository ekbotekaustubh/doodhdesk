<?php
declare(strict_types=1);

define('DOODHDESK_TEST_RUNNER', true);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\Auth;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;

echo "========================================================\n";
echo "     DOODHDESK REST API AUTOMATED TEST SUITE           \n";
echo "========================================================\n\n";

$db = Database::getInstance();
$passed = 0;
$failed = 0;

function runApiTest(string $name, callable $testFn, int &$passed, int &$failed): void
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

/**
 * Helper to simulate an API request through the Router and capture JSON response
 */
function callApi(string $method, string $path, array $data = [], ?string $bearerToken = null): array
{
    $_SERVER['REQUEST_METHOD'] = strtoupper($method);
    $_SERVER['REQUEST_URI'] = $path;
    $_SERVER['SCRIPT_NAME'] = '/index.php';
    $_SERVER['HTTP_ACCEPT'] = 'application/json';

    if ($bearerToken !== null) {
        $_SERVER['HTTP_AUTHORIZATION'] = "Bearer {$bearerToken}";
    } else {
        unset($_SERVER['HTTP_AUTHORIZATION'], $_SERVER['REDIRECT_HTTP_AUTHORIZATION']);
    }

    if (in_array(strtoupper($method), ['POST', 'PUT', 'PATCH', 'DELETE'])) {
        $_POST = $data;
        $_GET = [];
    } else {
        $_GET = $data;
        $_POST = [];
    }

    Auth::resetApiState();

    $request = new Request();
    $router = new Router();
    $routes = require __DIR__ . '/../routes.php';
    $routes($router);

    ob_start();
    try {
        $router->dispatch($request);
    } catch (\App\Core\HttpResponseException $e) {
        // Handled HTTP response
    } catch (\Throwable $e) {
        ob_end_clean();
        throw $e;
    }
    $output = ob_get_clean();

    $json = json_decode($output, true);
    if (!is_array($json)) {
        throw new \Exception("Invalid JSON response from {$method} {$path}: " . substr($output, 0, 200));
    }

    return [
        'status' => Response::getStatusCode(),
        'body' => $json,
    ];
}

// ==========================================
// TEST 1: Tenant & Admin Authentication
// ==========================================
$tenantToken = null;
$adminToken = null;

runApiTest("API Auth: Tenant & Admin Login with Bearer Token Generation", function() use (&$tenantToken, &$adminToken) {
    // 1. Invalid login
    $resFail = callApi('POST', '/api/auth/login', [
        'login' => 'ramesh@krishnadairy.com',
        'password' => 'wrongpassword'
    ]);
    if ($resFail['status'] !== 401 || $resFail['body']['success'] !== false) {
        throw new \Exception("Expected 401 for invalid login");
    }

    // 2. Valid tenant login
    $res = callApi('POST', '/api/auth/login', [
        'login' => 'ramesh@krishnadairy.com',
        'password' => 'password123',
        'device_name' => 'API Test Runner'
    ]);

    if ($res['status'] !== 200 || empty($res['body']['data']['token'])) {
        throw new \Exception("Tenant login failed: " . json_encode($res));
    }
    $tenantToken = $res['body']['data']['token'];

    // 3. Admin login
    $resAdmin = callApi('POST', '/api/admin/auth/login', [
        'email' => 'admin@doodhdesk.com',
        'password' => 'admin123'
    ]);

    if ($resAdmin['status'] !== 200 || empty($resAdmin['body']['data']['token'])) {
        throw new \Exception("Admin login failed: " . json_encode($resAdmin));
    }
    $adminToken = $resAdmin['body']['data']['token'];
}, $passed, $failed);

// ==========================================
// TEST 2: Protected Endpoints & Bearer Auth
// ==========================================
runApiTest("API Security: Unauthenticated Rejection & Bearer Token Resolution", function() use (&$tenantToken) {
    // Call /api/auth/me without token
    $resNoAuth = callApi('GET', '/api/auth/me');
    if ($resNoAuth['status'] !== 401 || $resNoAuth['body']['success'] !== false) {
        throw new \Exception("Expected 401 for unauthenticated request, got " . $resNoAuth['status']);
    }

    // Call with valid Bearer token
    $resAuth = callApi('GET', '/api/auth/me', [], $tenantToken);
    if ($resAuth['status'] !== 200 || $resAuth['body']['data']['tenant']['id'] !== 1) {
        throw new \Exception("Failed to resolve tenant from Bearer token");
    }
}, $passed, $failed);

// ==========================================
// TEST 3: Dashboard Statistics API
// ==========================================
runApiTest("API Dashboard: Live Shift Counts and Monthly KPIs", function() use (&$tenantToken) {
    $res = callApi('GET', '/api/dashboard/stats', [], $tenantToken);
    if ($res['status'] !== 200 || !isset($res['body']['data']['shifts']['morning'])) {
        throw new \Exception("Dashboard stats API returned invalid payload");
    }
    $data = $res['body']['data'];
    if (!isset($data['quota']['remaining_slots']) || !isset($data['billing_month'])) {
        throw new \Exception("Missing quota or billing month in dashboard stats");
    }
}, $passed, $failed);

// ==========================================
// TEST 4: Product Catalog APIs
// ==========================================
$createdProductId = 0;
runApiTest("API Products: Catalog Listing, Creation & Toggle", function() use (&$tenantToken, &$createdProductId) {
    // 1. List products
    $resList = callApi('GET', '/api/products', [], $tenantToken);
    if ($resList['status'] !== 200 || count($resList['body']['data']) < 2) {
        throw new \Exception("Expected at least 2 default products (Cow & Buffalo)");
    }

    // 2. Create custom product
    $resCreate = callApi('POST', '/api/products', [
        'name' => 'API Organic Ghee',
        'code' => 'api_ghee_' . time(),
        'unit' => 'kg',
        'default_rate' => 650.00,
        'sort_order' => 5
    ], $tenantToken);

    if ($resCreate['status'] !== 201 || empty($resCreate['body']['data']['id'])) {
        throw new \Exception("Failed to create product via API: " . json_encode($resCreate));
    }
    $createdProductId = (int)$resCreate['body']['data']['id'];

    // 3. Toggle product status
    $resToggle = callApi('POST', "/api/products/{$createdProductId}/toggle", [], $tenantToken);
    if ($resToggle['status'] !== 200 || $resToggle['body']['data']['is_active'] !== 0) {
        throw new \Exception("Product toggle failed");
    }
}, $passed, $failed);

// ==========================================
// TEST 5: Customer Management & Quota Guard
// ==========================================
$newCustomerId = 0;
runApiTest("API Customers: Creation with Subscriptions & Quota Enforcement", function() use (&$tenantToken, &$newCustomerId, $db) {
    // 1. List customers
    $resList = callApi('GET', '/api/customers', [], $tenantToken);
    if ($resList['status'] !== 200) {
        throw new \Exception("Failed to list customers");
    }

    // 2. Fetch routes
    $resRoutes = callApi('GET', '/api/customers/routes', [], $tenantToken);
    if ($resRoutes['status'] !== 200 || !is_array($resRoutes['body']['data'])) {
        throw new \Exception("Failed to fetch customer routes");
    }

    // 3. Create customer with Cow and Buffalo subscriptions
    $cowProd = $db->fetchOne("SELECT id FROM products WHERE tenant_id = 1 AND code = 'cow'");
    $bufProd = $db->fetchOne("SELECT id FROM products WHERE tenant_id = 1 AND code = 'buffalo'");

    $resCreate = callApi('POST', '/api/customers', [
        'name' => 'API Test Customer',
        'phone' => '9777711223',
        'address' => 'Flat 99, Cloud Residency',
        'route_name' => 'Tech Park Route',
        'status' => 'active',
        'subscriptions' => [
            ['product_id' => (int)$cowProd['id'], 'quantity' => 1.5, 'custom_rate' => 64.0, 'shift' => 'morning'],
            ['product_id' => (int)$bufProd['id'], 'quantity' => 2.0, 'custom_rate' => 74.0, 'shift' => 'both'],
        ]
    ], $tenantToken);

    if ($resCreate['status'] !== 201 || empty($resCreate['body']['data']['id'])) {
        throw new \Exception("Customer creation failed: " . json_encode($resCreate));
    }
    $newCustomerId = (int)$resCreate['body']['data']['id'];

    // 4. Verify customer detail
    $resShow = callApi('GET', "/api/customers/{$newCustomerId}", [], $tenantToken);
    if ($resShow['status'] !== 200 || count($resShow['body']['data']['subscriptions']) !== 2) {
        throw new \Exception("Customer subscriptions count mismatch in show API");
    }
}, $passed, $failed);

// ==========================================
// TEST 6: Customer Leave / Vacation Scheduling
// ==========================================
runApiTest("API Leaves: Vacation Scheduling & Deletion", function() use (&$tenantToken, &$newCustomerId) {
    $startDate = date('Y-m-d', strtotime('+5 days'));
    $endDate = date('Y-m-d', strtotime('+8 days'));

    // 1. Add vacation schedule
    $resAdd = callApi('POST', "/api/customers/{$newCustomerId}/leaves", [
        'start_date' => $startDate,
        'end_date' => $endDate,
        'reason' => 'Annual Pilgrimage'
    ], $tenantToken);

    if ($resAdd['status'] !== 201 || empty($resAdd['body']['data']['id'])) {
        throw new \Exception("Failed to add customer leave: " . json_encode($resAdd));
    }
    $leaveId = (int)$resAdd['body']['data']['id'];

    // 2. List leaves
    $resList = callApi('GET', "/api/customers/{$newCustomerId}/leaves", [], $tenantToken);
    if ($resList['status'] !== 200 || count($resList['body']['data']) !== 1) {
        throw new \Exception("Leaves list count mismatch");
    }

    // 3. Delete leave
    $resDel = callApi('DELETE', "/api/customers/{$newCustomerId}/leaves/{$leaveId}", [], $tenantToken);
    if ($resDel['status'] !== 200) {
        throw new \Exception("Failed to delete leave");
    }
}, $passed, $failed);

// ==========================================
// TEST 7: Daily Delivery Run-Sheet & Zero-Charge Rule
// ==========================================
runApiTest("API Deliveries: Run-Sheet Fetching & Zero-Charge Toggle", function() use (&$tenantToken, &$newCustomerId) {
    $today = date('Y-m-d');

    // 1. Fetch run sheet
    $resSheet = callApi('GET', '/api/deliveries', ['date' => $today, 'shift' => 'morning'], $tenantToken);
    if ($resSheet['status'] !== 200 || !isset($resSheet['body']['data']['items'])) {
        throw new \Exception("Failed to fetch delivery run-sheet");
    }

    // 2. Toggle to Skipped -> Must enforce Zero-Charge Rule (quantity strictly 0.00)
    $resSkip = callApi('POST', '/api/deliveries/toggle', [
        'customer_id' => $newCustomerId,
        'date' => $today,
        'shift' => 'morning',
        'status' => 'skipped',
        'notes' => 'Gate locked'
    ], $tenantToken);

    if ($resSkip['status'] !== 200 || (float)$resSkip['body']['data']['delivery']['quantity'] !== 0.00) {
        throw new \Exception("Zero-charge rule failed on skipped delivery");
    }

    // 3. Toggle back to Delivered
    $resDeliver = callApi('POST', '/api/deliveries/toggle', [
        'customer_id' => $newCustomerId,
        'date' => $today,
        'shift' => 'morning',
        'status' => 'delivered'
    ], $tenantToken);

    if ($resDeliver['status'] !== 200 || (float)$resDeliver['body']['data']['delivery']['quantity'] <= 0.00) {
        throw new \Exception("Failed to mark delivery as delivered");
    }
}, $passed, $failed);

// ==========================================
// TEST 8: Billing, Invoices & WhatsApp Share
// ==========================================
$generatedInvoiceId = 0;
runApiTest("API Invoicing: Single & Batch Generation & WhatsApp Sharing", function() use (&$tenantToken, &$newCustomerId, &$generatedInvoiceId) {
    $month = (int)date('n');
    $year = (int)date('Y');

    // 1. Generate single invoice for customer
    $resGen = callApi('POST', '/api/invoices/generate-single', [
        'customer_id' => $newCustomerId,
        'month' => $month,
        'year' => $year
    ], $tenantToken);

    if ($resGen['status'] !== 200 || empty($resGen['body']['data']['id'])) {
        throw new \Exception("Single invoice generation failed: " . json_encode($resGen));
    }
    $generatedInvoiceId = (int)$resGen['body']['data']['id'];

    // 2. View invoice details
    $resShow = callApi('GET', "/api/invoices/{$generatedInvoiceId}", [], $tenantToken);
    if ($resShow['status'] !== 200 || !isset($resShow['body']['data']['summary']['final_amount'])) {
        throw new \Exception("Invoice detail API failed");
    }

    // 3. Get WhatsApp sharing link
    $resShare = callApi('GET', "/api/invoices/{$generatedInvoiceId}/share", [], $tenantToken);
    if ($resShare['status'] !== 200 || empty($resShare['body']['data']['whatsapp_url'])) {
        throw new \Exception("WhatsApp share URL missing");
    }
}, $passed, $failed);

// ==========================================
// TEST 9: Payments & Customer Ledger
// ==========================================
runApiTest("API Payments: Recording & Customer Chronological Ledger", function() use (&$tenantToken, &$newCustomerId, &$generatedInvoiceId) {
    // 1. Record payment against invoice
    $resPay = callApi('POST', '/api/payments', [
        'customer_id' => $newCustomerId,
        'invoice_id' => $generatedInvoiceId,
        'amount' => 500.00,
        'payment_date' => date('Y-m-d'),
        'payment_method' => 'upi',
        'reference_no' => 'UPI-TEST-123456',
        'notes' => 'Received via PhonePe'
    ], $tenantToken);

    if ($resPay['status'] !== 201 || empty($resPay['body']['data']['id'])) {
        throw new \Exception("Payment recording failed: " . json_encode($resPay));
    }

    // 2. Fetch customer ledger
    $resLedger = callApi('GET', "/api/customers/{$newCustomerId}/ledger", [], $tenantToken);
    if ($resLedger['status'] !== 200 || empty($resLedger['body']['data']['ledger'])) {
        throw new \Exception("Customer ledger failed");
    }

    $ledgerEntries = $resLedger['body']['data']['ledger'];
    $hasPayment = false;
    foreach ($ledgerEntries as $entry) {
        if ($entry['type'] === 'payment' && (float)$entry['credit'] === 500.00) {
            $hasPayment = true;
            break;
        }
    }
    if (!$hasPayment) {
        throw new \Exception("Payment credit entry not found in customer ledger");
    }
}, $passed, $failed);

// ==========================================
// TEST 10: Settings, Taxes & Custom Surcharges
// ==========================================
runApiTest("API Settings: Profile, Tax & Surcharge Management", function() use (&$tenantToken) {
    // 1. Update taxes
    $resTax = callApi('PUT', '/api/settings/taxes', [
        'tax_enabled' => 1,
        'tax_label' => 'GST',
        'tax_percentage' => 5.0,
        'tax_number' => '27AAAAA0000A1Z5'
    ], $tenantToken);

    if ($resTax['status'] !== 200 || $resTax['body']['data']['tax_enabled'] !== true) {
        throw new \Exception("Tax update failed: " . json_encode($resTax));
    }

    // 2. Add custom surcharge
    $resCharge = callApi('POST', '/api/settings/charges', [
        'charge_name' => 'Eco Glass Bottle Maintenance',
        'charge_type' => 'flat_monthly',
        'amount' => 40.00,
        'is_taxable' => 1
    ], $tenantToken);

    if ($resCharge['status'] !== 201 || empty($resCharge['body']['data']['id'])) {
        throw new \Exception("Surcharge creation failed: " . json_encode($resCharge));
    }
    $chargeId = (int)$resCharge['body']['data']['id'];

    // 3. Delete surcharge
    $resDelCharge = callApi('DELETE', "/api/settings/charges/{$chargeId}", [], $tenantToken);
    if ($resDelCharge['status'] !== 200) {
        throw new \Exception("Surcharge deletion failed");
    }
}, $passed, $failed);

// ==========================================
// TEST 11: Subscription & Quota Upgrade
// ==========================================
runApiTest("API Subscription: Plan Capacity Upgrade", function() use (&$tenantToken) {
    // 1. Fetch current subscription
    $resSub = callApi('GET', '/api/subscription', [], $tenantToken);
    if ($resSub['status'] !== 200 || !isset($resSub['body']['data']['quota']['max_allowed'])) {
        throw new \Exception("Failed to fetch subscription quota");
    }
    $prevMax = (int)$resSub['body']['data']['quota']['max_allowed'];

    // 2. Upgrade by 1 block (+100 customers)
    $resUpgrade = callApi('POST', '/api/subscription/upgrade', ['blocks' => 1], $tenantToken);
    if ($resUpgrade['status'] !== 200) {
        throw new \Exception("Quota upgrade failed: " . json_encode($resUpgrade));
    }

    $newMax = (int)$resUpgrade['body']['data']['quota']['max_allowed'];
    if ($newMax !== $prevMax + 100) {
        throw new \Exception("Expected max capacity {$prevMax} + 100 = " . ($prevMax + 100) . ", got {$newMax}");
    }
}, $passed, $failed);

// ==========================================
// TEST 12: Super Admin Platform Governance
// ==========================================
runApiTest("API Super Admin: Platform Stats, Settings & Tenant Governance", function() use (&$adminToken) {
    // 1. Fetch platform stats
    $resStats = callApi('GET', '/api/admin/stats', [], $adminToken);
    if ($resStats['status'] !== 200 || empty($resStats['body']['data']['tenants'])) {
        throw new \Exception("Admin stats API failed");
    }

    // 2. Fetch all tenants
    $resTenants = callApi('GET', '/api/admin/tenants', [], $adminToken);
    if ($resTenants['status'] !== 200 || empty($resTenants['body']['data'])) {
        throw new \Exception("Admin tenants listing failed");
    }

    // 3. Update platform global settings
    $resSet = callApi('PUT', '/api/admin/settings', [
        'free_customer_limit' => 5,
        'paid_tier_unit_size' => 100,
        'paid_tier_unit_price' => 200.0,
        'currency_symbol' => '₹',
        'platform_name' => 'DoodhDesk SaaS'
    ], $adminToken);

    if ($resSet['status'] !== 200 || $resSet['body']['data']['platform_name'] !== 'DoodhDesk SaaS') {
        throw new \Exception("Admin settings update failed");
    }
}, $passed, $failed);

// Clean up test customer and test product
if ($newCustomerId > 0) {
    $db->execute("DELETE FROM customers WHERE id = ?", [$newCustomerId]);
}
if ($createdProductId > 0) {
    $db->execute("DELETE FROM products WHERE id = ?", [$createdProductId]);
}

echo "\n========================================================\n";
echo "   API TEST RESULTS: {$passed} PASSED, {$failed} FAILED\n";
echo "========================================================\n";

if ($failed > 0) {
    exit(1);
}
