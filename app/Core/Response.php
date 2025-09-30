<?php
namespace Hasphp\App\Core;

use OpenSwoole\Http\Response as SwooleResponse;
use JsonException;

class Response {
    private SwooleResponse $swooleResponse;

    public function __construct(SwooleResponse $res) {
        $this->swooleResponse = $res;
    }

    /**
     * Send JSON response
     * @param array $data Data to encode
     * @param int $status HTTP status code
     * @return self
     */
    public function json(array $data, int $status = 200): self {
        $this->swooleResponse->status($status);
        $this->swooleResponse->header("Content-Type", "application/json");
        try {
            $json = json_encode($data, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            $this->swooleResponse->end($json);
        } catch (JsonException $e) {
            $this->swooleResponse->status(500);
            $this->swooleResponse->end(json_encode([
                'error' => 'JSON encoding failed',
                'message' => $e->getMessage()
            ]));
        }
        return $this;
    }

    /**
     * Send plain text response
     * @param string $message Text content
     * @param int $status HTTP status code
     * @return void
     */
    public function text(string $message, int $status = 200): self {
        $this->swooleResponse->status($status);
        $this->swooleResponse->header("Content-Type", "text/plain");
        $this->swooleResponse->end($message);
        return $this;
    }

    /**
     * Send HTML response
     * @param string $html HTML content
     * @param int $status HTTP status code
     * @return void
     */
    public function html(string $html, int $status = 200): self {
        $this->swooleResponse->status($status);
        $this->swooleResponse->header("Content-Type", "text/html");
        $this->swooleResponse->end($html);
        return $this;
    }

    /**
     * Send redirect response
     * @param string $url Redirect URL
     * @param int $status HTTP status code (301, 302, etc)
     * @return void
     */
    public function redirect(string $url, int $status = 302): self {
        $this->swooleResponse->status($status);
        $this->swooleResponse->header("Location", $url);
        $this->swooleResponse->end();
        return $this;
    }

    /**
     * Send file response
     * @param string $path Path to file
     * @param string $contentType MIME type
     * @return void
     */
    public function file(string $path, string $contentType = 'application/octet-stream'): self {
        if (!file_exists($path)) {
            $this->swooleResponse->status(404);
            $this->swooleResponse->end('File not found');
            return $this;
        }

        $this->swooleResponse->status(200);
        $this->swooleResponse->header("Content-Type", $contentType);
        $this->swooleResponse->header("Content-Length", (string)filesize($path));
        $this->swooleResponse->sendfile($path);
        return $this;
    }

    /**
     * Set response header
     * @param string $key Header name
     * @param string $value Header value
     * @return void
     */
    public function header(string $key, string $value): self {
        $this->swooleResponse->header($key, $value);
        return $this;
    }

    /**
     * Set response status code
     * @param int $status HTTP status code
     * @return void
     */
    public function status(int $status): self {
        $this->swooleResponse->status($status);
        return $this;
    }

    /**
     * End the response
     * @param string|null $content Optional content to send
     * @return void
     */
    public function end(?string $content = null): self {
        if ($content !== null) {
            $this->swooleResponse->write($content);
        }
        $this->swooleResponse->end();
        return $this;
    }
}