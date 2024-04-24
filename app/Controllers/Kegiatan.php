<?php

namespace App\Controllers;

use App\Helpers\JwtHelper;
use App\Models\KegiatanModel;
use CodeIgniter\API\ResponseTrait;
use CodeIgniter\HTTP\Response;

class Kegiatan extends BaseController
{
    use ResponseTrait;
    protected $kegiatanModel;

    const HTTP_SERVER_ERROR = 500;
    const HTTP_BAD_REQUEST = 400;
    const HTTP_UNAUTHORIZED = 401;
    const HTTP_SUCCESS = 200;
    const HTTP_SUCCESS_CREATE = 201;

    public function __construct()
    {
        $this->kegiatanModel = new KegiatanModel();
    }

    public function DaftarKegiatan(): Response
    {
        try {
            $id_laporan = $this->request->getGet('id_laporan') ? $this->request->getGet('id_laporan') : '';
            $keyword = $this->request->getGet('keyword') ? $this->request->getGet('keyword') : '';

            // Buat query berdasarkan keberadaan id_laporan
            $query = $this->kegiatanModel;
            if ($id_laporan != '' || !empty($id_laporan)) {
                $query->where('id_laporan', $id_laporan);
            }

            // Buat query berdasarkan keberadaan keyword
            if ($keyword != '' || !empty($keyword)) {
                $query->groupStart()
                    ->like('kegiatan.nama', $keyword)
                    ->orLike('keterangan', $keyword)
                    ->groupEnd();
            }

            $kegiatan = $query->join('pengguna', 'pengguna.nip = kegiatan.nip_pengguna', 'left')
                ->select('kegiatan.*, pengguna.nama AS nama_pengguna')
                ->findAll();

            // Jika tidak ada kegiatan, kirim respons kosong
            if (empty($kegiatan)) {
                return $this->dataResponse([], self::HTTP_SUCCESS);
            }

            // Format data kegiatan
            $formattedData = [];
            foreach ($kegiatan as $item) {

                $formattedData[] = [
                    'id' => $item['id'],
                    'tanggal' => $item['tanggal'],
                    'target' => $item['target'],
                    'nama' => $item['nama'],
                    'nip_pengguna' => $item['nip_pengguna'],
                    'nama_pengguna' => $item['nama_pengguna'],
                    'realisasi' => $item['realisasi'],
                    'keterangan' => $item['keterangan'],
                ];
            }

            // Kirim respons dengan data kegiatan
            return $this->dataResponse($formattedData, self::HTTP_SUCCESS);
        } catch (\Exception $e) {
            // Tangani kesalahan dan kirim respons error
            $message = 'Terjadi kesalahan dalam mengambil data kegiatan: ' . $e->getMessage();
            return $this->messageResponse($message, self::HTTP_SERVER_ERROR);
        }
    }

    public function TambahTarget(): Response
    {
        try {

            $decoded = JwtHelper::decodeTokenFromRequest($this->request);

            if (!$decoded) {
                return $this->messageResponse('Token tidak valid', self::HTTP_UNAUTHORIZED);
            }

            // Dapatkan nip_pengguna dari payload
            $nip_pengguna = $decoded->nip;

            $id_laporan = $this->request->getPost('id_laporan');
            $nama_kegiatan = $this->request->getPost('nama');
            $tanggal = $this->request->getPost('tanggal');
            $target = $this->request->getPost('target');

            if (empty($nama_kegiatan) || empty($tanggal) || empty($target) || empty($nip_pengguna)) {
                $message = "Semua field harus diisi.";
                return $this->messageResponse($message, self::HTTP_BAD_REQUEST);
            }

            // Masukkan data ke dalam tabel kegiatan
            $data = [
                'id_laporan' => $id_laporan,
                'tanggal' => $tanggal,
                'target' => $target,
                'nama' => $nama_kegiatan,
                'nip_pengguna' => $nip_pengguna,
            ];

            $this->kegiatanModel->insert($data);

            // Kirim respons berhasil menambahkan target kegiatan
            $message = "Berhasil menambahkan target kegiatan.";
            return $this->messageResponse($message, self::HTTP_SUCCESS_CREATE);
        } catch (\Throwable $th) {
            // Tangani kesalahan dan kirim respons error
            $message = 'Terjadi kesalahan dalam proses penambahan target kegiatan.';
            return $this->messageResponse($message, self::HTTP_SERVER_ERROR);
        }
    }

    public function TambahRealisasi(): Response
    {
        try {
            // Ambil data POST dari request
            $id_kegiatan = $this->request->getPost('id');
            $realisasi = $this->request->getPost('realisasi');
            $keterangan = $this->request->getPost('keterangan');

            if (empty($id_kegiatan) || empty($realisasi)) {
                $message = "ID kegiatan dan realisasi harus diisi.";
                return $this->messageResponse($message, self::HTTP_BAD_REQUEST);
            }

            // Cek apakah kegiatan dengan ID tersebut ada di database
            $existingKegiatan = $this->kegiatanModel->find($id_kegiatan);

            if (!$existingKegiatan) {
                $message = "Kegiatan dengan ID tersebut tidak ditemukan.";
                return $this->messageResponse($message, self::HTTP_BAD_REQUEST);
            }

            // Lakukan pembaruan pada tabel kegiatan
            $data = [
                'realisasi' => $realisasi,
                'keterangan' => $keterangan,
            ];

            $this->kegiatanModel->update($id_kegiatan, $data);

            // Kirim respons berhasil mengubah data kegiatan
            $message = "Berhasil menambahkan realisasi kegiatan.";
            return $this->messageResponse($message, self::HTTP_SUCCESS);
        } catch (\Exception $e) {
            // Tangani kesalahan dan kirim respons error
            $message = 'Terjadi kesalahan dalam menambah realisasi kegiatan: ' . $e->getMessage();
            return $this->messageResponse($message, self::HTTP_SERVER_ERROR);
        }
    }

    public function HapusKegiatan(int $id): \CodeIgniter\HTTP\Response
    {
        try {
            // Cari kegiatan berdasarkan ID
            $kegiatan = $this->kegiatanModel->find($id);

            // Jika kegiatan tidak ditemukan, kirim respons 404
            if (!$kegiatan) {
                return $this->messageResponse('Kegiatan tidak ditemukan', self::HTTP_BAD_REQUEST);
            }

            // Hapus kegiatan dari database
            $this->kegiatanModel->where('id', $id)->delete();

            // Kirim respons berhasil menghapus kegiatan
            return $this->messageResponse('Berhasil menghapus kegiatan', self::HTTP_SUCCESS);
        } catch (\Exception $e) {
            // Tangani kesalahan dan kirim respons error
            $message = 'Terjadi kesalahan dalam menghapus kegiatan: ' . $e->getMessage();
            return $this->messageResponse($message, self::HTTP_SERVER_ERROR);
        }
    }
}
