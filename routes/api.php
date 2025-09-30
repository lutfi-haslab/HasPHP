<?php
use Hasphp\App\Core\Router;

// API Routes
Router::get('/api/posts', 'Api\\PostController@index', [], [
    'tags' => ['API', 'Posts'],
    'summary' => 'Get list of posts',
    'description' => 'Returns paginated list of published posts with author and tag information',
    'responses' => [
        200 => [
            'description' => 'Successful response',
            'type' => 'object',
            'properties' => [
                'success' => ['type' => 'boolean'],
                'data' => ['type' => 'array'],
                'pagination' => ['type' => 'object']
            ]
        ]
    ]
]);

Router::get('/api/posts/{slug}', 'Api\\PostController@show', [], [
    'tags' => ['API', 'Posts'],
    'summary' => 'Get a specific post',
    'description' => 'Returns a single post by slug with author, tags, and comments',
    'responses' => [
        200 => ['description' => 'Post found'],
        404 => ['description' => 'Post not found']
    ]
]);

Router::post('/api/posts', 'Api\\PostController@store', [], [
    'tags' => ['API', 'Posts'],
    'summary' => 'Create a new post',
    'description' => 'Creates a new blog post',
    'requestBody' => [
        'application/json' => [
            'schema' => [
                'type' => 'object',
                'properties' => [
                    'title' => ['type' => 'string'],
                    'content' => ['type' => 'string'],
                    'excerpt' => ['type' => 'string'],
                    'user_id' => ['type' => 'integer'],
                    'published' => ['type' => 'boolean'],
                    'featured' => ['type' => 'boolean'],
                    'tags' => ['type' => 'array']
                ],
                'required' => ['title', 'content', 'user_id']
            ]
        ]
    ]
]);

Router::get('/api/users', 'Api\\UserController@index', [], [
    'tags' => ['API', 'Users'],
    'summary' => 'Get list of users',
    'description' => 'Returns list of active users with post counts'
]);

Router::get('/api/users/{id}', 'Api\\UserController@show', [], [
    'tags' => ['API', 'Users'],
    'summary' => 'Get a specific user',
    'description' => 'Returns user profile with posts and statistics'
]);

Router::get('/api/tags', 'Api\\TagController@index', [], [
    'tags' => ['API', 'Tags'],
    'summary' => 'Get list of tags',
    'description' => 'Returns all tags with post counts'
]);

Router::get('/api/tags/{slug}', 'Api\\TagController@show', [], [
    'tags' => ['API', 'Tags'],
    'summary' => 'Get a specific tag',
    'description' => 'Returns tag with associated posts'
]);

// Statistics endpoint
Router::get('/api/stats', function($request, $response) {
    try {
        $db = $request->app->resolve('db');
        
        $stats = [
            'posts' => [
                'total' => $db->table('posts')->count(),
                'published' => $db->table('posts')->where('published', '=', 1)->count(),
                'featured' => $db->table('posts')->where('featured', '=', 1)->count(),
            ],
            'users' => [
                'total' => $db->table('users')->count(),
                'active' => $db->table('users')->where('active', '=', 1)->count(),
            ],
            'tags' => [
                'total' => $db->table('tags')->count(),
            ],
            'comments' => [
                'total' => $db->table('comments')->count(),
                'approved' => $db->table('comments')->where('approved', '=', 1)->count(),
            ]
        ];
        
        $response->json([
            'success' => true,
            'data' => $stats
        ]);
    } catch (\Exception $e) {
        $response->status(500)->json([
            'success' => false,
            'error' => 'Failed to fetch statistics',
            'message' => $e->getMessage()
        ]);
    }
}, [], [
    'tags' => ['API', 'Stats'],
    'summary' => 'Get application statistics',
    'description' => 'Returns overall statistics about posts, users, tags, and comments'
]);
