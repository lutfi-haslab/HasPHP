<?php

namespace Hasphp\App\Core\Internal;

/**
 * Internal Swagger UI Controller (Core System Feature)
 * This is automatically available and should not be modified by users.
 */
class SwaggerController
{
    /**
     * Serve the Swagger UI interface.
     */
    public function ui($request, $response)
    {
        $baseUrl = $this->getBaseUrl($request);
        
        $html = '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>API Documentation</title>
    <link rel="stylesheet" type="text/css" href="https://unpkg.com/swagger-ui-dist@5.9.0/swagger-ui.css" />
    <style>
        html { box-sizing: border-box; overflow: -moz-scrollbars-vertical; overflow-y: scroll; }
        *, *:before, *:after { box-sizing: inherit; }
        body { margin: 0; background: #fafafa; }
    </style>
</head>
<body>
    <div id="swagger-ui"></div>
    <script src="https://unpkg.com/swagger-ui-dist@5.9.0/swagger-ui-bundle.js"></script>
    <script src="https://unpkg.com/swagger-ui-dist@5.9.0/swagger-ui-standalone-preset.js"></script>
    <script>
        window.onload = function() {
            SwaggerUIBundle({
                url: "' . $baseUrl . '/api/docs/openapi.json",
                dom_id: "#swagger-ui",
                deepLinking: true,
                presets: [SwaggerUIBundle.presets.apis, SwaggerUIStandalonePreset],
                plugins: [SwaggerUIBundle.plugins.DownloadUrl],
                layout: "StandaloneLayout",
                tryItOutEnabled: true,
                validatorUrl: null
            });
        };
    </script>
</body>
</html>';

        $response->html($html);
    }

    /**
     * Generate and serve OpenAPI specification.
     */
    public function spec($request, $response)
    {
        $spec = $this->generateOpenApiSpec($request);
        $response->json($spec);
    }

    /**
     * Auto-generate OpenAPI spec from existing API routes.
     */
    private function generateOpenApiSpec($request): array
    {
        $baseUrl = $this->getBaseUrl($request);
        
        return [
            'openapi' => '3.0.3',
            'info' => [
                'title' => 'HasPHP API',
                'version' => '1.0.0',
                'description' => 'Auto-generated API documentation'
            ],
            'servers' => [
                ['url' => $baseUrl]
            ],
            'paths' => $this->discoverApiPaths(),
            'components' => $this->getCommonComponents()
        ];
    }

    /**
     * Auto-discover API paths from router.
     */
    private function discoverApiPaths(): array
    {
        // This would inspect the router and auto-generate paths
        // For now, return the known API endpoints
        return [
            '/api/posts' => [
                'get' => [
                    'tags' => ['Posts'],
                    'summary' => 'Get all posts',
                    'parameters' => [
                        [
                            'name' => 'limit',
                            'in' => 'query',
                            'schema' => ['type' => 'integer', 'default' => 10]
                        ],
                        [
                            'name' => 'offset',
                            'in' => 'query',
                            'schema' => ['type' => 'integer', 'default' => 0]
                        ]
                    ],
                    'responses' => [
                        '200' => [
                            'description' => 'Success',
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type' => 'object',
                                        'properties' => [
                                            'success' => ['type' => 'boolean'],
                                            'data' => [
                                                'type' => 'array',
                                                'items' => ['$ref' => '#/components/schemas/Post']
                                            ],
                                            'pagination' => ['$ref' => '#/components/schemas/Pagination']
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ],
                'post' => [
                    'tags' => ['Posts'],
                    'summary' => 'Create new post',
                    'requestBody' => [
                        'required' => true,
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'required' => ['title', 'content'],
                                    'properties' => [
                                        'title' => ['type' => 'string'],
                                        'content' => ['type' => 'string'],
                                        'published' => ['type' => 'boolean']
                                    ]
                                ]
                            ]
                        ]
                    ],
                    'responses' => [
                        '201' => [
                            'description' => 'Created',
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type' => 'object',
                                        'properties' => [
                                            'success' => ['type' => 'boolean'],
                                            'data' => ['$ref' => '#/components/schemas/Post']
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            '/api/posts/{slug}' => [
                'get' => [
                    'tags' => ['Posts'],
                    'summary' => 'Get post by slug',
                    'parameters' => [
                        [
                            'name' => 'slug',
                            'in' => 'path',
                            'required' => true,
                            'schema' => ['type' => 'string']
                        ]
                    ],
                    'responses' => [
                        '200' => [
                            'description' => 'Success',
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type' => 'object',
                                        'properties' => [
                                            'success' => ['type' => 'boolean'],
                                            'data' => ['$ref' => '#/components/schemas/Post']
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            '/api/users' => [
                'get' => [
                    'tags' => ['Users'],
                    'summary' => 'Get all users',
                    'responses' => [
                        '200' => [
                            'description' => 'Success',
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type' => 'object',
                                        'properties' => [
                                            'success' => ['type' => 'boolean'],
                                            'data' => [
                                                'type' => 'array',
                                                'items' => ['$ref' => '#/components/schemas/User']
                                            ]
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            '/api/users/{id}' => [
                'get' => [
                    'tags' => ['Users'],
                    'summary' => 'Get user by ID',
                    'parameters' => [
                        [
                            'name' => 'id',
                            'in' => 'path',
                            'required' => true,
                            'schema' => ['type' => 'integer']
                        ]
                    ],
                    'responses' => [
                        '200' => [
                            'description' => 'Success',
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type' => 'object',
                                        'properties' => [
                                            'success' => ['type' => 'boolean'],
                                            'data' => ['$ref' => '#/components/schemas/User']
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            '/api/tags' => [
                'get' => [
                    'tags' => ['Tags'],
                    'summary' => 'Get all tags',
                    'responses' => [
                        '200' => [
                            'description' => 'Success',
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type' => 'object',
                                        'properties' => [
                                            'success' => ['type' => 'boolean'],
                                            'data' => [
                                                'type' => 'array',
                                                'items' => ['$ref' => '#/components/schemas/Tag']
                                            ]
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];
    }

    /**
     * Get common component schemas.
     */
    private function getCommonComponents(): array
    {
        return [
            'schemas' => [
                'Post' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'integer'],
                        'title' => ['type' => 'string'],
                        'slug' => ['type' => 'string'],
                        'content' => ['type' => 'string'],
                        'excerpt' => ['type' => 'string'],
                        'published' => ['type' => 'integer'],
                        'created_at' => ['type' => 'string', 'format' => 'date-time'],
                        'author_name' => ['type' => 'string'],
                        'tags' => [
                            'type' => 'array',
                            'items' => ['$ref' => '#/components/schemas/Tag']
                        ]
                    ]
                ],
                'User' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'integer'],
                        'name' => ['type' => 'string'],
                        'email' => ['type' => 'string', 'format' => 'email'],
                        'created_at' => ['type' => 'string', 'format' => 'date-time'],
                        'posts_count' => ['type' => 'integer']
                    ]
                ],
                'Tag' => [
                    'type' => 'object',
                    'properties' => [
                        'name' => ['type' => 'string'],
                        'slug' => ['type' => 'string'],
                        'color' => ['type' => 'string']
                    ]
                ],
                'Pagination' => [
                    'type' => 'object',
                    'properties' => [
                        'total' => ['type' => 'integer'],
                        'limit' => ['type' => 'integer'],
                        'offset' => ['type' => 'integer'],
                        'has_more' => ['type' => 'boolean']
                    ]
                ]
            ]
        ];
    }

    /**
     * Get base URL.
     */
    private function getBaseUrl($request): string
    {
        $protocol = isset($request->server['HTTPS']) && $request->server['HTTPS'] !== 'off' ? 'https' : 'http';
        $host = $request->server['HTTP_HOST'] ?? 'localhost:8080';
        return "{$protocol}://{$host}";
    }
}
