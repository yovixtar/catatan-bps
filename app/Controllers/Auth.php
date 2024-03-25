<?php

namespace App\Controllers;

use App\Models\PenggunaModel;
use CodeIgniter\API\ResponseTrait;
use Config\Token;
use \Firebase\JWT\JWT;
use CodeIgniter\HTTP\Response;

class Auth extends BaseController
{
    use ResponseTrait;
    protected $penggunaModel;

    const HTTP_SERVER_ERROR = 500;
    const HTTP_BAD_REQUEST = 400;
    const HTTP_UNAUTHORIZED = 401;
    const HTTP_SUCCESS = 200;
    const HTTP_SUCCESS_CREATE = 201;

    public function __construct()
    {
        $this->penggunaModel = new PenggunaModel();
    }

    public function login(): Response
    {
        try {
            // Mengambil request pengguna
            $nip = $this->request->getPost('nip');
            $password = $this->request->getPost('password');

            // Validasi request
            if (empty($nip) || empty($password)) {
                $message = "NIP dan password harus diisi.";
                return $this->messageResponse($message, self::HTTP_BAD_REQUEST);
            }

            if (!is_string($password)) {
                $message = "Password harus berupa string.";
                return $this->messageResponse($message, self::HTTP_BAD_REQUEST);
            }

            // Pencocokan data pengguna
            $hashedPassword = sha1($password);

            $user = $this->penggunaModel->find($nip);

            if (!$user || $user['password'] !== $hashedPassword) {
                $message = "Gagal Login. NIP atau password salah.";
                return $this->messageResponse($message, self::HTTP_UNAUTHORIZED);
            }

            $key = Token::JWT_SECRET_KEY;
            $payload = [
                'nip' => $user['nip'],
                'role' => $user['role'],
                'timestamp' => time(),
            ];
            $token = JWT::encode($payload, $key, 'HS256');

            $this->penggunaModel->update($user['nip'], ['token' => $token]);

            // Pengkondisian berhasil login
            $message = "Berhasil Login";
            $data = [
                'code' => self::HTTP_SUCCESS,
                'message' => $message,
                'token' => $token,
            ];
            return $this->respond($data, self::HTTP_SUCCESS);
        } catch (\Throwable $th) {
            // Tangani kesalahan dan kirim respons error
            $message = 'Terjadi kesalahan dalam proses login.';
            return $this->messageResponse($message, self::HTTP_SERVER_ERROR);
        }
    }

    public function DaftarPengguna(): Response
    {
        try {
            // Ambil data pengguna dari database
            $penggunaModel = new PenggunaModel();
            $pengguna = $penggunaModel->select('nip, nama, role')->findAll();

            // Jika tidak ada pengguna, kirim respons kosong
            if (empty($pengguna)) {
                $data = [
                    'code' => self::HTTP_SUCCESS,
                    'data' => [],
                ];
                return $this->respond($data, self::HTTP_SUCCESS);
            }

            // Format data nip dan nama dari pengguna
            $formattedData = array_map(function ($user) {
                return [
                    'nip' => $user['nip'],
                    'nama' => $user['nama'],
                    'role' => $user['role'],
                ];
            }, $pengguna);

            // Kirim respons dengan data nip dan nama semua pengguna (kecuali admin)
            $data = [
                'code' => self::HTTP_SUCCESS,
                'data' => $formattedData,
            ];
            return $this->respond($data, self::HTTP_SUCCESS);
        } catch (\Exception $e) {
            // Tangani kesalahan dan kirim respons error
            $message = 'Terjadi kesalahan dalam mengambil data pengguna.';
            return $this->messageResponse($message, self::HTTP_SERVER_ERROR);
        }
    }

    public function TambahPengguna(): Response
    {
        try {
            // Ambil data POST dari request
            $nip = $this->request->getPost('nip');
            $nama = $this->request->getPost('nama');
            $password = $this->request->getPost('password');
            $role = $this->request->getPost('role') ?? 'petugas';

            // Verifikasi request
            if (empty($nip) || empty($nama) || empty($password)) {
                $message = "NIP, nama, dan password harus diisi.";
                return $this->messageResponse($message, self::HTTP_BAD_REQUEST);
            }

            if (!is_string($password)) {
                $message = "Password harus berupa string.";
                return $this->messageResponse($message, self::HTTP_BAD_REQUEST);
            }

            if (!in_array($role, ['petugas', 'pengawas'])) {
                $message = "Role yang diberikan tidak valid.";
                return $this->messageResponse($message, self::HTTP_BAD_REQUEST);
            }

            // Hash password menggunakan SHA1
            $hashedPassword = sha1($password);

            // Cek apakah pengguna sudah ada berdasarkan NIP
            $existingUser = $this->penggunaModel->find($nip);

            if ($existingUser) {
                $message = "Pengguna dengan NIP tersebut sudah ada.";
                return $this->messageResponse($message, self::HTTP_BAD_REQUEST);
            }

            // Tambahkan pengguna baru ke dalam tabel pengguna
            $data = [
                'nip' => $nip,
                'nama' => $nama,
                'password' => $hashedPassword,
                'role' => $role,
            ];

            $this->penggunaModel->insert($data);

            // Kirim respons berhasil menambahkan pengguna
            $message = "Berhasil menambahkan pengguna.";
            $data = [
                'code' => self::HTTP_SUCCESS,
                'message' => $message,
            ];
            return $this->respond($data, self::HTTP_SUCCESS);
        } catch (\Throwable $th) {
            // Tangani kesalahan dan kirim respons error
            $message = 'Terjadi kesalahan dalam proses penambahan pengguna.';
            return $this->messageResponse($message, self::HTTP_SERVER_ERROR);
        }
    }

