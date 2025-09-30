<?php

namespace Hasphp\App\Controllers;

use Hasphp\App\Core\Request;
use Hasphp\App\Core\Response;
use Hasphp\App\Core\View;

class DemoController
{
    /**
     * Show the demo page.
     */
    public function index($request, $response)
    {
        $html = View::render('demo.twig', [
            'title' => 'HasPHP v2.0 - API Demo',
            'api_base_url' => $this->getBaseUrl($request),
        ]);
        $response->html($html);
    }
    
    /**
     * Get the base URL for API calls.
     */
    private function getBaseUrl($request): string
    {
        $protocol = isset($request->server['HTTPS']) && $request->server['HTTPS'] !== 'off' ? 'https' : 'http';
        $host = $request->server['HTTP_HOST'] ?? 'localhost:9501';
        return "{$protocol}://{$host}";
    }
}
