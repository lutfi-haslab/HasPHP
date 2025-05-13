<?php
namespace Hasphp\App\Middleware;

use Hasphp\App\Core\Auth;

class AuthMiddleware
{
    public function handle($req, $res, $next)
    {
        $authHeader = $req->header('authorization');

        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return $res->status(401)->json(['error' => 'Unauthorized']);
        }

        $token = substr($authHeader, 7);
        $user = Auth::decode($token);

        if (!$user) {
            return $res->status(401)->json(['error' => 'Invalid Token']);
        }

        Auth::setUser($user);
        return $next($req, $res);
    }
}