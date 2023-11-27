<?php

namespace App\Controllers;

use CodeIgniter\API\ResponseTrait;

class Home extends BaseController
{
    use ResponseTrait;

    public function index() : \CodeIgniter\HTTP\Response
    {
        $message = "Selamat datang di API Catatan Kegiatan BPS - 20.240.0035 MUHAMMAD ISHLAKHUDDIN";
        return $this->messageResponse($message, 200);
    }
}
