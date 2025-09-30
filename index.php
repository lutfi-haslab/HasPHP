<?php
require __DIR__ . '/vendor/autoload.php';

/*
|--------------------------------------------------------------------------
| Create The Application
|--------------------------------------------------------------------------
|
| The first thing we will do is create a new HasPHP application instance
| which serves as the "glue" for all the components of HasPHP, and is
| the IoC container for the system binding all of the various parts.
|
*/

$app = require_once __DIR__.'/bootstrap/app.php';

/*
|--------------------------------------------------------------------------
| Load Routes
|--------------------------------------------------------------------------
|
| Next we need to include the routes file where all route definitions are
| registered with the router instance.
|
*/

require __DIR__ . '/routes/web.php';
require __DIR__ . '/app/Core/internal/Routes.php';

/*
|--------------------------------------------------------------------------
| Initialize Services
|--------------------------------------------------------------------------
|
| Boot the application and initialize required services like the view engine.
|
*/

use OpenSwoole\Http\Server;
use OpenSwoole\Http\Request;
use OpenSwoole\Http\Response;
use Hasphp\App\Core\Router;
use Hasphp\App\Core\Request as CustomRequest;
use Hasphp\App\Core\Response as CustomResponse;

// Initialize view engine
Hasphp\App\Core\View::init(__DIR__ . '/views');

/*
|--------------------------------------------------------------------------
| Middleware Handling
|--------------------------------------------------------------------------
|
| Define the middleware processing function that handles the request/response
| flow through the middleware stack.
|
*/

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

/*
|--------------------------------------------------------------------------
| Controller Resolution
|--------------------------------------------------------------------------
|
| Define how controllers are resolved from route definitions.
|
*/

function resolveController(string|\Closure $handler)
{
    // Handle Closure routes
    if ($handler instanceof \Closure) {
        return $handler;
    }
    
    [$class, $method] = explode('@', $handler);

    // If the class is already fully qualified, use it as is
    if (class_exists($class)) {
        $fullClass = $class;
    }
    // Handle Core\Internal namespace
    else if (str_starts_with($class, 'Core\\Internal\\')) {
        $fullClass = "Hasphp\\App\\Core\\Internal\\" . substr($class, strlen('Core\\Internal\\'));
    }
    // Handle API controllers
    else if (str_starts_with($class, 'Api\\')) {
        $fullClass = "Hasphp\\App\\Controllers\\Api\\" . substr($class, strlen('Api\\'));
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

/*
|--------------------------------------------------------------------------
| Start The Server
|--------------------------------------------------------------------------
|
| Create and configure the OpenSwoole server, then start handling requests.
|
*/

$server = new Server("127.0.0.1", 9501);

$server->on("Start", function (Server $server) {
    echo "🚀 HasPHP v2.0 Server started at http://127.0.0.1:9501\n";
    echo "📊 Demo page: http://127.0.0.1:9501/demo\n";
    echo "🔗 API endpoints: http://127.0.0.1:9501/api/*\n";
    echo "Press Ctrl+C to stop the server\n";
});

$server->on("Request", function (Request $swooleRequest, Response $swooleResponse) use ($app) {
    try {
        $request = new CustomRequest($swooleRequest);
        $request->app = $app; // Inject app instance for API controllers
        
        $response = new CustomResponse($swooleResponse);
        $uri = $request->server['request_uri'];
        $method = $request->server['request_method'];
        
        // Handle route parameters (simple implementation)
        $originalUri = $uri;
        $route = Router::dispatch($method, $uri);
        
        // If no exact match, try to find a parameterized route
        if (!$route) {
            foreach (Router::all()[$method] ?? [] as $pattern => $routeData) {
                if (strpos($pattern, '{') !== false) {
                    // Convert route pattern to regex
                    $regex = '#^' . preg_replace('/\{[^}]+\}/', '([^/]+)', $pattern) . '$#';
                    if (preg_match($regex, $uri, $matches)) {
                        $route = $routeData;
                        
                        // Extract route parameters
                        preg_match_all('/\{([^}]+)\}/', $pattern, $paramNames);
                        $params = array_slice($matches, 1);
                        
                        // Store parameters for controller access
                        $request->routeParams = array_combine($paramNames[1], $params);
                        break;
                    }
                }
            }
        }
        
        if (!$route) {
            $response->status(404);
            return $response->end("Not Found: $originalUri");
        }

        // Set CORS headers for API routes
        if (str_starts_with($uri, '/api/')) {
            $response->header('Access-Control-Allow-Origin', '*');
            $response->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
            $response->header('Access-Control-Allow-Headers', 'Content-Type, Authorization');
            
            if ($method === 'OPTIONS') {
                return $response->status(200)->end();
            }
        }

        runMiddleware(
            $route['middleware'] ?? [],
            $request,
            $response,
            function ($req, $res) use ($route) {
                $handler = resolveController($route['handler']);
                
                // Handle Closure routes
                if ($handler instanceof \Closure) {
                    $params = $req->routeParams ?? [];
                    $args = [$req, $res, ...array_values($params)];
                    $handler(...$args);
                    return;
                }
                
                // Handle controller@method routes
                [$controller, $method] = $handler;
                
                // Pass route parameters as method arguments
                $params = $req->routeParams ?? [];
                $args = [$req, $res, ...array_values($params)];
                
                $controller->$method(...$args);
            }
        );
    } catch (\Exception $e) {
        $swooleResponse->header('Content-Type', 'application/json');
        $swooleResponse->status(500);
        $swooleResponse->end(json_encode([
            'success' => false,
            'error' => 'Internal Server Error',
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]));
    }
});

$server->start();
