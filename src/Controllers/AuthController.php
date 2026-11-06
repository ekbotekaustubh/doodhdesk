<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Services\QuotaService;

final class AuthController
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function loginView(Request $request): void
    {
        if (Auth::isTenant()) {
            Response::redirect(url('/'));
        }
        View::render('auth/login', [
            'error' => Auth::flash('error'),
            'success' => Auth::flash('success'),
        ], 'auth');
    }

    public function login(Request $request): void
    {
        $loginInput = trim((string)$request->post('login', ''));
        $password = (string)$request->post('password', '');

        if (empty($loginInput) || empty($password)) {
            Auth::flash('error', 'Please enter your phone/email and password.');
            Response::redirect(url('/login'));
        }

        $tenant = $this->db->fetchOne(
            "SELECT * FROM tenants WHERE phone = ? OR email = ?",
            [$loginInput, $loginInput]
        );

        if (!$tenant || !password_verify($password, (string)$tenant['password_hash'])) {
            Auth::flash('error', 'Invalid login credentials. Please try again.');
            Response::redirect(url('/login'));
        }

        if ($tenant['status'] === 'suspended') {
            Auth::flash('error', 'Your account has been suspended by the platform administrator.');
            Response::redirect(url('/login'));
        }

        Auth::loginTenant($tenant);
        Auth::flash('success', "Welcome back, {$tenant['owner_name']}!");
        Response::redirect(url('/'));
    }

    public function registerView(Request $request): void
    {
        if (Auth::isTenant()) {
            Response::redirect(url('/'));
        }
        View::render('auth/register', [
            'error' => Auth::flash('error')
        ], 'auth');
    }

    public function register(Request $request): void
    {
        $businessName = trim((string)$request->post('business_name', ''));
        $ownerName = trim((string)$request->post('owner_name', ''));
        $phone = trim((string)$request->post('phone', ''));
        $email = trim((string)$request->post('email', ''));
        $password = (string)$request->post('password', '');

        if (empty($businessName) || empty($ownerName) || empty($phone) || empty($password)) {
            Auth::flash('error', 'Please fill in all required fields.');
            Response::redirect(url('/register'));
        }

        // Check duplicate
        $existing = $this->db->fetchOne("SELECT id FROM tenants WHERE phone = ?", [$phone]);
        if ($existing) {
            Auth::flash('error', 'A dairy is already registered with this phone number.');
            Response::redirect(url('/register'));
        }

        $passwordHash = password_hash($password, PASSWORD_BCRYPT);

        // Fetch free customer limit from system settings
        $quotaService = new QuotaService();
        $sys = $quotaService->getSystemSettings();
        $freeLimit = (int)$sys['free_customer_limit'];

        $this->db->transaction(function() use ($businessName, $ownerName, $phone, $email, $passwordHash, $freeLimit) {
            $this->db->execute(
                "INSERT INTO tenants (business_name, owner_name, phone, email, password_hash, status)
                 VALUES (?, ?, ?, ?, ?, 'active')",
                [$businessName, $ownerName, $phone, empty($email) ? null : $email, $passwordHash]
            );
            $tenantId = $this->db->lastInsertId();

            // Default tenant settings
            $this->db->execute(
                "INSERT INTO tenant_settings (tenant_id, tax_enabled, tax_label, tax_percentage)
                 VALUES (?, 0, 'GST', 0.00)",
                [$tenantId]
            );

            // Default subscription (Free Tier)
            $this->db->execute(
                "INSERT INTO tenant_subscriptions (tenant_id, purchased_blocks, max_customers_allowed, amount_paid, payment_status)
                 VALUES (?, 0, ?, 0.00, 'free')",
                [$tenantId, $freeLimit]
            );

            $tenant = $this->db->fetchOne("SELECT * FROM tenants WHERE id = ?", [$tenantId]);
            Auth::loginTenant($tenant);
        });

        Auth::flash('success', "Welcome to DairyFlow! Your free account with {$freeLimit} customer slots is ready.");
        Response::redirect(url('/'));
    }

    public function adminLoginView(Request $request): void
    {
        if (Auth::isAdmin()) {
            Response::redirect(url('/admin'));
        }
        View::render('superadmin/login', [
            'error' => Auth::flash('error')
        ], 'auth');
    }

    public function adminLogin(Request $request): void
    {
        $email = trim((string)$request->post('email', ''));
        $password = (string)$request->post('password', '');

        $admin = $this->db->fetchOne("SELECT * FROM super_admins WHERE email = ?", [$email]);
        if (!$admin || !password_verify($password, (string)$admin['password_hash'])) {
            Auth::flash('error', 'Invalid admin credentials.');
            Response::redirect(url('/admin/login'));
        }

        Auth::loginAdmin($admin);
        Response::redirect(url('/admin'));
    }

    public function demoSwitch(Request $request, array $params): void
    {
        $role = $params['role'] ?? 'tenant1';

        if ($role === 'tenant1') {
            $t = $this->db->fetchOne("SELECT * FROM tenants WHERE id = 1");
            if ($t) {
                Auth::loginTenant($t);
                Auth::flash('success', 'Logged in as Ramesh Patel (Krishna Pure Milk Dairy - Paid Tier Demo)');
            }
            Response::redirect(url('/'));
        } elseif ($role === 'tenant2') {
            $t = $this->db->fetchOne("SELECT * FROM tenants WHERE id = 2");
            if ($t) {
                Auth::loginTenant($t);
                Auth::flash('success', 'Logged in as Suresh Sharma (Radha Cow Dairy - Free Tier Demo, 4/5 Customers)');
            }
            Response::redirect(url('/'));
        } elseif ($role === 'admin') {
            $a = $this->db->fetchOne("SELECT * FROM super_admins WHERE id = 1");
            if ($a) {
                Auth::loginAdmin($a);
            }
            Response::redirect(url('/admin'));
        }

        Response::redirect(url('/login'));
    }

    public function logout(Request $request): void
    {
        Auth::logout();
        Response::redirect(url('/login?msg=logged_out'));
    }
}
