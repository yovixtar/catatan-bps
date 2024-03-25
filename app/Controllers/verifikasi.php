<?php

namespace App\Controllers;

use App\Helpers\JwtHelper;
use App\Models\LaporanModel;
use App\Models\VerifikasiModel;
use CodeIgniter\HTTP\Response;

class Verifikasi extends BaseController
{
    private $verifikasiModel, $laporanModel;

    const HTTP_SERVER_ERROR = 500;
    const HTTP_BAD_REQUEST = 400;
    const HTTP_UNAUTHORIZED = 401;
    const HTTP_SUCCESS = 200;
    const HTTP_SUCCESS_CREATE = 201;

    public function __construct()
    {
        $this->verifikasiModel = new VerifikasiModel();
        $this->laporanModel = new LaporanModel();
    }

    public function ReportingVerifikasi(): Response
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
                'nip_pengguna' => $nip_pengguna,
                'id_laporan' => $id_laporan,
                'status' => $status,
                'keterangan' => $keterangan,
            ];

            $this->verifikasiModel->insert($data);
            $this->laporanModel->update($id_laporan, ['status' => 'reported']);

            // Kirim respons berhasil menambahkan verifikasi
            $message = "Berhasil menambahkan proses verifikasi.";
            return $this->messageResponse($message, self::HTTP_SUCCESS_CREATE);
        } catch (\Throwable $th) {
            // Tangani kesalahan dan kirim respons error
            $message = 'Terjadi kesalahan dalam proses verifikasi: ' . $th;
            return $this->messageResponse($message, self::HTTP_SERVER_ERROR);
        }
    }

    public function DaftarVerfikasi(int $id_laporan)
    {
        try {
            
            if (empty($id_laporan)) {
                $message = "ID laporan harus diisi.";
                return $this->messageResponse($message, self::HTTP_BAD_REQUEST);
            }

            // Ambil data verifikasi dari database
            $verifikasi = $this->verifikasiModel
                ->where('id_laporan', $id_laporan)
                ->join('pengguna', 'pengguna.nip = verifikasi.nip_pengguna', 'left')
                ->select('verifikasi.*, pengguna.nama AS nama_pengguna')
                ->findAll();

            // Jika tidak ada verifikasi, kirim respons kosong
            if (empty($verifikasi)) {
                return $this->dataResponse([], self::HTTP_SUCCESS);
            }

            // Format data riwayat verifikasi
            $formattedData = [];
            foreach ($verifikasi as $item) {
                $formattedData[] = [
                    'id' => $item['id'],
                    'nip_pengguna' => $item['nip_pengguna'],
                    'nama_pengguna' => $item['nama_pengguna'],
                    'status' => $item['status'],
                    'keterangan' => $item['keterangan'],
                ];
            }

            // Kirim respons dengan data riwayat verifikasi
            return $this->dataResponse($formattedData, self::HTTP_SUCCESS);
        } catch (\Throwable $th) {
            // Tangani kesalahan dan kirim respons error
            $message = 'Terjadi kesalahan dalam mengambil data verifikasi: ' . $th;
            return $this->messageResponse($message, self::HTTP_SERVER_ERROR);
        }
    }
}
