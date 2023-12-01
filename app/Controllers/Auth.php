<?php

namespace App\Controllers;

use CodeIgniter\API\ResponseTrait;
use Config\Token;
use \Firebase\JWT\JWT;

class Auth extends BaseController
{
    use ResponseTrait;
    private $db;

    public function __construct()
    {
        $this->db = \Config\Database::connect();;
    }

    public function Login(): \CodeIgniter\HTTP\Response
    {
        try {
            // Ambil data POST dari request
            $nip = $this->request->getPost('nip');
            $password = $this->request->getPost('password');

            if (empty($nip) || empty($password)) {
                $message = "NIP dan password harus diisi.";
                return $this->messageResponse($message, 400);
            }

            if (!is_string($password)) {
                $message = "Password harus berupa string.";
                return $this->messageResponse($message, 400);
            }

            // Hash password menggunakan SHA1
            $hashedPassword = sha1($password);

            // Ambil data pengguna dari database berdasarkan NIP
            $user = $this->db->table('pengguna')->where('nip', $nip)->get()->getRow();

            // Jika pengguna tidak ditemukan atau password tidak cocok, kirim respons error
            if (!$user || $user->password !== $hashedPassword) {
                $message = "Gagal Login. NIP atau password salah.";
                return $this->messageResponse($message, 401);
            }

            // Buat token JWT dengan NIP dan role (misalnya, asumsi ada kolom 'role' pada tabel pengguna)
            $key = Token::JWT_SECRET_KEY;

            $payload = array(
                'nip' => $user->nip,
                'role' => $user->role,
                'timestamp' => time(),
            );

            $token = JWT::encode($payload, $key, 'HS256');

            // Simpan token ke dalam tabel pengguna
            $this->db->table('pengguna')->set('token', $token)->where('nip', $nip)->update();

            // Kirim respons berhasil login dengan data token
            $message = "Berhasil Login";
            $data = [
                'code' => 200,
                'message' => $message,
                'token' => $token,
            ];
            return $this->respond($data, 200);
        } catch (\Throwable $th) {
            // Tangani kesalahan dan kirim respons error
            $message = 'Terjadi kesalahan dalam proses login.';
            return $this->messageResponse($message, 500);
        }
    }

    public function DaftarPengguna(): \CodeIgniter\HTTP\Response
    {
        try {
            // Ambil data pengguna dari database kecuali yang memiliki role "admin"
            $pengguna = $this->db->table('pengguna')
                ->where('role !=', 'admin')
                ->select('nip, nama')
                ->get()
                ->getResult();

            // Jika tidak ada pengguna, kirim respons kosong
            if (empty($pengguna)) {
                $data = [
                    'code' => 200,
                    'data' => [],
                ];
                return $this->respond($data, 200);
            }

            // Format data nip dan nama dari pengguna
            $formattedData = array_map(function ($user) {
                return [
                    'nip' => $user->nip,
                    'nama' => $user->nama,
                ];
            }, $pengguna);

            // Kirim respons dengan data nip dan nama semua pengguna (kecuali admin)
            $data = [
                'code' => 200,
                'data' => $formattedData,
            ];
            return $this->respond($data, 200);
        } catch (\Exception $e) {
            // Tangani kesalahan dan kirim respons error
            $message = 'Terjadi kesalahan dalam mengambil data pengguna.';
            return $this->messageResponse($message, 500);
        }
    }

    public function TambahPengguna(): \CodeIgniter\HTTP\Response
    {
        try {
            // Ambil data POST dari request
            $nip = $this->request->getPost('nip');
            $nama = $this->request->getPost('nama');
            $password = $this->request->getPost('password');

            if (empty($nip) || empty($nama) || empty($password)) {
                $message = "NIP, nama, dan password harus diisi.";
                return $this->messageResponse($message, 400);
            }

            if (!is_string($password)) {
                $message = "Password harus berupa string.";
                return $this->messageResponse($message, 400);
            }

            // Hash password menggunakan SHA1
            $hashedPassword = sha1($password);

            // Cek apakah pengguna sudah ada berdasarkan NIP
            $existingUser = $this->db->table('pengguna')->where('nip', $nip)->get()->getRow();

            if ($existingUser) {
                $message = "Pengguna dengan NIP tersebut sudah ada.";
                return $this->messageResponse($message, 400);
            }

            // Tambahkan pengguna baru ke dalam tabel pengguna
            $data = [
                'nip' => $nip,
                'nama' => $nama,
                'password' => $hashedPassword,
            ];

            $this->db->table('pengguna')->insert($data);

            // Kirim respons berhasil menambahkan pengguna
            $message = "Berhasil menambahkan pengguna.";
            $data = [
                'code' => 200,
                'message' => $message,
            ];
            return $this->respond($data, 200);
        } catch (\Throwable $th) {
            // Tangani kesalahan dan kirim respons error
            $message = 'Terjadi kesalahan dalam proses penambahan pengguna.';
            return $this->messageResponse($message, 500);
        }
    }

    public function GantiPassword(): \CodeIgniter\HTTP\Response
    {
        try {
            $nip = $this->request->getPost('nip');
            $password_baru = $this->request->getPost('password-baru');

            if (empty($nip) || empty($password_baru)) {
                $message = "NIP dan password (Baru) harus diisi.";
                return $this->messageResponse($message, 400);
            }

            if (!is_string($password_baru)) {
                $message = "Password harus berupa string.";
                return $this->messageResponse($message, 400);
            }

            // Hash password baru menggunakan SHA1
            $hashedPassword = sha1($password_baru);

            // Cek apakah pengguna dengan NIP tersebut ada di database
            $existingUser = $this->db->table('pengguna')->where('nip', $nip)->get()->getRow();

            if (!$existingUser) {
                $message = "Pengguna dengan NIP tersebut tidak ditemukan.";
                return $this->messageResponse($message, 404);
            }

            // Update password untuk pengguna yang bersangkutan
            $this->db->table('pengguna')->set('password', $hashedPassword)->where('nip', $nip)->update();

            // Kirim respons berhasil mengubah password
            $message = "Berhasil mengubah password.";
            return $this->messageResponse($message, 200);

        } catch (\Throwable $th) {
            // Tangani kesalahan dan kirim respons error
            $message = 'Terjadi kesalahan dalam proses penambahan pengguna.';
            return $this->messageResponse($message, 500);
        }
    }

    public function HapusAkun(String $nip): \CodeIgniter\HTTP\Response {
        try {    
            
            if (empty($nip)) {
                $message = "NIP harus diisi.";
                return $this->messageResponse($message, 400);
            }
    
            // Cek apakah pengguna dengan NIP tersebut ada di database
            $existingUser = $this->db->table('pengguna')->where('nip', $nip)->get()->getRow();
    
            if (!$existingUser) {
                $message = "Pengguna dengan NIP tersebut tidak ditemukan.";
                return $this->messageResponse($message, 404);
            }
    
            // Hapus akun pengguna
            $this->db->table('pengguna')->where('nip', $nip)->delete();
    
            // Kirim respons berhasil menghapus akun
            $message = "Berhasil menghapus akun pengguna.";
            return $this->messageResponse($message, 200);
    
        } catch (\Throwable $th) {
            // Tangani kesalahan dan kirim respons error
            $message = 'Terjadi kesalahan dalam proses penghapusan akun pengguna.';
            return $this->messageResponse($message, 500);
        }
    }
    
}
