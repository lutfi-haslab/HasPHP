<?php

/**
 * Internal System Routes (Core Framework Feature)
 * 
 * These routes are automatically registered by the HasPHP framework
 * and should not be modified by application developers.
 */

use Hasphp\App\Core\Router;

// Internal API Documentation Routes (Hidden from users)
Router::get('/api/docs', 'Core\\Internal\\SwaggerController@ui');
Router::get('/api/docs/openapi.json', 'Core\\Internal\\SwaggerController@spec');

// Alternative paths for convenience
Router::get('/docs', 'Core\\Internal\\SwaggerController@ui');
Router::get('/swagger', 'Core\\Internal\\SwaggerController@ui');
