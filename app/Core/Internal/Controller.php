<?php
namespace Hasphp\App\Core\Internal;

use Hasphp\App\Core\Request;
use Hasphp\App\Core\Response;
use Hasphp\App\Core\View;
use Hasphp\App\Core\Router;

class Controller
{
    public function ui(Request $req, Response $res)
    {
        $html = View::render('swagger.twig', ['swaggerJsonUrl' => '/swagger.json']);
        $res->html($html);
    }

    public function json(Request $req, Response $res)
    {
        $routes = Router::all();
        $openapi = [
            'openapi' => '3.0.0',
            'info' => ['title' => 'HasPHP API', 'version' => '1.0.0'],
            'paths' => [],
        ];

        foreach ($routes as $method => $paths) {
            foreach ($paths as $route => $data) {
                $meta = $data['meta'] ?? [];

                $pathItem = [
                    'tags' => $meta['tags'] ?? ['Default'],
                    'summary' => $meta['summary'] ?? '',
                    'description' => $meta['description'] ?? '',
                    'responses' => [
                        '200' => [
                            'description' => $meta['responses'][200]['description'] ?? 'OK',
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type' => $meta['responses'][200]['type'] ?? 'string',
                                        'properties' => $meta['responses'][200]['properties'] ?? []
                                    ]
                                ]
                            ]
                        ]
                    ]
                ];

                if (isset($meta['requestBody'])) {
                    $pathItem['requestBody'] = [
                        'required' => true,
                        'content' => $meta['requestBody']
                    ];
                }

                $openapi['paths'][$route][strtolower($method)] = $pathItem;
            }
        }

        $res->header("Content-Type", "application/json");
        $res->end(json_encode($openapi, JSON_PRETTY_PRINT));
    }
}