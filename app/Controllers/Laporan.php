<?php

namespace App\Controllers;

use App\Helpers\JwtHelper;
use App\Models\LaporanModel;
use App\Models\VerifikasiLaporanModel;
use \CodeIgniter\HTTP\Response;

class Laporan extends BaseController
{

    private $laporanModel, $verifikasiLaporanModel;

    const HTTP_SERVER_ERROR = 500;
    const HTTP_BAD_REQUEST = 400;
    const HTTP_UNAUTHORIZED = 401;
    const HTTP_SUCCESS = 200;
    const HTTP_SUCCESS_CREATE = 201;

    public function __construct()
    {
        $this->laporanModel = new LaporanModel();
        $this->verifikasiLaporanModel = new VerifikasiLaporanModel();
    }

    public function DaftarLaporan(): Response
    {
        try {
            $decoded = JwtHelper::decodeTokenFromRequest($this->request);

            if (!$decoded) {
                return $this->messageResponse('Token tidak valid', self::HTTP_UNAUTHORIZED);
            }

            // Dapatkan nip_pengguna dari payload
            $nip_pengguna = $decoded->nip;

            $tahun = $this->request->getGet('tahun');
            $status = $this->request->getGet('status');

            // Buat query berdasarkan parameter filter
            $laporanQuery = $this->laporanModel
                ->select('laporan.*, laporan.status AS status_laporan, laporan.keterangan AS keterangan_laporan, 
              pengguna.nama AS nama_pengguna')
                ->where('laporan.nip_pengguna', $nip_pengguna)
                ->join('pengguna', 'pengguna.nip = laporan.nip_pengguna')
                ->groupBy('laporan.id')
                ->withDeleted();

            if (!empty($tahun)) {
                $laporanQuery->where('tahun', $tahun);
            } else {
                $tahunSekarang = date('Y');
                $laporanQuery->where('tahun', $tahunSekarang);
            }

            if (!empty($status)) {
                $laporanQuery->where('laporan.status', $status);
            }

            // Eksekusi query
            $laporan = $laporanQuery->findAll();

            // Jika tidak ada laporan, kirim respons kosong
            if (empty($laporan)) {
                return $this->dataResponse([], self::HTTP_SUCCESS);
            }

            // Format data laporan
            // $formattedData = [];
            foreach ($laporan as $item) {
                $formattedData[] = [
                    'id' => $item['id'],
                    'nip_pengguna' => $item['nip_pengguna'],
                    'nama_pengguna' => $item['nama_pengguna'],
                    'tahun' => $item['tahun'],
                    'bulan' => $item['bulan'],
                    'keterangan_laporan' => $item['keterangan_laporan'],
                    'status_laporan' => $item['status_laporan'],
                    'active' => ($item['deleted_at'] == null) ? true : false,
                ];
            }

            // Kirim respons dengan data laporan
            return $this->dataResponse($formattedData, self::HTTP_SUCCESS);
        } catch (\Throwable $th) {
            // Tangani kesalahan dan kirim respons error
            $message = 'Terjadi kesalahan dalam mengambil data laporan: ' . $th;
            return $this->messageResponse($message, self::HTTP_SERVER_ERROR);
        }
    }

    public function TambahLaporan(): Response
    {
        try {
            $decoded = JwtHelper::decodeTokenFromRequest($this->request);

            if (!$decoded) {
                return $this->messageResponse('Token tidak valid', self::HTTP_UNAUTHORIZED);
            }

            // Dapatkan nip_pengguna dari payload
            $nip_pengguna = $decoded->nip;

            $tahun = $this->request->getPost('tahun');
            $bulan = $this->request->getPost('bulan');
            $keterangan = $this->request->getPost('keterangan') ?? null;

            if (empty($nip_pengguna) || empty($tahun) || empty($bulan)) {
                $message = "Required field Tahun dan Bulan harus diisi.";
                return $this->messageResponse($message, self::HTTP_BAD_REQUEST);
            }

            $existingLaporan = $this->laporanModel
                ->where('nip_pengguna', $nip_pengguna)
                ->where('tahun', $tahun)
                ->where('bulan', $bulan)
                ->first();

            if ($existingLaporan) {
                $message = "Laporan untuk bulan dan tahun yang sama sudah ada.";
                return $this->messageResponse($message, self::HTTP_BAD_REQUEST);
            }

            // Masukkan data ke dalam tabel laporan
            $data = [
                'nip_pengguna' => $nip_pengguna,
                'tahun' => $tahun,
                'bulan' => $bulan,
                'keterangan' => $keterangan,
            ];

            $this->laporanModel->insert($data);

            // Kirim respons berhasil menambahkan laporan
            $message = "Berhasil menambahkan Laporan.";
            return $this->messageResponse($message, self::HTTP_SUCCESS_CREATE);
        } catch (\Throwable $th) {
            // Tangani kesalahan dan kirim respons error
            $message = 'Terjadi kesalahan dalam proses penambahan laporan: ' . $th;
            return $this->messageResponse($message, self::HTTP_SERVER_ERROR);
        }
    }

    public function SwitchStatusLaporan(): Response
    {
        try {
            $id = $this->request->getPost('id');

            if (empty($id)) {
                $message = "ID tidak ditemukan.";
                return $this->messageResponse($message, self::HTTP_BAD_REQUEST);
            }

            // Cek apakah laporam dengan ID tersebut ada di database
            $laporan = $this->laporanModel->withDeleted()->find($id);

            if (!$laporan) {
                $message = "Laporan dengan ID tersebut tidak ditemukan.";
                return $this->messageResponse($message, self::HTTP_BAD_REQUEST);
            }

            if ($laporan['deleted_at'] !== null) {
                $this->laporanModel->update($id, ['deleted_at' => null]);
                $message = "Berhasil mengaktifkan item laporan.";
            } else {
                $this->laporanModel->delete($id);
                $message = "Berhasil menonaktifkan item laporan.";
            }

            return $this->messageResponse($message, self::HTTP_SUCCESS);
        } catch (\Throwable $th) {
            // Tangani kesalahan dan kirim respons error
            $message = 'Terjadi kesalahan dalam proses aktivasi/non-aktivasi item laporan: ' . $th;
            return $this->messageResponse($message, self::HTTP_SERVER_ERROR);
        }
    }

    public function HapusLaporan(string $id): Response
    {
        try {
            if (empty($id)) {
                $message = "ID tidak ditemukan.";
                return $this->messageResponse($message, self::HTTP_BAD_REQUEST);
            }

            // Cek apakah laporam dengan ID tersebut ada di database
            $laporan = $this->laporanModel->withDeleted()->find($id);

            if (!$laporan) {
                $message = "Laporan dengan ID tersebut tidak ditemukan.";
                return $this->messageResponse($message, self::HTTP_BAD_REQUEST);
            }

            // Hapus permanen laporan
            $this->laporanModel->where('id', $id)->purgeDeleted();

            $message = "Berhasil menghapus permanen laporan dengan ID: $id.";
            return $this->messageResponse($message, self::HTTP_SUCCESS);
        } catch (\Throwable $th) {
            // Tangani kesalahan dan kirim respons error
            $message = 'Terjadi kesalahan dalam proses penghapusan permanen item laporan: ' . $th;
            return $this->messageResponse($message, self::HTTP_SERVER_ERROR);
        }
    }

    // Pengawas

    public function DaftarLaporanPengawas(): Response
    {
        try {
            $tahun = $this->request->getGet('tahun');
            $status = $this->request->getGet('status');

            // Buat query berdasarkan parameter filter
            $laporanQuery = $this->laporanModel
                ->select('laporan.*, laporan.status AS status_laporan, laporan.keterangan AS keterangan_laporan, pengguna.nama AS nama_pengguna')
                ->join('pengguna', 'pengguna.nip = laporan.nip_pengguna')
                ->groupBy('laporan.id')
                ->withDeleted();

            if (!empty($tahun)) {
                $laporanQuery->where('tahun', $tahun);
            } else {
                $tahunSekarang = date('Y');
                $laporanQuery->where('tahun', $tahunSekarang);
            }

            if (!empty($status)) {
                $laporanQuery->where('laporan.status', $status);
            } else {
                $laporanQuery->where('laporan.status', 'reporting');
            }

            // Eksekusi query
            $laporan = $laporanQuery->findAll();

            // Jika tidak ada laporan, kirim respons kosong
            if (empty($laporan)) {
                return $this->dataResponse([], self::HTTP_SUCCESS);
            }

            // Format data laporan
            // $formattedData = [];
            foreach ($laporan as $item) {
                $lastVerif = $this->verifikasiLaporanModel->where('id_laporan', $item['id'])->orderBy('id', 'DESC')->first();

                $formattedData[] = [
                    'id' => $item['id'],
                    'nip_pengguna' => $item['nip_pengguna'],
                    'nama_pengguna' => $item['nama_pengguna'],
                    'tahun' => $item['tahun'],
                    'bulan' => $item['bulan'],
                    'keterangan_laporan' => $item['keterangan_laporan'],
                    'status_laporan' => $item['status_laporan'],
                    'status_verifikasi' => $lastVerif['status'] ? $lastVerif['status'] : null,
                    'keterangan_verifikasi' => $lastVerif['keterangan'] ? $lastVerif['keterangan'] : null,
                    'active' => ($item['deleted_at'] == null) ? true : false,
                ];
            }

            // Kirim respons dengan data laporan
            return $this->dataResponse($formattedData, self::HTTP_SUCCESS);
        } catch (\Throwable $th) {
            // Tangani kesalahan dan kirim respons error
            $message = 'Terjadi kesalahan dalam mengambil data laporan: ' . $th;
            return $this->messageResponse($message, self::HTTP_SERVER_ERROR);
        }
    }
}
