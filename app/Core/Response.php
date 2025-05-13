<?php
namespace Hasphp\App\Core;

use OpenSwoole\Http\Response as SwooleResponse;
use JsonException;

class Response {
    private SwooleResponse $res;

    public function __construct(SwooleResponse $res) {
        $this->res = $res;
    }

    /**
     * Send JSON response
     * @param array $data Data to encode
     * @param int $status HTTP status code
     * @return void
     */
    public function json(array $data, int $status = 200): void {
        $this->res->status($status);
        $this->res->header("Content-Type", "application/json");
        try {
            $json = json_encode($data, JSON_THROW_ON_ERROR);
            $this->res->end($json);
        } catch (JsonException $e) {
            $this->res->status(500);
            $this->res->end(json_encode([
                'error' => 'JSON encoding failed',
                'message' => $e->getMessage()
            ]));
        }
    }

    /**
     * Send plain text response
     * @param string $message Text content
     * @param int $status HTTP status code
     * @return void
     */
    public function text(string $message, int $status = 200): void {
        $this->res->status($status);
        $this->res->header("Content-Type", "text/plain");
        $this->res->end($message);
    }

    /**
     * Send HTML response
     * @param string $html HTML content
     * @param int $status HTTP status code
     * @return void
     */
    public function html(string $html, int $status = 200): void {
        $this->res->status($status);
        $this->res->header("Content-Type", "text/html");
        $this->res->end($html);
    }

    /**
     * Send redirect response
     * @param string $url Redirect URL
     * @param int $status HTTP status code (301, 302, etc)
     * @return void
     */
    public function redirect(string $url, int $status = 302): void {
        $this->res->status($status);
        $this->res->header("Location", $url);
        $this->res->end();
    }

    /**
     * Send file response
     * @param string $path Path to file
     * @param string $contentType MIME type
     * @return void
     */
    public function file(string $path, string $contentType = 'application/octet-stream'): void {
        if (!file_exists($path)) {
            $this->res->status(404);
            $this->res->end('File not found');
            return;
        }

        $this->res->status(200);
        $this->res->header("Content-Type", $contentType);
        $this->res->header("Content-Length", (string)filesize($path));
        $this->res->sendfile($path);
    }

    /**
     * Set response header
     * @param string $key Header name
     * @param string $value Header value
     * @return void
     */
    public function header(string $key, string $value): void {
        $this->res->header($key, $value);
    }

    /**
     * Set response status code
     * @param int $status HTTP status code
     * @return void
     */
    public function status(int $status): void {
        $this->res->status($status);
    }

    /**
     * End the response
     * @param string|null $content Optional content to send
     * @return void
     */
    public function end(?string $content = null): void {
        if ($content !== null) {
            $this->res->write($content);
        }
        $this->res->end();
    }
}