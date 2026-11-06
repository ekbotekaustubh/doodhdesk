<?php
declare(strict_types=1);

use App\Controllers\Api\AdminApiController;
use App\Controllers\Api\AuthApiController;
use App\Controllers\Api\CustomerApiController;
use App\Controllers\Api\DashboardApiController;
use App\Controllers\Api\DeliveryApiController;
use App\Controllers\Api\InvoiceApiController;
use App\Controllers\Api\LeaveApiController;
use App\Controllers\Api\PaymentApiController;
use App\Controllers\Api\ProductApiController;
use App\Controllers\Api\SettingsApiController;
use App\Controllers\Api\SubscriptionApiController;
use App\Core\Router;

return function (Router $router): void {
    // 1. Authentication
    $router->post('/api/auth/login', [AuthApiController::class, 'login']);
    $router->post('/api/auth/register', [AuthApiController::class, 'register']);
    $router->get('/api/auth/me', [AuthApiController::class, 'me'], ['api.tenant']);
    $router->post('/api/auth/logout', [AuthApiController::class, 'logout'], ['api.tenant']);
    $router->post('/api/admin/auth/login', [AuthApiController::class, 'adminLogin']);

    // 2. Dashboard KPIs
    $router->get('/api/dashboard/stats', [DashboardApiController::class, 'stats'], ['api.tenant']);

    // 3. Products
    $router->get('/api/products', [ProductApiController::class, 'index'], ['api.tenant']);
    $router->post('/api/products', [ProductApiController::class, 'store'], ['api.tenant']);
    $router->get('/api/products/{id}', [ProductApiController::class, 'show'], ['api.tenant']);
    $router->put('/api/products/{id}', [ProductApiController::class, 'update'], ['api.tenant']);
    $router->post('/api/products/{id}', [ProductApiController::class, 'update'], ['api.tenant']);
    $router->post('/api/products/{id}/toggle', [ProductApiController::class, 'toggle'], ['api.tenant']);
    $router->delete('/api/products/{id}', [ProductApiController::class, 'destroy'], ['api.tenant']);

    // 4. Customers
    $router->get('/api/customers', [CustomerApiController::class, 'index'], ['api.tenant']);
    $router->get('/api/customers/routes', [CustomerApiController::class, 'routes'], ['api.tenant']);
    $router->get('/api/customers/{id}', [CustomerApiController::class, 'show'], ['api.tenant']);
    $router->post('/api/customers', [CustomerApiController::class, 'store'], ['api.tenant']);
    $router->put('/api/customers/{id}', [CustomerApiController::class, 'update'], ['api.tenant']);
    $router->post('/api/customers/{id}', [CustomerApiController::class, 'update'], ['api.tenant']);
    $router->post('/api/customers/{id}/status', [CustomerApiController::class, 'toggleStatus'], ['api.tenant']);

    // 5. Customer Leaves / Vacations
    $router->get('/api/customers/{id}/leaves', [LeaveApiController::class, 'index'], ['api.tenant']);
    $router->post('/api/customers/{id}/leaves', [LeaveApiController::class, 'store'], ['api.tenant']);
    $router->delete('/api/customers/{id}/leaves/{leave_id}', [LeaveApiController::class, 'destroy'], ['api.tenant']);

    // 6. Delivery Run-Sheet (Anti-Dispute Engine)
    $router->get('/api/deliveries', [DeliveryApiController::class, 'index'], ['api.tenant']);
    $router->post('/api/deliveries/toggle', [DeliveryApiController::class, 'toggle'], ['api.tenant']);
    $router->post('/api/deliveries/edit-quantity', [DeliveryApiController::class, 'editQuantity'], ['api.tenant']);
    $router->post('/api/deliveries/bulk-deliver', [DeliveryApiController::class, 'bulkDeliver'], ['api.tenant']);
    $router->get('/api/deliveries/summary', [DeliveryApiController::class, 'summary'], ['api.tenant']);

    // 7. Billing & Invoices
    $router->get('/api/invoices', [InvoiceApiController::class, 'index'], ['api.tenant']);
    $router->get('/api/invoices/{id}', [InvoiceApiController::class, 'show'], ['api.tenant']);
    $router->post('/api/invoices/generate-batch', [InvoiceApiController::class, 'generateBatch'], ['api.tenant']);
    $router->post('/api/invoices/generate-single', [InvoiceApiController::class, 'generateSingle'], ['api.tenant']);
    $router->get('/api/invoices/{id}/share', [InvoiceApiController::class, 'share'], ['api.tenant']);

    // 8. Payments & Ledger
    $router->get('/api/invoices/{id}/payments', [PaymentApiController::class, 'invoicePayments'], ['api.tenant']);
    $router->post('/api/payments', [PaymentApiController::class, 'store'], ['api.tenant']);
    $router->get('/api/customers/{id}/ledger', [PaymentApiController::class, 'customerLedger'], ['api.tenant']);

    // 9. Settings, Taxes & Charges
    $router->get('/api/settings', [SettingsApiController::class, 'getSettings'], ['api.tenant']);
    $router->put('/api/settings/profile', [SettingsApiController::class, 'updateProfile'], ['api.tenant']);
    $router->post('/api/settings/profile', [SettingsApiController::class, 'updateProfile'], ['api.tenant']);
    $router->put('/api/settings/taxes', [SettingsApiController::class, 'updateTaxes'], ['api.tenant']);
    $router->post('/api/settings/taxes', [SettingsApiController::class, 'updateTaxes'], ['api.tenant']);
    $router->put('/api/settings/upi', [SettingsApiController::class, 'updateUpi'], ['api.tenant']);
    $router->post('/api/settings/upi', [SettingsApiController::class, 'updateUpi'], ['api.tenant']);
    $router->get('/api/settings/charges', [SettingsApiController::class, 'getCharges'], ['api.tenant']);
    $router->post('/api/settings/charges', [SettingsApiController::class, 'addCharge'], ['api.tenant']);
    $router->post('/api/settings/charges/{id}/toggle', [SettingsApiController::class, 'toggleCharge'], ['api.tenant']);
    $router->delete('/api/settings/charges/{id}', [SettingsApiController::class, 'deleteCharge'], ['api.tenant']);

    // 10. Subscription & Quota
    $router->get('/api/subscription', [SubscriptionApiController::class, 'getSubscription'], ['api.tenant']);
    $router->post('/api/subscription/upgrade', [SubscriptionApiController::class, 'upgradeQuota'], ['api.tenant']);

    // 11. Super Admin Platform Governance
    $router->get('/api/admin/stats', [AdminApiController::class, 'stats'], ['api.admin']);
    $router->get('/api/admin/settings', [AdminApiController::class, 'getSettings'], ['api.admin']);
    $router->put('/api/admin/settings', [AdminApiController::class, 'updateSettings'], ['api.admin']);
    $router->post('/api/admin/settings', [AdminApiController::class, 'updateSettings'], ['api.admin']);
    $router->get('/api/admin/tenants', [AdminApiController::class, 'getTenants'], ['api.admin']);
    $router->post('/api/admin/tenants/{id}/toggle-status', [AdminApiController::class, 'toggleTenantStatus'], ['api.admin']);
    $router->post('/api/admin/tenants/{id}/quota', [AdminApiController::class, 'adjustQuota'], ['api.admin']);
};
