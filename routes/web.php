<?php
use Hasphp\App\Core\Router;

Router::get('/', 'HomeController@index');
Router::get('/hello', 'HomeController@hello', [], [
    'tags' => ['Home'],
    'summary' => 'Say Hello',
    'description' => 'Returns a plain text greeting',
    'responses' => [
        200 => ['type' => 'string']
    ]
]);
Router::post('/login', 'AuthController@login', [], [
    'tags' => ['Auth'],
    'summary' => 'User login',
    'description' => 'Authenticate user and return a token',
    'requestBody' => [
        'application/json' => [
            'schema' => [
                'type' => 'object',
                'properties' => [
                    'email' => ['type' => 'string', 'format' => 'email'],
                    'password' => ['type' => 'string']
                ],
                'required' => ['email', 'password']
            ]
        ]
    ],
    'responses' => [
        200 => [
            'description' => 'Successful login',
            'type' => 'object',
            'properties' => [
                'token' => ['type' => 'string']
            ]
        ],
        401 => [
            'description' => 'Unauthorized'
        ]
    ]
]);
Router::get('/users', 'UserController@list', [], [
    'tags' => ['User'],
    'summary' => 'Get list of users',
    'description' => 'Returns a list of users',
    'responses' => [
        200 => ['type' => 'array']
    ]
]);
Router::post('/users', 'UserController@createUser');
Router::post('/users/{id}/password', 'UserController@updatePassword');
