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

class JWTFilter implements FilterInterface
{
    use ResponseTrait;

    public function before(RequestInterface $request, $arguments = null)
    {
        $key = Token::JWT_SECRET_KEY;
        $header = $request->getHeaderLine("Authorization");
        $token = null;
  
        // extract the token from the header
        if(!empty($header)) {
            if (preg_match('/Bearer\s(\S+)/', $header, $matches)) {
                $token = $matches[1];
            }
        }
  
        // check if token is null or empty
        if(is_null($token) || empty($token)) {
            $data = [
                'code' => 401,
                'message' => 'Unauthorized: Token tidak valid',
            ];
            $response = service('response');
            $response->setContentType('application/json');
            $response->setBody(json_encode($data));
            $response->setStatusCode(401);
            return $response;
        }
  
        try {
            // $decoded = JWT::decode($token, $key, array("HS256"));
            $decoded = JWT::decode($token, new Key($key, 'HS256'));
        } catch (Exception $ex) {
            $data = [
                'code' => 401,
                'message' => 'Unauthorized: Token tidak valid',
            ];
            $response = service('response');
            $response->setContentType('application/json');
            $response->setBody(json_encode($data));
            $response->setStatusCode(401);
            return $response;
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // Lakukan operasi setelah request diproses
        return $response;
    }
}
