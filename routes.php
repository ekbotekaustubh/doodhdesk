<?php
declare(strict_types=1);

use App\Controllers\AuthController;
use App\Controllers\CustomerController;
use App\Controllers\DashboardController;
use App\Controllers\DeliveryController;
use App\Controllers\InvoiceController;
use App\Controllers\SettingsController;
use App\Controllers\SuperAdminController;
use App\Core\Router;

return function (Router $router): void {
    // 1. Auth & Demo Switching
    $router->get('/login', [AuthController::class, 'loginView']);
    $router->post('/login', [AuthController::class, 'login']);
    $router->get('/register', [AuthController::class, 'registerView']);
    $router->post('/register', [AuthController::class, 'register']);
    $router->get('/logout', [AuthController::class, 'logout']);
    $router->get('/demo/{role}', [AuthController::class, 'demoSwitch']);

    // Admin Auth
    $router->get('/admin/login', [AuthController::class, 'adminLoginView']);
    $router->post('/admin/login', [AuthController::class, 'adminLogin']);

    // 2. Tenant Dashboard
    $router->get('/', [DashboardController::class, 'index'], ['tenant']);

    // 3. Module 1: Daily Delivery Tracker (Core Anti-Error Engine)
    $router->get('/deliveries', [DeliveryController::class, 'index'], ['tenant']);
    $router->post('/deliveries/toggle', [DeliveryController::class, 'toggle'], ['tenant']);
    $router->post('/deliveries/edit-quantity', [DeliveryController::class, 'editQuantity'], ['tenant']);
    $router->post('/deliveries/bulk-deliver', [DeliveryController::class, 'markAllDelivered'], ['tenant']);
    $router->get('/deliveries/stats-json', [DeliveryController::class, 'getStatsJson'], ['tenant']);

    // 4. Customers & Vacation Management (Quota Guarded)
    $router->get('/customers', [CustomerController::class, 'index'], ['tenant']);
    $router->get('/customers/create', [CustomerController::class, 'createView'], ['tenant']);
    $router->get('/customers/export', [CustomerController::class, 'exportCsv'], ['tenant']);
    $router->get('/customers/sample-csv', [CustomerController::class, 'sampleCsv'], ['tenant']);
    $router->post('/customers/import', [CustomerController::class, 'importCsv'], ['tenant']);
    $router->post('/customers', [CustomerController::class, 'store'], ['tenant']);
    $router->get('/customers/{id}/edit', [CustomerController::class, 'editView'], ['tenant']);
    $router->post('/customers/{id}', [CustomerController::class, 'update'], ['tenant']);
    $router->post('/customers/{id}/leaves', [CustomerController::class, 'addLeave'], ['tenant']);
    $router->post('/customers/{id}/leaves/{leave_id}/delete', [CustomerController::class, 'deleteLeave'], ['tenant']);

    // 5. Module 2: Billing & Invoicing Engine
    $router->get('/invoices', [InvoiceController::class, 'index'], ['tenant']);
    $router->post('/invoices/generate-batch', [InvoiceController::class, 'generateBatch'], ['tenant']);
    $router->post('/invoices/generate-single', [InvoiceController::class, 'generateSingle'], ['tenant']);
    $router->get('/invoices/{id}', [InvoiceController::class, 'show'], ['tenant']);
    $router->get('/invoices/{id}/a4', [InvoiceController::class, 'printA4'], ['tenant']);
    $router->get('/invoices/{id}/thermal', [InvoiceController::class, 'printThermal'], ['tenant']);
    $router->get('/invoices/{id}/pdf', [InvoiceController::class, 'downloadPdf'], ['tenant']);
    $router->post('/invoices/{id}/payment', [InvoiceController::class, 'recordPayment'], ['tenant']);

    // 6. Settings, Taxes, Surcharges & Subscription
    $router->get('/settings', [SettingsController::class, 'index'], ['tenant']);
    $router->post('/settings/profile', [SettingsController::class, 'updateProfile'], ['tenant']);
    $router->post('/settings/taxes', [SettingsController::class, 'updateTaxes'], ['tenant']);
    $router->post('/settings/products', [SettingsController::class, 'addProduct'], ['tenant']);
    $router->post('/settings/products/{id}/toggle', [SettingsController::class, 'toggleProduct'], ['tenant']);
    $router->post('/settings/products/{id}/delete', [SettingsController::class, 'deleteProduct'], ['tenant']);
    $router->post('/settings/charges', [SettingsController::class, 'addCharge'], ['tenant']);
    $router->post('/settings/charges/{id}/toggle', [SettingsController::class, 'toggleCharge'], ['tenant']);
    $router->post('/settings/charges/{id}/delete', [SettingsController::class, 'deleteCharge'], ['tenant']);
    $router->get('/subscription', [SettingsController::class, 'subscriptionView'], ['tenant']);
    $router->post('/subscription/upgrade', [SettingsController::class, 'upgradeQuota'], ['tenant']);

    // 7. Super Admin SaaS Platform Governance
    $router->get('/admin', [SuperAdminController::class, 'index'], ['admin']);
    $router->post('/admin/settings', [SuperAdminController::class, 'updateSettings'], ['admin']);
    $router->post('/admin/tenants/{id}/toggle-status', [SuperAdminController::class, 'toggleTenantStatus'], ['admin']);
    $router->post('/admin/tenants/{id}/quota', [SuperAdminController::class, 'adjustQuota'], ['admin']);

    // 8. REST API Endpoints (Mobile & Headless)
    $apiRoutes = require __DIR__ . '/routes_api.php';
    $apiRoutes($router);
};
