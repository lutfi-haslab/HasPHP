<?php
require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/routes/web.php';
require __DIR__ . '/app/Core/internal/Routes.php';

use OpenSwoole\Http\Server;
use OpenSwoole\Http\Request;
use OpenSwoole\Http\Response;
use Hasphp\App\Core\Router;
use Hasphp\App\Core\Request as CustomRequest;
use Hasphp\App\Core\Response as CustomResponse;


Hasphp\App\Core\View::init(__DIR__ . '/views');

function runMiddleware(array $middleware, $request, $response, callable $controller)
{
    $next = array_reduce(
        array_reverse($middleware),
        fn($next, $middlewareClass) => fn($req, $res) =>
        (new $middlewareClass())->handle($req, $res, $next),
        $controller
    );

    $next($request, $response);
}

function resolveController(string $handler)
{
    [$class, $method] = explode('@', $handler);

    // If the class is already fully qualified, use it as is
    if (class_exists($class)) {
        $fullClass = $class;
    }
    // Handle Core\Internal namespace
    else if (str_starts_with($class, 'Core\\Internal\\')) {
        $fullClass = "Hasphp\\App\\Core\\Internal\\" . substr($class, strlen('Core\\Internal\\'));
    }
    // Default to Controllers namespace
    else {
        $fullClass = "Hasphp\\App\\Controllers\\$class";
    }

    if (!class_exists($fullClass)) {
        throw new \RuntimeException("Controller class not found: $fullClass");
    }

    return [new $fullClass, $method];
}

$server = new Server("127.0.0.1", 9501);


$server->on("Start", function (Server $server) {
    echo "Swoole server started at http://127.0.0.1:9501\n";
});

$server->on("Request", function (Request $swooleRequest, Response $swooleResponse) {
    $request = new CustomRequest($swooleRequest);
    $response = new CustomResponse($swooleResponse);
    $uri = $request->server['request_uri'];
    $method = $request->server['request_method'];
    $route = Router::dispatch($method, $uri);

    if (!$route) {
        $response->status(404);
        return $response->end("Not Found");
    }

    runMiddleware(
        $route['middleware'] ?? [],
        $request,
        $response,
        function ($req, $res) use ($route) {
            [$controller, $method] = resolveController($route['handler']);
            $controller->$method($req, $res);
        }
    );
});

$server->start();