<?php
use Hasphp\App\Core\Router;

// Main Application Routes
Router::get('/', 'HomeController@index');
Router::get('/demo', 'DemoController@index');

// SPA Routes (Component-based architecture)
Router::get('/spa', 'SPAController@index');
Router::get('/spa/posts', 'SPAController@index');
Router::get('/spa/users', 'SPAController@index');
Router::get('/spa/about', 'SPAController@index');

// Static files route for assets
Router::get('/js/{file}', function($request, $response, $file) {
    $filePath = __DIR__ . "/../public/js/{$file}";
    if (file_exists($filePath) && pathinfo($filePath, PATHINFO_EXTENSION) === 'js') {
        $response->header('Content-Type', 'application/javascript');
        $response->end(file_get_contents($filePath));
    } else {
        $response->status(404)->end('File not found');
    }
});

// Include API routes
require_once __DIR__ . '/api.php';
