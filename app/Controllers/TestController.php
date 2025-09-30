<?php

namespace Hasphp\App\Controllers;

use Hasphp\App\Core\View;

/**
 * TestController Controller
 */
class TestController
{
    /**
     * Display a listing of the resource.
     */
    public function index($request, $response)
    {
        // TODO: Implement index method
        $response->json([
            'message' => 'Hello from TestController!'
        ]);
    }

    /**
     * Show a specific resource.
     */
    public function show($request, $response, $id)
    {
        // TODO: Implement show method
        $response->json([
            'message' => "Showing resource {$id}"
        ]);
    }
}
