<?php
declare(strict_types=1);

namespace App\Core;

final class Auth
{
    private static ?array $apiTenant = null;
    private static ?array $apiAdmin = null;
    private static ?string $currentApiToken = null;

    public static function resetApiState(): void
    {
        self::$apiTenant = null;
        self::$apiAdmin = null;
        self::$currentApiToken = null;
        if (session_status() === PHP_SESSION_ACTIVE) {
            unset($_SESSION['tenant'], $_SESSION['admin']);
        }
    }

    public static function startSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            if (!headers_sent()) {
                @session_start();
            }
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

    public static function resolveApiUser(): void
    {
        if (self::$apiTenant !== null || self::$apiAdmin !== null) {
            return;
        }

        // 1. Check Bearer token in headers
        $authHeader = '';
        if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
            $authHeader = trim((string)$_SERVER['HTTP_AUTHORIZATION']);
        } elseif (!empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            $authHeader = trim((string)$_SERVER['REDIRECT_HTTP_AUTHORIZATION']);
        } elseif (!empty($_SERVER['HTTP_X_API_KEY'])) {
            $authHeader = 'Bearer ' . trim((string)$_SERVER['HTTP_X_API_KEY']);
        }

        if (str_starts_with($authHeader, 'Bearer ')) {
            $token = trim(substr($authHeader, 7));
            if (!empty($token)) {
                $hash = hash('sha256', $token);
                $db = Database::getInstance();
                $tokenRow = $db->fetchOne(
                    "SELECT * FROM api_tokens 
                     WHERE token_hash = ? AND (expires_at IS NULL OR expires_at > NOW())",
                    [$hash]
                );

                if ($tokenRow) {
                    self::$currentApiToken = $token;
                    $db->execute("UPDATE api_tokens SET last_used_at = NOW() WHERE id = ?", [$tokenRow['id']]);

                    if ($tokenRow['token_type'] === 'tenant' && !empty($tokenRow['tenant_id'])) {
                        $tenant = $db->fetchOne("SELECT * FROM tenants WHERE id = ? AND status = 'active'", [$tokenRow['tenant_id']]);
                        if ($tenant) {
                            self::$apiTenant = [
                                'id' => (int)$tenant['id'],
                                'business_name' => (string)$tenant['business_name'],
                                'owner_name' => (string)$tenant['owner_name'],
                                'phone' => (string)$tenant['phone'],
                                'email' => (string)($tenant['email'] ?? ''),
                                'status' => (string)$tenant['status'],
                            ];
                        }
                    } elseif ($tokenRow['token_type'] === 'admin' && !empty($tokenRow['admin_id'])) {
                        $admin = $db->fetchOne("SELECT * FROM super_admins WHERE id = ?", [$tokenRow['admin_id']]);
                        if ($admin) {
                            self::$apiAdmin = [
                                'id' => (int)$admin['id'],
                                'name' => (string)$admin['name'],
                                'email' => (string)$admin['email'],
                            ];
                        }
                    }
                    return;
                }
            }
        }

        // 2. Fall back to Session
        self::startSession();
        if (!empty($_SESSION['tenant'])) {
            self::$apiTenant = $_SESSION['tenant'];
        }
        if (!empty($_SESSION['admin'])) {
            self::$apiAdmin = $_SESSION['admin'];
        }
    }

    public static function createApiToken(int $entityId, string $type = 'tenant', string $name = 'default', ?int $expiresInDays = 90): string
    {
        $plainToken = bin2hex(random_bytes(32));
        $hash = hash('sha256', $plainToken);
        $expiresAt = $expiresInDays ? date('Y-m-d H:i:s', time() + ($expiresInDays * 86400)) : null;

        $db = Database::getInstance();
        $db->execute(
            "INSERT INTO api_tokens (tenant_id, admin_id, token_hash, token_type, name, expires_at)
             VALUES (?, ?, ?, ?, ?, ?)",
            [
                $type === 'tenant' ? $entityId : null,
                $type === 'admin' ? $entityId : null,
                $hash,
                $type,
                $name,
                $expiresAt
            ]
        );

        return $plainToken;
    }

    public static function revokeCurrentApiToken(): bool
    {
        self::resolveApiUser();
        if (empty(self::$currentApiToken)) {
            return false;
        }
        $hash = hash('sha256', self::$currentApiToken);
        $db = Database::getInstance();
        $db->execute("DELETE FROM api_tokens WHERE token_hash = ?", [$hash]);
        self::$currentApiToken = null;
        self::$apiTenant = null;
        self::$apiAdmin = null;
        return true;
    }

    public static function tenant(): ?array
    {
        self::resolveApiUser();
        if (self::$apiTenant !== null) {
            return self::$apiTenant;
        }
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
        self::resolveApiUser();
        if (self::$apiAdmin !== null) {
            return self::$apiAdmin;
        }
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

    public static function requireApiTenant(): void
    {
        self::resolveApiUser();
        if (!self::isTenant()) {
            Response::json([
                'success' => false,
                'error' => 'Unauthenticated: Valid tenant Bearer token or active session is required.',
                'code' => 'UNAUTHENTICATED'
            ], 401);
        }
        if (self::tenant()['status'] === 'suspended') {
            Response::json([
                'success' => false,
                'error' => 'Account suspended. Please contact platform administration.',
                'code' => 'ACCOUNT_SUSPENDED'
            ], 403);
        }
    }

    public static function requireApiAdmin(): void
    {
        self::resolveApiUser();
        if (!self::isAdmin()) {
            Response::json([
                'success' => false,
                'error' => 'Unauthenticated: Valid super admin Bearer token or active session is required.',
                'code' => 'UNAUTHENTICATED'
            ], 401);
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
