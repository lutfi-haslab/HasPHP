<?php
namespace Hasphp\App\Core;

class Router {
    private static array $routes = [];

    public static function get(string $path, string $handler, array $middleware = [], array $meta = []) {
        self::$routes['GET'][$path] = compact('handler', 'middleware', 'meta');
    }

    public static function post(string $path, string $handler, array $middleware = [], array $meta = []) {
        self::$routes['POST'][$path] = compact('handler', 'middleware', 'meta');
    }

    public static function dispatch(string $method, string $path) {
        return self::$routes[$method][$path] ?? null;
    }

    public static function all(): array {
        return self::$routes;
    }
}