    public function GantiPassword(): Response
    {
        try {
            $nip = $this->request->getPost('nip');
            $password_baru = $this->request->getPost('password-baru');

            if (empty($nip) || empty($password_baru)) {
                $message = "NIP dan password (Baru) harus diisi.";
                return $this->messageResponse($message, self::HTTP_BAD_REQUEST);
            }

            if (!is_string($password_baru)) {
                $message = "Password harus berupa string.";
                return $this->messageResponse($message, self::HTTP_BAD_REQUEST);
            }

            // Hash password baru menggunakan SHA1
            $hashedPassword = sha1($password_baru);

            // Cek apakah pengguna dengan NIP tersebut ada di database
            $existingUser = $this->penggunaModel->find($nip);

            if (!$existingUser) {
                $message = "Pengguna dengan NIP tersebut tidak ditemukan.";
                return $this->messageResponse($message, 404);
            }

            // Update password untuk pengguna yang bersangkutan
            $this->penggunaModel->set(['password' => $hashedPassword])->where('nip', $nip)->update();

            // Kirim respons berhasil mengubah password
            $message = "Berhasil mengubah password.";
            return $this->messageResponse($message, self::HTTP_SUCCESS);
        } catch (\Throwable $th) {
            // Tangani kesalahan dan kirim respons error
            $message = 'Terjadi kesalahan dalam proses pengubahan password.';
            return $this->messageResponse($message, self::HTTP_SERVER_ERROR);
        }
    }

    public function GantiNama(): Response
    {
        try {
            $nip = $this->request->getPost('nip');
            $nama_baru = $this->request->getPost('nama-baru');

            if (empty($nip) || empty($nama_baru)) {
                $message = "NIP dan Nama (Baru) harus diisi.";
                return $this->messageResponse($message, self::HTTP_BAD_REQUEST);
            }

            // Cek apakah pengguna dengan NIP tersebut ada di database
            $existingUser = $this->penggunaModel->find($nip);

            if (!$existingUser) {
                $message = "Pengguna dengan NIP tersebut tidak ditemukan.";
                return $this->messageResponse($message, 404);
            }

            // Update password untuk pengguna yang bersangkutan
            $this->penggunaModel->set(['nama' => $nama_baru])->where('nip', $nip)->update();

            // Kirim respons berhasil mengubah nama
            $message = "Berhasil mengubah nama.";
            return $this->messageResponse($message, self::HTTP_SUCCESS);
        } catch (\Throwable $th) {
            // Tangani kesalahan dan kirim respons error
            $message = 'Terjadi kesalahan dalam proses pengubahan nama.';
            return $this->messageResponse($message, self::HTTP_SERVER_ERROR);
        }
    }

    public function SwitchStatusPengguna(): Response
    {
        try {
            $nip = $this->request->getPost('nip');

            if (empty($nip)) {
                $message = "NIP harus diisi.";
                return $this->messageResponse($message, self::HTTP_BAD_REQUEST);
            }

            // Cek apakah pengguna dengan NIP tersebut ada di database
            $pengguna = $this->penggunaModel->withDeleted()->find($nip);

            if (!$pengguna) {
                $message = "Pengguna dengan NIP tersebut tidak ditemukan.";
                return $this->messageResponse($message, self::HTTP_BAD_REQUEST);
            }

            if ($pengguna['deleted_at'] !== null) {
                $this->penggunaModel->update($nip, ['deleted_at' => null]);
                $message = "Berhasil mengaktifkan akun pengguna.";
            } else {
                $this->penggunaModel->delete($nip);
                $message = "Berhasil menonaktifkan akun pengguna.";
            }

            return $this->messageResponse($message, self::HTTP_SUCCESS);
        } catch (\Throwable $th) {
            // Tangani kesalahan dan kirim respons error
            $message = 'Terjadi kesalahan dalam proses aktivasi/non-aktivasi akun pengguna.' . $th;
            return $this->messageResponse($message, self::HTTP_SERVER_ERROR);
        }
    }

    public function HapusPengguna(string $nip): Response
    {
        try {
            if (empty($nip)) {
                $message = "NIP harus diisi.";
                return $this->messageResponse($message, self::HTTP_BAD_REQUEST);
            }

            // Periksa apakah pengguna dengan NIP tersebut ada di database
            $pengguna = $this->penggunaModel->withDeleted()->find($nip);

            if (!$pengguna) {
                $message = "Pengguna dengan NIP tersebut tidak ditemukan.";
                return $this->messageResponse($message, self::HTTP_BAD_REQUEST);
            }

            // Hapus permanen pengguna
            $this->penggunaModel->where('nip', $nip)->purgeDeleted();

            $message = "Berhasil menghapus permanen akun pengguna dengan NIP: $nip.";
            return $this->messageResponse($message, self::HTTP_SUCCESS);
        } catch (\Throwable $th) {
            // Tangani kesalahan dan kirim respons error
            $message = 'Terjadi kesalahan dalam proses penghapusan permanen akun pengguna.';
            return $this->messageResponse($message, self::HTTP_SERVER_ERROR);
        }
    }
}
