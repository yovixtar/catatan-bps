<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\API\ResponseTrait;
use Config\Token;
use Exception;
use \Firebase\JWT\JWT;
use Firebase\JWT\Key;

class AdminFilter implements FilterInterface
{
    use ResponseTrait;

    public function before(RequestInterface $request, $arguments = null)
    {
        $key = Token::JWT_SECRET_KEY;
        $header = $request->getHeaderLine("Authorization");
        $token = null;

        // ekstrak token dari header
        if (!empty($header)) {
            if (preg_match('/Bearer\s(\S+)/', $header, $matches)) {
                $token = $matches[1];
            }
        }

        // periksa jika token kosong atau null
        if (is_null($token) || empty($token)) {
            return $this->respondUnauthorized('Token tidak valid');
        }

        try {
            $decoded = JWT::decode($token, new Key($key, 'HS256'));
            // Periksa apakah token yang didekode memiliki peran 'admin'
            if (!property_exists($decoded, 'role') || $decoded->role !== 'admin') {
                return $this->respondUnauthorized('Anda tidak memiliki izin untuk akses ini');
            }
        } catch (Exception $ex) {
            return $this->respondUnauthorized('Token tidak valid');
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // Lakukan operasi setelah request diproses
        return $response;
    }

    protected function respondUnauthorized(string $message)
    {
        $data = [
            'code' => 401,
            'message' => 'Unauthorized: ' . $message,
        ];

        $response = service('response');
        $response->setContentType('application/json');
        $response->setBody(json_encode($data));
        $response->setStatusCode(401);
        
        return $response;
    }
}