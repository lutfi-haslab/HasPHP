<?php
namespace Hasphp\App\Controllers;

use Hasphp\App\Core\Request;
use Hasphp\App\Core\Response;

class AuthController
{
    public function login(Request $req, Response $res)
    {
        $data = $req->json();

        $email = $data['email'] ?? null;
        $password = $data['password'] ?? null;

        if (!$email || !$password) {
            $res->status(400);
            return $res->json([
                'error' => 'Email and password are required'
            ]);
        }
        
        if ($email === 'user@example.com' && $password === 'secret') {
            return $res->json([
                'token' => base64_encode('dummy_token_' . time())
            ]);
        }

        $res->status(401);
        return $res->json([
            'error' => 'Invalid credentials'
        ]);
    }
}