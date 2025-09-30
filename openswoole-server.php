<?php
/**
 * HasPHP OpenSwoole Server
 * 
 * High-performance HTTP server using OpenSwoole with async/coroutine support.
 * Falls back to PHP's built-in server if OpenSwoole is not available.
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';

require __DIR__ . '/routes/web.php';
require __DIR__ . '/app/Core/internal/Routes.php';

use Hasphp\App\Core\Router;
use Hasphp\App\Core\View;

// Initialize view engine
View::init(__DIR__ . '/views');

// Check if OpenSwoole is available
if (!extension_loaded('openswoole')) {
    echo "⚠️  OpenSwoole extension not found. Please install it first:\n\n";
    echo "📦 Installation options:\n";
    echo "   1. Via PECL: pecl install openswoole\n";
    echo "   2. Via Homebrew: brew install swoole (alternative)\n";
    echo "   3. Via Docker: Use official OpenSwoole Docker image\n\n";
    echo "💡 For macOS with Homebrew PHP, you might need:\n";
    echo "   brew install pcre2\n";
    echo "   export PKG_CONFIG_PATH=/opt/homebrew/lib/pkgconfig\n";
    echo "   pecl install openswoole\n\n";
    echo "🔄 Using fallback PHP built-in server instead...\n";
    echo "   Run: php -S 127.0.0.1:8080 server.php\n\n";
    exit(1);
}

use OpenSwoole\Http\Server;
use OpenSwoole\Http\Request;
use OpenSwoole\Http\Response;
use OpenSwoole\Process;

/**
 * Enhanced Request wrapper for OpenSwoole compatibility
 */
class SwooleRequest extends \Hasphp\App\Core\Request
{
    public ?array $routeParams = null;
    public $app;

    public function __construct($swooleRequest)
    {
        parent::__construct($swooleRequest);
        
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
 * Enhanced Response wrapper for OpenSwoole
 */
class SwooleResponse
{
    private $swooleResponse;
    private bool $ended = false;

    public function __construct(Response $response)
    {
        $this->swooleResponse = $response;
    }

    public function status(int $status): self
    {
        if (!$this->ended) {
            $this->swooleResponse->status($status);
        }
        return $this;
    }

    public function header(string $key, string $value): self
    {
        if (!$this->ended) {
            $this->swooleResponse->header($key, $value);
        }
        return $this;
    }

    public function json(array $data, int $status = 200): self
    {
        if ($this->ended) return $this;
        
        $this->status($status);
        $this->header('Content-Type', 'application/json');
        $this->swooleResponse->end(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $this->ended = true;
        return $this;
    }

    public function html(string $html, int $status = 200): self
    {
        if ($this->ended) return $this;
        
        $this->status($status);
        $this->header('Content-Type', 'text/html; charset=utf-8');
        $this->swooleResponse->end($html);
        $this->ended = true;
        return $this;
    }

    public function text(string $text, int $status = 200): self
    {
        if ($this->ended) return $this;
        
        $this->status($status);
        $this->header('Content-Type', 'text/plain; charset=utf-8');
        $this->swooleResponse->end($text);
        $this->ended = true;
        return $this;
    }

    public function end(?string $content = null): self
    {
        if ($this->ended) return $this;
        
        $this->swooleResponse->end($content ?? '');
        $this->ended = true;
        return $this;
    }

    public function redirect(string $url, int $status = 302): self
    {
        if ($this->ended) return $this;
        
        $this->status($status);
        $this->header('Location', $url);
        $this->swooleResponse->end();
        $this->ended = true;
        return $this;
    }

    // Allow direct access to Swoole response methods
    public function __call(string $method, array $args)
    {
        if (!$this->ended && method_exists($this->swooleResponse, $method)) {
            return $this->swooleResponse->$method(...$args);
        }
        return $this;
    }
}

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
 * Request Handler
 */
function handleSwooleRequest(Request $request, Response $response): void
{
    try {
        $req = new SwooleRequest($request);
        $res = new SwooleResponse($response);
        
        $method = $request->server['request_method'] ?? 'GET';
        $uri = $request->server['request_uri'] ?? '/';
        $uri = parse_url($uri, PHP_URL_PATH);

        // Handle route parameters
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
                        $req->routeParams = array_combine($paramNames[1], $params);
                        break;
                    }
                }
            }
        }

