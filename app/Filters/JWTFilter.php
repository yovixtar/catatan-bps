<?php

namespace App\Filters;

use CodeIgniter\API\ResponseTrait;
use Config\Token;
use \Firebase\JWT\JWT;
use Firebase\JWT\Key;

class JWTFilter implements \CodeIgniter\Filters\FilterInterface
{
    use ResponseTrait;

    public function before(\CodeIgniter\HTTP\RequestInterface $request, $arguments = null)
    {
        // Lakukan verifikasi token JWT di sini
        $token = $request->getHeaderLine('Authorization');

        try {
            // Dekode token
            $key = Token::JWT_SECRET_KEY;
            $decoded = JWT::decode($token, new Key($key, 'HS256'));

            // Jika verifikasi berhasil, lanjutkan request
            return $request;

        } catch (\Exception $e) {
            // Jika terjadi kesalahan verifikasi, kirim respons error
            $data = [
                'code' => 401,
                'message' => 'Unauthorized: Token tidak valid',
            ];
            return $this->respond($data, 401);
        }
    }

    public function after(\CodeIgniter\HTTP\RequestInterface $request, \CodeIgniter\HTTP\ResponseInterface $response, $arguments = null)
    {
        // Lakukan operasi setelah request diproses
        return $response;
    }
}
