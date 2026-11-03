<?php
declare(strict_types=1);

// Global Helper Functions for DairyFlow SaaS

function e(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function formatMoney(float|int|string $amount, string $symbol = '₹'): string
{
    return $symbol . number_format((float)$amount, 2);
}

function formatDate(?string $date, string $format = 'd M Y'): string
{
    if (empty($date)) {
        return '-';
    }
    return (new \DateTime($date))->format($format);
}

function url(string $path = '/'): string
{
    static $base = null;
    if ($base === null) {
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        if (str_ends_with($scriptName, 'index.php')) {
            $base = $scriptName;
        } else {
            $dir = str_replace('\\', '/', dirname($scriptName));
            $base = ($dir === '/' || $dir === '.') ? '/index.php' : rtrim($dir, '/') . '/index.php';
        }
    }
    $cleanPath = '/' . ltrim($path, '/');
    if ($cleanPath === '/') {
        return $base;
    }
    return $base . $cleanPath;
}

function asset(string $path): string
{
    static $baseDir = null;
    if ($baseDir === null) {
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        $dir = str_replace('\\', '/', dirname($scriptName));
        $baseDir = ($dir === '/' || $dir === '.') ? '' : rtrim($dir, '/');
    }
    return $baseDir . '/' . ltrim($path, '/');
}

function isActiveRoute(string $prefix): bool
{
    $req = new \App\Core\Request();
    $current = $req->path();
    if ($prefix === '/' && $current === '/') {
        return true;
    }
    return $prefix !== '/' && str_starts_with($current, $prefix);
}
