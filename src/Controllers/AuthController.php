<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\ApiClient;
use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;

final class AuthController
{
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

        // Authenticate via REST API
        $res = ApiClient::post('/api/auth/login', [
            'login' => $loginInput,
            'password' => $password,
            'device_name' => 'Web Browser',
        ]);

        if (!$res['success'] || empty($res['data']['tenant'])) {
            Auth::flash('error', $res['error'] ?? 'Invalid login credentials. Please try again.');
            Response::redirect(url('/login'));
        }

        $tenant = $res['data']['tenant'];
        Auth::loginTenant($tenant);
        if (!empty($res['data']['token'])) {
            $_SESSION['api_token'] = $res['data']['token'];
        }

        Auth::flash('success', "Welcome back, {$tenant['owner_name']}!");
        Response::redirect(url('/'));
    }

    public function registerView(Request $request): void
    {
        if (Auth::isTenant()) {
            Auth::logout();
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

        // Register via REST API
        $res = ApiClient::post('/api/auth/register', [
            'business_name' => $businessName,
            'owner_name' => $ownerName,
            'phone' => $phone,
            'email' => $email,
            'password' => $password,
            'device_name' => 'Web Browser',
        ]);

        if (!$res['success'] || empty($res['data']['tenant'])) {
            Auth::flash('error', $res['error'] ?? 'Registration failed. Please check your details.');
            Response::redirect(url('/register'));
        }

        $tenant = $res['data']['tenant'];
        Auth::loginTenant($tenant);
        if (!empty($res['data']['token'])) {
            $_SESSION['api_token'] = $res['data']['token'];
        }

        Auth::flash('success', "Welcome to DoodhDesk! Your free account is ready.");
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

        // Authenticate admin via REST API
        $res = ApiClient::post('/api/admin/auth/login', [
            'email' => $email,
            'password' => $password,
            'device_name' => 'Admin Console',
        ]);

        if (!$res['success'] || empty($res['data']['admin'])) {
            Auth::flash('error', $res['error'] ?? 'Invalid admin credentials.');
            Response::redirect(url('/admin/login'));
        }

        $admin = $res['data']['admin'];
        Auth::loginAdmin($admin);
        if (!empty($res['data']['token'])) {
            $_SESSION['api_token'] = $res['data']['token'];
        }

        Response::redirect(url('/admin'));
    }

    public function demoSwitch(Request $request, array $params): void
    {
        $role = $params['role'] ?? 'tenant1';

        if ($role === 'tenant1') {
            $res = ApiClient::post('/api/auth/login', [
                'login' => '9822000001',
                'password' => 'password123',
                'device_name' => 'Demo Switcher',
            ]);
            if ($res['success'] && !empty($res['data']['tenant'])) {
                Auth::loginTenant($res['data']['tenant']);
                $_SESSION['api_token'] = $res['data']['token'] ?? null;
                Auth::flash('success', 'Logged in as Ramesh Patel (Krishna Pure Milk Dairy - Paid Tier Demo)');
            }
            Response::redirect(url('/'));
        } elseif ($role === 'tenant2') {
            $res = ApiClient::post('/api/auth/login', [
                'login' => '9822000002',
                'password' => 'password123',
                'device_name' => 'Demo Switcher',
            ]);
            if ($res['success'] && !empty($res['data']['tenant'])) {
                Auth::loginTenant($res['data']['tenant']);
                $_SESSION['api_token'] = $res['data']['token'] ?? null;
                Auth::flash('success', 'Logged in as Suresh Sharma (Radha Cow Dairy - Free Tier Demo)');
            }
            Response::redirect(url('/'));
        } elseif ($role === 'admin') {
            $res = ApiClient::post('/api/admin/auth/login', [
                'email' => 'admin@dairyflow.com',
                'password' => 'admin123',
                'device_name' => 'Demo Switcher',
            ]);
            if ($res['success'] && !empty($res['data']['admin'])) {
                Auth::loginAdmin($res['data']['admin']);
                $_SESSION['api_token'] = $res['data']['token'] ?? null;
            }
            Response::redirect(url('/admin'));
        }

        Response::redirect(url('/login'));
    }

    public function logout(Request $request): void
    {
        ApiClient::post('/api/auth/logout');
        Auth::logout();
        Response::redirect(url('/login?msg=logged_out'));
    }
}
