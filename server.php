<?php
/**
 * HasPHP v2.0 - Development Server
 * 
 * This is a fallback server that uses PHP's built-in server when OpenSwoole is not available.
 * It provides the same API functionality as the OpenSwoole version.
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';

require __DIR__ . '/routes/web.php';
require __DIR__ . '/app/Core/internal/Routes.php';

use Hasphp\App\Core\Router;
use Hasphp\App\Core\View;

// Initialize view engine
View::init(__DIR__ . '/views');

/**
 * Controller Resolution
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

/**
 * Middleware Runner
 */
function runMiddleware(array $middleware, StandardRequest $request, StandardResponse $response, callable $next)
{
    // For now, just call the next function directly
    // In a full implementation, middleware would be processed here
    $next($request, $response);
}

/**
 * Mock SwooleRequest for compatibility
 */
class MockSwooleRequest 
{
    public ?array $get = null;
    public ?array $post = null;
    public ?array $header = null;
    public ?array $server = null;
    private string $rawContent = '';
    
    public function __construct()
    {
        $this->get = $_GET ?? [];
        $this->post = $_POST ?? [];
        $this->header = $this->getAllHeaders();
        $this->server = $_SERVER ?? [];
        $this->rawContent = file_get_contents('php://input') ?: '';
    }
    
    public function rawContent(): string
    {
        return $this->rawContent;
    }
    
    private function getAllHeaders(): array
    {
        $headers = [];
        
        if (function_exists('getallheaders')) {
            return getallheaders() ?: [];
        }
        
        // Fallback for CLI and other environments
        foreach ($_SERVER as $name => $value) {
            if (substr($name, 0, 5) === 'HTTP_') {
                $headerName = str_replace(' ', '-', ucwords(str_replace('_', ' ', strtolower(substr($name, 5)))));
                $headers[$headerName] = $value;
            }
        }
        
        return $headers;
    }
}

/**
 * Standard HTTP Request class (fallback for when OpenSwoole is not available)
 * Extends the original Request to maintain compatibility
 */
class StandardRequest extends \Hasphp\App\Core\Request
{
    public ?array $routeParams = null;
    public $app;

    public function __construct()
    {
        // Create and initialize a mock Swoole request
        $mockRequest = new MockSwooleRequest();
        
        // Call the parent constructor with the mock object
        parent::__construct($mockRequest);
        
        // Set the app instance
        global $app;
        $this->app = $app;
    }

    /**
     * Get JSON decoded request body
     */
    public function json(): ?array
    {
        if (empty($this->rawBody)) {
            return null;
        }

        try {
            return json_decode($this->rawBody, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            return null;
        }
    }

    /**
     * Get request input by key
     */
    public function input(string $key, $default = null)
    {
        // Check POST data first, then query parameters
        return $this->post[$key] ?? $this->query[$key] ?? $default;
    }

    /**
     * Get all request input
     */
    public function all(): array
    {
        return array_merge($this->query, $this->post);
    }

    /**
     * Check if request has input key
     */
    public function has(string $key): bool
    {
        return isset($this->post[$key]) || isset($this->query[$key]);
    }

    /**
     * Get header value
     */
    public function header(string $key, $default = null)
    {
        $key = strtolower($key);
        foreach ($this->headers as $headerKey => $value) {
            if (strtolower($headerKey) === $key) {
                return $value;
            }
        }
        return $default;
    }
}

/**
 * Standard HTTP Response class (fallback for when OpenSwoole is not available)
 */
class StandardResponse
{
    private int $statusCode = 200;
    private array $headers = [];
    private string $content = '';
    private bool $sent = false;

    public function status(int $status): self
    {
        $this->statusCode = $status;
        return $this;
    }

    public function header(string $key, string $value): self
    {
        $this->headers[$key] = $value;
        return $this;
    }

    public function json(array $data, int $status = 200): self
    {
        $this->status($status);
        $this->header('Content-Type', 'application/json');
        $this->content = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $this->send();
        return $this;
    }

    public function html(string $html, int $status = 200): self
    {
        $this->status($status);
        $this->header('Content-Type', 'text/html; charset=utf-8');
        $this->content = $html;
        $this->send();
        return $this;
    }

    public function text(string $text, int $status = 200): self
    {
        $this->status($status);
        $this->header('Content-Type', 'text/plain; charset=utf-8');
        $this->content = $text;
        $this->send();
        return $this;
    }

    public function end(?string $content = null): self
    {
        if ($content !== null) {
            $this->content = $content;
        }
        $this->send();
        return $this;
    }

    public function redirect(string $url, int $status = 302): self
    {
        $this->status($status);
        $this->header('Location', $url);
        $this->send();
        return $this;
    }

    private function send(): void
    {
        if ($this->sent) {
            return;
        }

        http_response_code($this->statusCode);
        
        foreach ($this->headers as $key => $value) {
            header("$key: $value");
        }

        echo $this->content;
        $this->sent = true;
    }
}

/**
 * Handle the incoming request
 */
function handleRequest(): void
{
    try {
        $request = new StandardRequest();
        $response = new StandardResponse();
        
        $method = $_SERVER['REQUEST_METHOD'];
        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

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
            $response->status(404)->json([
                'success' => false,
                'error' => 'Not Found',
                'message' => "Route not found: $originalUri"
            ]);
            return;
        }

        // Set CORS headers for API routes
        if (str_starts_with($uri, '/api/')) {
            $response->header('Access-Control-Allow-Origin', '*');
            $response->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
            $response->header('Access-Control-Allow-Headers', 'Content-Type, Authorization');
            
            if ($method === 'OPTIONS') {
                $response->status(200)->end();
                return;
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
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => 'Internal Server Error',
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ], JSON_PRETTY_PRINT);
    }
}

// Handle the request
handleRequest();
