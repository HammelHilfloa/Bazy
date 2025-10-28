<?php

namespace App\Support;

class Router
{
    public function __construct(private array $routes)
    {
    }

    public function dispatch(Request $request): void
    {
        foreach ($this->routes as $route) {
            [$method, $pattern, $middlewares, $handler] = $route;
            if ($method !== $request->method()) {
                continue;
            }

            $regex = '#^' . preg_replace('#\{[^/]+\}#', '([^/]+)', $pattern) . '$#';
            if (!preg_match($regex, $request->path(), $matches)) {
                continue;
            }

            array_shift($matches);
            $this->executeMiddlewares($middlewares, $request, $matches);
            $this->invokeHandler($handler, $request, $matches);
            return;
        }

        http_response_code(404);
        echo '404 Not Found';
    }

    private function executeMiddlewares(array $middlewares, Request $request, array $params): void
    {
        $middlewares = isset($middlewares[0]) && is_array($middlewares[0]) ? $middlewares : [$middlewares];
        foreach ($middlewares as $middleware) {
            [$class, $method] = $middleware;
            $instance = new $class();
            $instance->$method($request, $params);
        }
    }

    private function invokeHandler(array $handler, Request $request, array $params): void
    {
        [$class, $method] = $handler;
        $controller = new $class();
        echo $controller->$method($request, ...$params);
    }
}
