<?php

namespace App\Controllers;

use App\Helpers\JwtHelper;
use App\Models\KegiatanModel;
use App\Models\LaporanModel;
use App\Models\VerifikasiKegiatanModel;
use App\Models\VerifikasiLaporanModel;
use CodeIgniter\HTTP\Response;

class Verifikasi extends BaseController
{
    private $verifikasiLaporanModel, $verifikasiKegiatanModel, $laporanModel, $kegiatanModel;

    const HTTP_SERVER_ERROR = 500;
    const HTTP_BAD_REQUEST = 400;
    const HTTP_UNAUTHORIZED = 401;
    const HTTP_SUCCESS = 200;
    const HTTP_SUCCESS_CREATE = 201;

    public function __construct()
    {
        $this->verifikasiLaporanModel = new VerifikasiLaporanModel();
        $this->verifikasiKegiatanModel = new VerifikasiKegiatanModel();
        $this->laporanModel = new LaporanModel();
        $this->kegiatanModel = new KegiatanModel();
    }

    public function VerifikasiPetugas(): Response
    {
        try {
            $decoded = JwtHelper::decodeTokenFromRequest($this->request);

            if (!$decoded) {
                return $this->messageResponse('Token tidak valid', self::HTTP_UNAUTHORIZED);
            }

            $nip_pengguna = $decoded->nip;

            $id_laporan = $this->request->getPost('id_laporan');
            $status = $this->request->getPost('status');
            $keterangan = $this->request->getPost('keterangan') ?? null;

            if (empty($nip_pengguna) || empty($id_laporan) || empty($status)) {
                $message = "Periksa kembali request anda.";
                return $this->messageResponse($message, self::HTTP_BAD_REQUEST);
            }

            // Periksa apakah status yang dikirim sesuai dengan nilai yang diizinkan
            $allowedStatus = ['reporting'];
            if (!in_array($status, $allowedStatus)) {
                $message = "Status yang dikirim tidak valid.";
                return $this->messageResponse($message, self::HTTP_BAD_REQUEST);
            }

            $kegiatan = $this->kegiatanModel->where('id_laporan', $id_laporan)->findAll();

            foreach ($kegiatan as $item) {
                if ($item['realisasi'] == null) {
                    $message = "Seluruh kegiatan pada laporan harus terealisasi!";
                    return $this->messageResponse($message, self::HTTP_BAD_REQUEST);
                }
            }

            $data = [
                'id_laporan' => $id_laporan,
                'status' => $status,
                'keterangan' => $keterangan,
            ];

            $this->verifikasiLaporanModel->insert($data);
            $this->laporanModel->update($id_laporan, ['status' => 'reporting']);

            // Kirim respons berhasil menambahkan verifikasi
            $message = "Berhasil menambahkan proses verifikasi.";
            return $this->messageResponse($message, self::HTTP_SUCCESS);
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
            $verifikasi = $this->verifikasiLaporanModel
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

    public function VerifikasiPengawas(): Response
    {
        try {
            $decoded = JwtHelper::decodeTokenFromRequest($this->request);
            if (!$decoded) {
                return $this->messageResponse('Token tidak valid', self::HTTP_UNAUTHORIZED);
            }

            $nip_verifikator = $decoded->nip;
            $dataVerifikasiKegiatan = $this->request->getJSON();

            $id_laporan = $dataVerifikasiKegiatan->id_laporan;
            $keterangan_verifikasi = $dataVerifikasiKegiatan->keterangan_verifikasi;

            $lastBatch = $this->verifikasiKegiatanModel
                ->where('id_laporan', $id_laporan)
                ->orderBy('batch', 'DESC')
                ->first();

            $nextBatch = $lastBatch ? $lastBatch['batch'] + 1 : 1;

            $statuses = [];

            foreach ($dataVerifikasiKegiatan->list_kegiatan as $verifikasiKegiatan) {
                $status = $verifikasiKegiatan->approval ? 'approval' : 'rejection';

                $verifikasiKegiatanData = [
                    'id_kegiatan' => $verifikasiKegiatan->id_kegiatan,
                    'id_laporan' => $id_laporan,
                    'nip_pengguna' => $dataVerifikasiKegiatan->nip_pengguna,
                    'nip_verifikator' => $nip_verifikator,
                    'status' => $status,
                    'batch' => $nextBatch,
                    'keterangan' => $verifikasiKegiatan->keterangan
                ];

                $this->verifikasiKegiatanModel->insert($verifikasiKegiatanData);

                $statuses[] = $verifikasiKegiatan->approval;
            }

            $status_laporan = array_reduce($statuses, function ($carry, $status) {
                return $carry && $status;
            }, true) ? 'approval' : 'rejection';

            $data = [
                'nip_pengguna' => $dataVerifikasiKegiatan->nip_pengguna,
                'nip_verifikator' => $nip_verifikator,
                'id_laporan' => $id_laporan,
                'status' => $status_laporan,
                'keterangan' => $keterangan_verifikasi,
            ];

            $this->verifikasiLaporanModel->insert($data);
            $this->laporanModel->update($id_laporan, ['status' => $status_laporan]);

            $message = 'Verifikasi Laporan berhasil.';
            return $this->messageResponse($message, self::HTTP_SUCCESS);
        } catch (\Exception $e) {
            $message = 'Terjadi kesalahan dalam proses verifikasi kegiatan. Error : ' . $e->getMessage();
            return $this->messageResponse($message, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function GetLastVerifLaporanByID(int $id_laporan) : Response {
        try {
            $lastVerif = $this->verifikasiLaporanModel->where('id_laporan', $id_laporan)->orderBy('id', 'DESC')->first();

            if (!$lastVerif) {
                return $this->messageResponse('Verifikasi laporan tidak ditemukan', self::HTTP_BAD_REQUEST);
            }

            $data = [
                'status' => $lastVerif['status'],
                'keterangan' => $lastVerif['>keterangan'],
            ];

            return $this->dataResponse($data, self::HTTP_SUCCESS);
        } catch (\Throwable $th) {
            $message = 'Terjadi kesalahan dalam proses verifikasi kegiatan. Error : ' . $th;
            return $this->messageResponse($message, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
