<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Auth;
use App\Core\Request;
use App\Services\ProductService;
use App\Services\QuotaService;

final class AuthApiController extends ApiBaseController
{
    private QuotaService $quotaService;
    private ProductService $productService;

    public function __construct()
    {
        parent::__construct();
        $this->quotaService = new QuotaService();
        $this->productService = new ProductService();
    }

    public function login(Request $request): void
    {
        $login = trim((string)$request->input('login', ''));
        $password = (string)$request->input('password', '');
        $deviceName = trim((string)$request->input('device_name', 'Mobile App'));

        $errors = $this->validate(['login' => $login, 'password' => $password], [
            'login' => 'required',
            'password' => 'required'
        ]);

        if (!empty($errors)) {
            $this->jsonError('Validation failed', 422, 'VALIDATION_ERROR', $errors);
        }

        $tenant = $this->db->fetchOne(
            "SELECT * FROM tenants WHERE phone = ? OR email = ?",
            [$login, $login]
        );

        if (!$tenant || !password_verify($password, $tenant['password_hash'])) {
            $this->jsonError('Invalid phone/email or password.', 401, 'INVALID_CREDENTIALS');
        }

        if ($tenant['status'] === 'suspended') {
            $this->jsonError('Account is suspended. Please contact platform administration.', 403, 'ACCOUNT_SUSPENDED');
        }

        // Generate Bearer token
        $token = Auth::createApiToken((int)$tenant['id'], 'tenant', $deviceName, 90);
        $quota = $this->quotaService->getTenantQuota((int)$tenant['id']);

        $this->jsonSuccess([
            'token' => $token,
            'token_type' => 'Bearer',
            'expires_in_days' => 90,
            'tenant' => [
                'id' => (int)$tenant['id'],
                'business_name' => $tenant['business_name'],
                'owner_name' => $tenant['owner_name'],
                'phone' => $tenant['phone'],
                'email' => $tenant['email'],
                'status' => $tenant['status'],
                'created_at' => $tenant['created_at'],
            ],
            'quota' => $quota,
        ], 'Login successful');
    }

    public function register(Request $request): void
    {
        $businessName = trim((string)$request->input('business_name', ''));
        $ownerName = trim((string)$request->input('owner_name', ''));
        $phone = trim((string)$request->input('phone', ''));
        $email = trim((string)$request->input('email', ''));
        $password = (string)$request->input('password', '');
        $deviceName = trim((string)$request->input('device_name', 'Mobile App'));

        $errors = $this->validate([
            'business_name' => $businessName,
            'owner_name' => $ownerName,
            'phone' => $phone,
            'password' => $password,
        ], [
            'business_name' => 'required',
            'owner_name' => 'required',
            'phone' => 'required',
            'password' => 'required',
        ]);

        if (strlen($password) < 6) {
            $errors['password'] = 'Password must be at least 6 characters.';
        }

        if (!empty($errors)) {
            $this->jsonError('Validation failed', 422, 'VALIDATION_ERROR', $errors);
        }

        $existing = $this->db->fetchOne("SELECT id FROM tenants WHERE phone = ?", [$phone]);
        if ($existing) {
            $this->jsonError('Phone number already registered. Please login.', 422, 'PHONE_TAKEN');
        }

        if (!empty($email)) {
            $existingEmail = $this->db->fetchOne("SELECT id FROM tenants WHERE email = ?", [$email]);
            if ($existingEmail) {
                $this->jsonError('Email already registered.', 422, 'EMAIL_TAKEN');
            }
        }

        $tenantId = 0;
        $this->db->transaction(function () use ($businessName, $ownerName, $phone, $email, $password, &$tenantId) {
            $passwordHash = password_hash($password, PASSWORD_BCRYPT);
            $this->db->execute(
                "INSERT INTO tenants (business_name, owner_name, phone, email, password_hash, status)
                 VALUES (?, ?, ?, ?, ?, 'active')",
                [$businessName, $ownerName, $phone, empty($email) ? null : $email, $passwordHash]
            );
            $tenantId = (int)$this->db->lastInsertId();

            $this->db->execute(
                "INSERT INTO tenant_settings (tenant_id, tax_enabled, tax_label, tax_percentage)
                 VALUES (?, 0, 'GST', 5.00)",
                [$tenantId]
            );

            $freeLimit = $this->quotaService->getFreeCustomerLimit();
            $this->db->execute(
                "INSERT INTO tenant_subscriptions (tenant_id, purchased_blocks, max_customers_allowed, amount_paid, payment_status)
                 VALUES (?, 0, ?, 0.00, 'free')",
                [$tenantId, $freeLimit]
            );

            // Default products
            $this->productService->ensureDefaultProducts($tenantId);
        });

        $tenant = $this->db->fetchOne("SELECT * FROM tenants WHERE id = ?", [$tenantId]);
        $token = Auth::createApiToken($tenantId, 'tenant', $deviceName, 90);
        $quota = $this->quotaService->getTenantQuota($tenantId);

        $this->jsonSuccess([
            'token' => $token,
            'token_type' => 'Bearer',
            'expires_in_days' => 90,
            'tenant' => [
                'id' => (int)$tenant['id'],
                'business_name' => $tenant['business_name'],
                'owner_name' => $tenant['owner_name'],
                'phone' => $tenant['phone'],
                'email' => $tenant['email'],
                'status' => $tenant['status'],
                'created_at' => $tenant['created_at'],
            ],
            'quota' => $quota,
        ], 'Registration successful. Free plan active.', 201);
    }

