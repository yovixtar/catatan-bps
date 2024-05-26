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
    const HTTP_NOT_FOUND = 404;
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

            if (empty($kegiatan)) {
                    $message = "Tidak ada kegiatan pada laporan ini!";
                    return $this->messageResponse($message, self::HTTP_NOT_FOUND);
            }

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

            $lastIDLaporan = $this->verifikasiLaporanModel
                ->where('id_laporan', $id_laporan)
                ->orderBy('id', 'DESC')
                ->first();

            $nextIDLaporan = $lastIDLaporan ? $lastIDLaporan['id'] + 1 : 1;

            $statuses = [];

            foreach ($dataVerifikasiKegiatan->list_kegiatan as $verifikasiKegiatan) {
                $status = $verifikasiKegiatan->approval ? 'approval' : 'rejection';

                $verifikasiKegiatanData = [
                    'id_kegiatan' => $verifikasiKegiatan->id_kegiatan,
                    'id_laporan' => $id_laporan,
                    'id_verifikasi_laporan' => $nextIDLaporan,
                    'nip_verifikator' => $nip_verifikator,
                    'status' => $status,
                    'keterangan' => $verifikasiKegiatan->keterangan
                ];

                $this->verifikasiKegiatanModel->insert($verifikasiKegiatanData);
                $this->kegiatanModel->update($verifikasiKegiatan->id_kegiatan, ['status' => $status]);

                $statuses[] = $verifikasiKegiatan->approval;
            }

            $status_laporan = array_reduce($statuses, function ($carry, $status) {
                return $carry && $status;
            }, true) ? 'approval' : 'rejection';

            $data = [
                'id' => $nextIDLaporan,
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

    public function DaftarVerifikasiLaporan(string $id_laporan): Response
    {
        try {
            $data_verifikasi = $this->verifikasiLaporanModel
                ->where('id_laporan', $id_laporan)
                ->whereIn('status', ['rejection', 'approval'])
                ->orderBy('created_at', 'ASC')->findAll();

            if (empty($data_verifikasi)) {
                return $this->dataResponse([], self::HTTP_SUCCESS);
            }

            $formattedData = [];
            foreach ($data_verifikasi as $item) {
                $formattedData[] = [
                    'id' => $item['id'],
                    'status_laporan' => $item['status'],
                    'keterangan_laporan' => $item['keterangan'],
                    'status_verifikasi' => $item['status'],
                    'keterangan_verifikasi' => $item['keterangan'],
                    'tanggal' => date('Y-m-d', strtotime($item['created_at'])),
                    'active' => true,
                ];
            }

            return $this->dataResponse($formattedData, self::HTTP_SUCCESS);
        } catch (\Throwable $th) {
            // Tangani kesalahan dan kirim respons error
            $message = 'Terjadi kesalahan dalam mengambil data verifikasi : ' . $th;
            return $this->messageResponse($message, self::HTTP_SERVER_ERROR);
        }
    }

    public function DaftarVerifikasiKegiatan(int $id_verifikasi_laporan): Response
    {
        try {
            $data_verifikasi = $this->verifikasiKegiatanModel
                ->where('id_verifikasi_laporan', $id_verifikasi_laporan)
                ->join('kegiatan', 'kegiatan.id = verifikasi_kegiatan.id_kegiatan', 'left')
                ->join('pengguna', 'pengguna.nip = kegiatan.nip_pengguna', 'left')
                ->select("verifikasi_kegiatan.id AS id, verifikasi_kegiatan.status AS status, kegiatan.keterangan AS keterangan, verifikasi_kegiatan.created_at AS created_at, kegiatan.target AS target, kegiatan.nama AS nama, kegiatan.nip_pengguna AS nip_pengguna, pengguna.nama AS nama_pengguna, kegiatan.realisasi AS realisasi")
                ->orderBy('created_at', 'ASC')->findAll();

            if (empty($data_verifikasi)) {
                return $this->dataResponse([], self::HTTP_SUCCESS);
            }

            $formattedData = [];
            foreach ($data_verifikasi as $item) {
                $formattedData[] = [
                    'id' => $item['id'],
                    'status' => $item['status'],
                    'keterangan' => $item['keterangan'],
                    'tanggal' => date('Y-m-d', strtotime($item['created_at'])),
                    
                    'target' => $item['target'],
                    'nama' => $item['nama'],
                    'nip_pengguna' => $item['nip_pengguna'],
                    'nama_pengguna' => $item['nama_pengguna'],
                    'realisasi' => $item['realisasi'],
                ];
            }

            return $this->dataResponse($formattedData, self::HTTP_SUCCESS);
        } catch (\Throwable $th) {
            // Tangani kesalahan dan kirim respons error
            $message = 'Terjadi kesalahan dalam mengambil data verifikasi : ' . $th;
            return $this->messageResponse($message, self::HTTP_SERVER_ERROR);
        }
    }
}
