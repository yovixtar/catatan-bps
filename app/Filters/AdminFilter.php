<?php

namespace App\Filters;

use App\Helpers\JwtHelper;
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
        try {
            $decoded = JwtHelper::decodeTokenFromRequest($request);

            if (!$decoded) {
                return $this->respondUnauthorized('Token tidak valid');
            }
            
            // Periksa apakah token yang didekode memiliki peran 'pengawas'
            if (!property_exists($decoded, 'role') || $decoded->role !== 'pengawas') {
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