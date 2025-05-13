<?php
namespace Hasphp\App\Controllers;

use Hasphp\App\Core\Request;
use Hasphp\App\Core\Response;
use Hasphp\App\Core\View;



class HomeController
{
    public function index(Request $req, Response $res)
    {
        $html = View::render('home.twig', ['title' => 'Hello hola cool right']);
        $res->html($html);
    }

    public function hello(Request $req, Response $res)
    {
        $res->header("Content-Type", "text/plain");
        $res->end("Hello from controller");
    }
}