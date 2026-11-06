<?php
declare(strict_types=1);

namespace App\Core;

final class ApiClient
{
    /**
     * Dispatch an internal API call without HTTP network overhead.
     *
     * @param string $method HTTP method (GET, POST, PUT, DELETE)
     * @param string $path Relative API path (e.g. '/api/customers' or '/api/dashboard/stats')
     * @param array $data Query parameters for GET, or request payload for POST/PUT
     * @param string|null $bearerToken Optional explicit Bearer token (otherwise uses session context)
     * @return array Standardized result envelope: ['status' => int, 'body' => array, 'success' => bool, 'data' => mixed, 'message' => ?string, 'error' => ?string, 'meta' => array]
     */
    public static function call(string $method, string $path, array $data = [], ?string $bearerToken = null): array
    {
        $normalizedPath = '/' . ltrim($path, '/');
        if (!str_starts_with($normalizedPath, '/api')) {
            $normalizedPath = '/api' . $normalizedPath;
        }

        // Enable in-memory interception in Response
        Response::beginIntercept();

        try {
            $request = Request::createInternal($method, $normalizedPath, $data, $bearerToken);

            $router = new Router();
            $apiRoutes = require dirname(__DIR__, 2) . '/routes_api.php';
            $apiRoutes($router);

            $router->dispatch($request);
        } catch (HttpResponseException $e) {
            // Captured in memory cleanly via Response::$interceptedData
        } catch (\Throwable $e) {
            return [
                'status' => 500,
                'body' => ['success' => false, 'error' => $e->getMessage()],
                'success' => false,
                'data' => null,
                'message' => null,
                'error' => $e->getMessage(),
                'meta' => [],
            ];
        } finally {
            Response::endIntercept();
        }

        $intercepted = Response::getInterceptedData();

        if ($intercepted === null) {
            return [
                'status' => 404,
                'body' => ['success' => false, 'error' => "API endpoint not found: {$method} {$normalizedPath}"],
                'success' => false,
                'data' => null,
                'message' => null,
                'error' => "API endpoint not found: {$method} {$normalizedPath}",
                'meta' => [],
            ];
        }

        $status = (int)($intercepted['status'] ?? 200);
        $body = $intercepted['body'] ?? [];
        if (!is_array($body) && isset($intercepted['html'])) {
            $body = ['html' => $intercepted['html']];
        }

        return [
            'status' => $status,
            'body' => $body,
            'success' => (bool)($body['success'] ?? ($status >= 200 && $status < 300)),
            'data' => $body['data'] ?? null,
            'message' => $body['message'] ?? null,
            'error' => $body['error'] ?? null,
            'meta' => $body['meta'] ?? [],
        ];
    }

    public static function get(string $path, array $queryParams = [], ?string $token = null): array
    {
        return self::call('GET', $path, $queryParams, $token);
    }

    public static function post(string $path, array $body = [], ?string $token = null): array
    {
        return self::call('POST', $path, $body, $token);
    }

    public static function put(string $path, array $body = [], ?string $token = null): array
    {
        return self::call('PUT', $path, $body, $token);
    }

    public static function delete(string $path, array $data = [], ?string $token = null): array
    {
        return self::call('DELETE', $path, $data, $token);
    }
}
