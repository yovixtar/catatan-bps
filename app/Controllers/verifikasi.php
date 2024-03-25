<?php

namespace App\Controllers;

use App\Helpers\JwtHelper;
use App\Models\VerifikasiModel;
use CodeIgniter\HTTP\Response;

class Verifikasi extends BaseController
{
    private $verifikasiModel;

    const HTTP_SERVER_ERROR = 500;
    const HTTP_BAD_REQUEST = 400;
    const HTTP_UNAUTHORIZED = 401;
    const HTTP_SUCCESS = 200;
    const HTTP_SUCCESS_CREATE = 201;

    public function __construct()
    {
        $this->verifikasiModel = new VerifikasiModel();
    }

    function ReportingVerifikasi(): Response
    {
        try {
            $decoded = JwtHelper::decodeTokenFromRequest($this->request);

            if (!$decoded) {
                return $this->messageResponse('Token tidak valid', self::HTTP_UNAUTHORIZED);
            }

            // Dapatkan nip_pengguna dari payload
            $nip_pengguna = $decoded->nip;

            $id_laporan = $this->request->getPost('id_laporan');
            $status = $this->request->getPost('status');
            $keterangan = $this->request->getPost('keterangan') ?? null;

            if (empty($nip_pengguna) || empty($id_laporan) || empty($status)) {
                $message = "Periksa kembali request anda.";
                return $this->messageResponse($message, self::HTTP_BAD_REQUEST);
            }

            // Periksa apakah status yang dikirim sesuai dengan nilai yang diizinkan
            $allowedStatus = ['reporting', 'approval', 'rejection', 'resubmission'];
            if (!in_array($status, $allowedStatus)) {
                $message = "Status yang dikirim tidak valid.";
                return $this->messageResponse($message, self::HTTP_BAD_REQUEST);
            }

            // Masukkan data ke dalam tabel verifiksai
            $data = [
                'nip_petugas' => $nip_pengguna,
                'id_laporan' => $id_laporan,
                'status' => $status,
                'keterangan' => $keterangan,
            ];

            $this->verifikasiModel->insert($data);

            // Kirim respons berhasil menambahkan verifikasi
            $message = "Berhasil menambahkan proses verifikasi.";
            return $this->messageResponse($message, self::HTTP_SUCCESS_CREATE);
        } catch (\Throwable $th) {
            // Tangani kesalahan dan kirim respons error
            $message = 'Terjadi kesalahan dalam proses verifikasi: ' . $th;
            return $this->messageResponse($message, self::HTTP_SERVER_ERROR);
        }
    }
}