        if (!$route) {
            $res->status(404)->json([
                'success' => false,
                'error' => 'Not Found',
                'message' => "Route not found: $originalUri"
            ]);
            return;
        }

        // Set CORS headers for API routes
        if (str_starts_with($uri, '/api/')) {
            $res->header('Access-Control-Allow-Origin', '*');
            $res->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
            $res->header('Access-Control-Allow-Headers', 'Content-Type, Authorization');
            
            if ($method === 'OPTIONS') {
                $res->status(200)->end();
                return;
            }
        }

        // Execute the route handler
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

    } catch (\Exception $e) {
        $response->status(500);
        $response->header('Content-Type', 'application/json');
        $response->end(json_encode([
            'success' => false,
            'error' => 'Internal Server Error',
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ], JSON_PRETTY_PRINT));
    }
}

// Create OpenSwoole HTTP Server
$server = new Server("0.0.0.0", 8080);

// Server configuration for optimal performance
$server->set([
    'worker_num' => 4,          // Number of worker processes (adjust based on CPU cores)
    'task_worker_num' => 2,     // Number of task worker processes
    'max_request' => 10000,     // Max requests per worker before restart
    'max_conn' => 1000,         // Max concurrent connections
    'daemonize' => false,       // Set to true for production
    'log_level' => SWOOLE_LOG_INFO,
    'log_file' => __DIR__ . '/storage/logs/swoole.log',
    'pid_file' => __DIR__ . '/storage/swoole.pid',
    'enable_coroutine' => true, // Enable coroutine support
    'hook_flags' => SWOOLE_HOOK_ALL, // Enable all coroutine hooks
    'document_root' => __DIR__ . '/public',
    'enable_static_handler' => true, // Serve static files
    'static_handler_locations' => ['/js', '/css', '/images', '/assets'],
]);

// Handle HTTP requests
$server->on('request', function (Request $request, Response $response) {
    handleSwooleRequest($request, $response);
});

// Worker start event
$server->on('workerStart', function ($server, $workerId) {
    echo "🚀 Worker #{$workerId} started\n";
});

// Server start event
$server->on('start', function ($server) {
    echo "🌟 HasPHP OpenSwoole Server started successfully!\n";
    echo "📍 Server Address: http://0.0.0.0:8080\n";
    echo "👥 Worker Processes: {$server->setting['worker_num']}\n";
    echo "⚙️  Task Workers: {$server->setting['task_worker_num']}\n";
    echo "🔧 Max Connections: {$server->setting['max_conn']}\n";
    echo "🚀 Coroutines: Enabled\n";
    echo "📁 Static Files: Enabled (/js, /css, /images, /assets)\n";
    echo "\n💡 Features:\n";
    echo "   • High-performance async/await support\n";
    echo "   • Coroutine-based concurrent processing\n";
    echo "   • Built-in static file serving\n";
    echo "   • Automatic worker process management\n";
    echo "   • Hot-reload ready\n";
    echo "\n🎯 API Endpoints:\n";
    echo "   • http://localhost:8080/         → Home page\n";
    echo "   • http://localhost:8080/demo     → API demo\n";
    echo "   • http://localhost:8080/spa      → Single Page App\n";
    echo "   • http://localhost:8080/api/docs → API Documentation\n";
    echo "\n🔥 Press Ctrl+C to stop the server\n";
});

// Graceful shutdown
Process::signal(SIGTERM, function () use ($server) {
    echo "\n🛑 Graceful shutdown initiated...\n";
    $server->shutdown();
});

Process::signal(SIGINT, function () use ($server) {
    echo "\n🛑 Graceful shutdown initiated...\n";
    $server->shutdown();
});

// Start the server
echo "🚀 Starting HasPHP OpenSwoole Server...\n";
echo "📦 Checking dependencies...\n";

// Create storage directory if it doesn't exist
$storageDir = __DIR__ . '/storage/logs';
if (!is_dir($storageDir)) {
    mkdir($storageDir, 0755, true);
}

$server->start();
