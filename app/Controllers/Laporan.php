<?php

namespace App\Controllers;

use App\Helpers\JwtHelper;
use App\Models\LaporanModel;
use \CodeIgniter\HTTP\Response;

class Laporan extends BaseController
{

    private $laporanModel;

    const HTTP_SERVER_ERROR = 500;
    const HTTP_BAD_REQUEST = 400;
    const HTTP_UNAUTHORIZED = 401;
    const HTTP_SUCCESS = 200;
    const HTTP_SUCCESS_CREATE = 201;

    public function __construct()
    {
        $this->laporanModel = new LaporanModel();
    }

    public function DaftarLaporan(): Response
    {
        try {
            $tahun = $this->request->getGet('tahun');
            $status = $this->request->getGet('status');

            // Buat query berdasarkan parameter filter
            $laporanQuery = $this->laporanModel->select('laporan.*, pengguna.nama AS nama_pengguna, verifikasi.status AS status_verifikasi, verifikasi.keterangan AS keterangan_verifikasi')
                ->join('pengguna', 'pengguna.nip = laporan.nip_pengguna')
                ->join('verifikasi', 'verifikasi.id_laporan = laporan.id')
                ->join(
                    "(SELECT id_laporan, MAX(id) AS max_id FROM verifikasi GROUP BY id_laporan) AS latest_verifikasi",
                    "latest_verifikasi.id_laporan = laporan.id AND verifikasi.id = latest_verifikasi.max_id"
                );

            if (!empty($tahun)) {
                $laporanQuery->where('tahun', $tahun);
            }

            if (!empty($status)) {
                $laporanQuery->where('verifikasi.status', $status);
            }

            // Eksekusi query
            $laporan = $laporanQuery->findAll();

            // Jika tidak ada laporan, kirim respons kosong
            if (empty($laporan)) {
                return $this->dataResponse([], self::HTTP_SUCCESS);
            }

            // Format data laporan
            $formattedData = [];
            foreach ($laporan as $item) {
                $formattedData[] = [
                    'id' => $item->id,
                    'nip_pengguna' => $item->nip_pengguna,
                    'nama_pengguna' => $item->nama_pengguna,
                    'tahun' => $item->tahun,
                    'bulan' => $item->bulan,
                    'keterangan_laporan' => $item->keterangan,
                    'status_laporan' => $item->status,
                    'status_verifikasi' => $item->status_verifikasi,
                    'keterangan_verifikasi' => $item->keterangan_verifikasi,
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

            // Masukkan data ke dalam tabel laporan
            $data = [
                'nip_petugas' => $nip_pengguna,
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
}
