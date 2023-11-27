<?php

namespace App\Controllers;

use CodeIgniter\API\ResponseTrait;

class Kegiatan extends BaseController
{
    use ResponseTrait;

    public function DaftarKegiatan(): \CodeIgniter\HTTP\Response
    {
        $message = "Hallo";
        $data = [
            'code' => 200,
            'message' => $message,
        ];
        return $this->respond($data, 200);
    }
}
