<?php
use Hasphp\App\Core\Router;

Router::get('/docs', 'Core\\Internal\\Controller@ui');
Router::get('/swagger.json','Core\\Internal\\Controller@json');