    public function me(Request $request): void
    {
        $tenant = $this->tenant();
        if (!$tenant) {
            $this->jsonError('Unauthenticated', 401, 'UNAUTHENTICATED');
        }

        $tenantId = (int)$tenant['id'];
        $freshTenant = $this->db->fetchOne("SELECT id, business_name, owner_name, phone, email, status, created_at FROM tenants WHERE id = ?", [$tenantId]);
        $quota = $this->quotaService->getTenantQuota($tenantId);
        $settings = $this->db->fetchOne("SELECT tax_enabled, tax_label, tax_percentage, tax_number, upi_id, invoice_footer_note FROM tenant_settings WHERE tenant_id = ?", [$tenantId]);

        $this->jsonSuccess([
            'tenant' => $freshTenant,
            'quota' => $quota,
            'settings' => $settings,
        ]);
    }

    public function logout(Request $request): void
    {
        Auth::revokeCurrentApiToken();
        Auth::logout();
        $this->jsonSuccess(null, 'Logged out successfully');
    }

    public function adminLogin(Request $request): void
    {
        $email = trim((string)$request->input('email', ''));
        $password = (string)$request->input('password', '');
        $deviceName = trim((string)$request->input('device_name', 'Admin Console'));

        $errors = $this->validate(['email' => $email, 'password' => $password], [
            'email' => 'required|email',
            'password' => 'required'
        ]);

        if (!empty($errors)) {
            $this->jsonError('Validation failed', 422, 'VALIDATION_ERROR', $errors);
        }

        $admin = $this->db->fetchOne("SELECT * FROM super_admins WHERE email = ?", [$email]);
        if (!$admin || !password_verify($password, $admin['password_hash'])) {
            $this->jsonError('Invalid admin email or password.', 401, 'INVALID_CREDENTIALS');
        }

        $token = Auth::createApiToken((int)$admin['id'], 'admin', $deviceName, 30);

        $this->jsonSuccess([
            'token' => $token,
            'token_type' => 'Bearer',
            'expires_in_days' => 30,
            'admin' => [
                'id' => (int)$admin['id'],
                'name' => $admin['name'],
                'email' => $admin['email'],
            ]
        ], 'Admin authentication successful');
    }
}
