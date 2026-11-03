<?php
declare(strict_types=1);

namespace App\Core;

final class Router
{
    private array $routes = [];

    public function get(string $pattern, array|callable $handler, array $middleware = []): void
    {
        $this->addRoute('GET', $pattern, $handler, $middleware);
    }

    public function post(string $pattern, array|callable $handler, array $middleware = []): void
    {
        $this->addRoute('POST', $pattern, $handler, $middleware);
    }

    public function any(string $pattern, array|callable $handler, array $middleware = []): void
    {
        $this->addRoute('ANY', $pattern, $handler, $middleware);
    }

    private function addRoute(string $method, string $pattern, array|callable $handler, array $middleware): void
    {
        $normalizedPattern = '/' . trim($pattern, '/');
        if ($normalizedPattern !== '/') {
            $regex = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '(?P<$1>[^/]+)', $normalizedPattern);
            $regex = '#^' . $regex . '$#';
        } else {
            $regex = '#^/$#';
        }

        $this->routes[] = [
            'method' => $method,
            'pattern' => $normalizedPattern,
            'regex' => $regex,
            'handler' => $handler,
            'middleware' => $middleware,
        ];
    }

    public function dispatch(Request $request): void
    {
        $method = $request->method();
        $path = '/' . trim($request->path(), '/');

        foreach ($this->routes as $route) {
            if ($route['method'] !== 'ANY' && $route['method'] !== $method) {
                continue;
            }

            if (preg_match($route['regex'], $path, $matches)) {
                // Apply middleware
                foreach ($route['middleware'] as $mw) {
                    $this->runMiddleware($mw);
                }

                // Extract named params
                $params = [];
                foreach ($matches as $k => $v) {
                    if (is_string($k)) {
                        $params[$k] = $v;
                    }
                }

                $handler = $route['handler'];
                if (is_callable($handler)) {
                    $handler($request, $params);
                    return;
                }

                if (is_array($handler) && count($handler) === 2) {
                    [$class, $action] = $handler;
                    $controller = new $class();
                    $controller->$action($request, $params);
                    return;
                }
            }
        }

        // 404
        http_response_code(404);
        View::render('errors/404', ['path' => $path], 'main');
    }

    private function runMiddleware(string $middleware): void
    {
        match ($middleware) {
            'tenant' => Auth::requireTenant(),
            'admin' => Auth::requireAdmin(),
            default => null,
        };
    }
}
