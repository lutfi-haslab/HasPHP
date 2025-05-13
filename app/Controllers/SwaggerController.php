<?php
namespace Hasphp\App\Controllers;

use Hasphp\App\Core\Request;
use Hasphp\App\Core\Response;
use Hasphp\App\Core\View;

class SwaggerController
{
    public function ui(Request $req, Response $res)
    {
        $html = View::render('swagger.twig', [
            'swaggerJsonUrl' => '/swagger.json'
        ]);

        $res->html($html);
    }
}