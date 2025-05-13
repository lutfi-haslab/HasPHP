<?php
namespace Hasphp\App\Core;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Exception;

class Auth
{
    protected static ?array $user = null;

    public static function decode(string $token): ?array
    {
        try {
            $decoded = JWT::decode($token, new Key($_ENV['JWT_SECRET'], 'HS256'));
            return (array) $decoded;
        } catch (Exception $e) {
            return null;
        }
    }

    public static function user(): ?array
    {
        return self::$user;
    }

    public static function setUser(array $data): void
    {
        self::$user = $data;
    }
}