<?php

namespace App\Controllers;

use CodeIgniter\API\ResponseTrait;

class Kegiatan extends BaseController
{
    use ResponseTrait;

    public function daftar_kegiatan(): \CodeIgniter\HTTP\Response
    {
        $message = "Hallo";
        $data = [
            'code' => 200,
            'message' => $message,
        ];
        return $this->respond($data, 200);
    }
}
