<?php
namespace App\Helpers;

use Config\Token;
use \Firebase\JWT\JWT;
use Firebase\JWT\Key;

class JwtHelper
{
    public static function decodeToken(string $token): ?object
    {
        try {
            $key = Token::JWT_SECRET_KEY;
            return JWT::decode($token, new Key($key, 'HS256'));
        } catch (\Throwable $th) {
            // Tangani kesalahan jika terjadi
            return null;
        }
    }

    public static function decodeTokenFromRequest($request): ?object
    {
        $header = $request->getHeaderLine("Authorization");
        $token = null;

        if (!empty($header) && preg_match('/Bearer\s(\S+)/', $header, $matches)) {
            $token = $matches[1];
        }

        if (empty($token)) {
            return null;
        }

        return self::decodeToken($token);
    }
}