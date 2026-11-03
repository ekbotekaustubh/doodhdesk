<?php
declare(strict_types=1);

namespace App\Core;

final class Request
{
    private string $method;
    private string $uri;
    private string $path;
    private array $queryParams;
    private array $bodyParams;
    private ?array $jsonBody = null;
    private array $files;
    private array $headers;

    public function __construct()
    {
        $this->method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $this->uri = $_SERVER['REQUEST_URI'] ?? '/';
        $this->queryParams = $_GET;
        $this->bodyParams = $_POST;
        $this->files = $_FILES;
        $this->headers = $this->collectHeaders();
        $this->path = $this->resolvePath();

        $rawInput = file_get_contents('php://input');
        if (!empty($rawInput) && str_contains($this->getHeader('content-type') ?? '', 'application/json')) {
            $decoded = json_decode($rawInput, true);
            if (is_array($decoded)) {
                $this->jsonBody = $decoded;
            }
        }
    }

    private function collectHeaders(): array
    {
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $name = strtolower(str_replace('_', '-', substr($key, 5)));
                $headers[$name] = (string)$value;
            } elseif (in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH'])) {
                $name = strtolower(str_replace('_', '-', $key));
                $headers[$name] = (string)$value;
            }
        }
        return $headers;
    }

    private function resolvePath(): string
    {
        // 1. Explicit query parameter 'r' (e.g. index.php?r=/deliveries)
        if (!empty($_GET['r'])) {
            $p = '/' . ltrim((string)$_GET['r'], '/');
            return parse_url($p, PHP_URL_PATH) ?: '/';
        }

        // 2. PATH_INFO (e.g. index.php/deliveries)
        if (!empty($_SERVER['PATH_INFO'])) {
            return '/' . ltrim($_SERVER['PATH_INFO'], '/');
        }

        // 3. REQUEST_URI normalized against script directory or SCRIPT_NAME
        $parsedUri = parse_url($this->uri, PHP_URL_PATH) ?: '/';
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        $scriptDir = dirname($scriptName);

        if ($scriptDir !== '/' && str_starts_with($parsedUri, $scriptDir)) {
            $parsedUri = substr($parsedUri, strlen($scriptDir));
        }

        if (str_starts_with($parsedUri, '/index.php')) {
            $parsedUri = substr($parsedUri, 10);
        }

        $parsedUri = '/' . ltrim($parsedUri, '/');
        return $parsedUri === '' ? '/' : $parsedUri;
    }

    public function method(): string
    {
        return $this->method;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->queryParams[$key] ?? $default;
    }

    public function post(string $key, mixed $default = null): mixed
    {
        if ($this->jsonBody !== null) {
            return $this->jsonBody[$key] ?? $default;
        }
        return $this->bodyParams[$key] ?? $default;
    }

    public function all(): array
    {
        if ($this->jsonBody !== null) {
            return array_merge($this->queryParams, $this->jsonBody);
        }
        return array_merge($this->queryParams, $this->bodyParams);
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->all());
    }

    public function file(string $key): ?array
    {
        return $this->files[$key] ?? null;
    }

    public function getHeader(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }

    public function isHtmx(): bool
    {
        return strtolower((string)$this->getHeader('hx-request')) === 'true';
    }

    public function isAjax(): bool
    {
        return $this->isHtmx() || strtolower((string)$this->getHeader('x-requested-with')) === 'xmlhttprequest';
    }

    public function isPost(): bool
    {
        return $this->method === 'POST';
    }

    public function isGet(): bool
    {
        return $this->method === 'GET';
    }

    public function validateCsrf(): bool
    {
        if ($this->isGet()) {
            return true;
        }
        $token = $this->post('csrf_token') ?? $this->getHeader('x-csrf-token');
        return !empty($token) && hash_equals($_SESSION['csrf_token'] ?? '', (string)$token);
    }
}
