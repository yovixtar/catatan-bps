<?php

namespace App\Controllers;

use CodeIgniter\API\ResponseTrait;
use Config\Token;
use \Firebase\JWT\JWT;

class Auth extends BaseController
{
    use ResponseTrait;
    // : \CodeIgniter\HTTP\Response
    public function login()
    {
        // Ambil data POST dari request
        $nip = $this->request->getPost('nip');
        $password = $this->request->getPost('password');

        if (empty($nip) || empty($password)) {
            $message = "NIP dan password harus diisi.";
            $data = [
                'code' => 400,
                'message' => $message,
            ];
            return $this->respond($data, 400);
        }

        if (!is_string($password)) {
            $message = "Password harus berupa string.";
            $data = [
                'code' => 400,
                'message' => $message,
            ];
            return $this->respond($data, 400);
        }

        // Hash password menggunakan SHA1
        $hashedPassword = sha1($password);

        // Ambil data pengguna dari database berdasarkan NIP
        $db = \Config\Database::connect();
        $user = $db->table('pengguna')->where('nip', $nip)->get()->getRow();

        // Jika pengguna tidak ditemukan atau password tidak cocok, kirim respons error
        if (!$user || $user->password !== $hashedPassword) {
            $message = "Gagal Login. NIP atau password salah.";
            $data = [
                'code' => 401,
                'message' => $message,
            ];
            return $this->respond($data, 401);
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
        $db->table('pengguna')->set('token', $token)->where('nip', $nip)->update();

        // Kirim respons berhasil login dengan data token
        $message = "Berhasil Login";
        $data = [
            'code' => 200,
            'message' => $message,
            'token' => $token,
        ];
        return $this->respond($data, 200);
    }
}
