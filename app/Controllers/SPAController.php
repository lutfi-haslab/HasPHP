<?php

namespace Hasphp\App\Controllers;

use Hasphp\App\Core\View;

class SPAController
{
    /**
     * Show the modular component-based SPA demo page.
     */
    public function index($request, $response)
    {
        $html = View::render('spa-modular.twig', [
            'title' => 'HasPHP Component-Based SPA',
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
        $host = $request->server['HTTP_HOST'] ?? 'localhost:8080';
        return "{$protocol}://{$host}";
    }
}
