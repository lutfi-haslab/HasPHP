<?php
namespace Hasphp\App\Core;

use OpenSwoole\Http\Request as SwooleRequest;
use JsonException;

class Request {
    public array $query;
    public array $post;
    public array $headers;
    public array $server;
    public string $rawBody;

    public function __construct(SwooleRequest $req) {
        $this->query = $req->get ?? [];
        $this->post = $req->post ?? [];
        $this->headers = $req->header ?? [];
        $this->server = $req->server ?? [];
        $this->rawBody = $req->rawContent() ?? '';
    }

    /**
     * Get JSON decoded request body
     * @return array|null Returns null if body is empty or invalid JSON
     * @throws JsonException
     */
    public function json(): ?array {
        if (empty($this->rawBody)) {
            return null;
        }
        
        $decoded = json_decode($this->rawBody, true, 512, JSON_THROW_ON_ERROR);
        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Get a parameter from request
     * @param string $key Parameter name
     * @param mixed $default Default value if not found
     * @return mixed
     */
    public function input(string $key, $default = null) {
        return $this->post[$key] ?? $this->query[$key] ?? $default;
    }

    /**
     * Get all request parameters (query + post)
     * @return array
     */
    public function all(): array {
        return array_merge($this->query, $this->post);
    }

    /**
     * Check if a parameter exists in request
     * @param string $key Parameter name
     * @return bool
     */
    public function has(string $key): bool {
        return isset($this->query[$key]) || isset($this->post[$key]);
    }

    /**
     * Get request method
     * @return string
     */
    public function method(): string {
        return $this->server['request_method'] ?? 'GET';
    }

    /**
     * Get request path
     * @return string
     */
    public function path(): string {
        return $this->server['request_uri'] ?? '/';
    }

    /**
     * Get a specific header
     * @param string $name Header name (case-insensitive)
     * @param mixed $default Default value if not found
     * @return mixed
     */
    public function header(string $name, $default = null) {
        $name = strtolower($name);
        return $this->headers[$name] ?? $default;
    }

    /**
     * Get client IP address
     * @return string
     */
    public function ip(): string {
        return $this->server['remote_addr'] ?? '127.0.0.1';
    }
}