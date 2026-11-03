<?php
declare(strict_types=1);

namespace App\Core;

final class Auth
{
    public static function startSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
    }

    public static function csrfToken(): string
    {
        self::startSession();
        return $_SESSION['csrf_token'] ?? '';
    }

    public static function csrfField(): string
    {
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(self::csrfToken(), ENT_QUOTES) . '">';
    }

    public static function loginTenant(array $tenant): void
    {
        self::startSession();
        session_regenerate_id(true);
        $_SESSION['tenant'] = [
            'id' => (int)$tenant['id'],
            'business_name' => (string)$tenant['business_name'],
            'owner_name' => (string)$tenant['owner_name'],
            'phone' => (string)$tenant['phone'],
            'email' => (string)($tenant['email'] ?? ''),
            'status' => (string)$tenant['status'],
        ];
        unset($_SESSION['admin']);
    }

    public static function loginAdmin(array $admin): void
    {
        self::startSession();
        session_regenerate_id(true);
        $_SESSION['admin'] = [
            'id' => (int)$admin['id'],
            'name' => (string)$admin['name'],
            'email' => (string)$admin['email'],
        ];
        unset($_SESSION['tenant']);
    }

    public static function logout(): void
    {
        self::startSession();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }
        session_destroy();
    }

    public static function tenant(): ?array
    {
        self::startSession();
        return $_SESSION['tenant'] ?? null;
    }

    public static function tenantId(): int
    {
        $tenant = self::tenant();
        if ($tenant === null) {
            throw new \RuntimeException('Unauthenticated tenant access.');
        }
        return (int)$tenant['id'];
    }

    public static function isTenant(): bool
    {
        return self::tenant() !== null;
    }

    public static function admin(): ?array
    {
        self::startSession();
        return $_SESSION['admin'] ?? null;
    }

    public static function isAdmin(): bool
    {
        return self::admin() !== null;
    }

    public static function requireTenant(): void
    {
        if (!self::isTenant()) {
            Response::redirect(url('/login?msg=tenant_required'));
        }
    }

    public static function requireAdmin(): void
    {
        if (!self::isAdmin()) {
            Response::redirect(url('/admin/login?msg=admin_required'));
        }
    }

    public static function flash(string $key, ?string $message = null): ?string
    {
        self::startSession();
        if ($message !== null) {
            $_SESSION['flash'][$key] = $message;
            return null;
        }
        $val = $_SESSION['flash'][$key] ?? null;
        unset($_SESSION['flash'][$key]);
        return $val;
    }
}
