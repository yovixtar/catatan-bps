<?php

namespace App\Controllers;

use App\Helpers\JwtHelper;
use CodeIgniter\API\ResponseTrait;

class Kegiatan extends BaseController
{
    use ResponseTrait;
    private $db;

    const HTTP_SERVER_ERROR = 500;
    const HTTP_BAD_REQUEST = 400;
    const HTTP_UNAUTHORIZED = 401;
    const HTTP_SUCCESS = 200;
    const HTTP_SUCCESS_CREATE = 201;

    public function __construct()
    {
        $this->db = \Config\Database::connect();;
    }

    public function DaftarKegiatan(): \CodeIgniter\HTTP\Response
    {
        try {
            // Ambil data kegiatan dari database
            $kegiatan = $this->db->table('kegiatan')
                ->select('kegiatan.*, pengguna.nama AS nama_pencatat')
                ->join('pengguna', 'pengguna.nip = kegiatan.nip_pencatat')
                ->get()
                ->getResult();

            // Jika tidak ada kegiatan, kirim respons kosong
            if (empty($kegiatan)) {
                return $this->dataResponse([], self::HTTP_SUCCESS);
            }

            // Format data kegiatan
            $formattedData = [];
            foreach ($kegiatan as $item) {
                $terealisasi = $item->terealisasi === '1' ? true : false;

                $formattedData[] = [
                    'id' => $item->id,
                    'terealisasi' => $terealisasi,
                    'tanggal' => $item->tanggal,
                    'target' => $item->target,
                    'nama' => $item->nama,
                    'nip_pencatat' => $item->nip_pencatat,
                    'nama_pencatat' => $item->nama_pencatat,
                    'realisasi' => $item->realisasi,
                    'keterangan' => $item->keterangan,
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

    public function TambahTarget(): \CodeIgniter\HTTP\Response
    {
        try {

            $decoded = JwtHelper::decodeTokenFromRequest($this->request);

            if (!$decoded) {
                return $this->messageResponse('Token tidak valid', self::HTTP_UNAUTHORIZED);
            }

            // Dapatkan nip_pencatat dari payload
            $nip_pencatat = $decoded->nip;

            $nama_kegiatan = $this->request->getPost('nama');
            $tanggal = $this->request->getPost('tanggal');
            $target = $this->request->getPost('target');

            if (empty($nama_kegiatan) || empty($tanggal) || empty($target) || empty($nip_pencatat)) {
                $message = "Semua field harus diisi.";
                return $this->messageResponse($message, self::HTTP_BAD_REQUEST);
            }

            // Lakukan validasi tanggal di sini jika diperlukan

            // Masukkan data ke dalam tabel kegiatan
            $data = [
                'tanggal' => $tanggal,
                'target' => $target,
                'nama' => $nama_kegiatan,
                'nip_pencatat' => $nip_pencatat,
            ];

            $this->db->table('kegiatan')->insert($data);

            // Kirim respons berhasil menambahkan target kegiatan
            $message = "Berhasil menambahkan target kegiatan.";
            return $this->messageResponse($message, self::HTTP_SUCCESS_CREATE);
        } catch (\Throwable $th) {
            // Tangani kesalahan dan kirim respons error
            $message = 'Terjadi kesalahan dalam proses penambahan target kegiatan.';
            return $this->messageResponse($message, self::HTTP_SERVER_ERROR);
        }
    }

    public function TambahRealisasi(): \CodeIgniter\HTTP\Response
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
            $existingKegiatan = $this->db->table('kegiatan')->where('id', $id_kegiatan)->get()->getRow();

            if (!$existingKegiatan) {
                $message = "Kegiatan dengan ID tersebut tidak ditemukan.";
                return $this->messageResponse($message, self::HTTP_BAD_REQUEST);
            }

            // Lakukan pembaruan pada tabel kegiatan
            $data = [
                'terealisasi' => true,
                'realisasi' => $realisasi,
                'keterangan' => $keterangan,
            ];

            $this->db->table('kegiatan')->where('id', $id_kegiatan)->update($data);

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
            $kegiatan = $this->db->table('kegiatan')->where('id', $id)->get()->getRow();

            // Jika kegiatan tidak ditemukan, kirim respons 404
            if (!$kegiatan) {
                return $this->messageResponse('Kegiatan tidak ditemukan', self::HTTP_BAD_REQUEST);
            }

            // Hapus kegiatan dari database
            $this->db->table('kegiatan')->where('id', $id)->delete();

            // Kirim respons berhasil menghapus kegiatan
            return $this->messageResponse('Berhasil menghapus kegiatan', self::HTTP_SUCCESS);
        } catch (\Exception $e) {
            // Tangani kesalahan dan kirim respons error
            $message = 'Terjadi kesalahan dalam menghapus kegiatan: ' . $e->getMessage();
            return $this->messageResponse($message, self::HTTP_SERVER_ERROR);
        }
    }
}
