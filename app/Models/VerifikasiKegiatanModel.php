<?php

namespace App\Models;

use CodeIgniter\Model;

class VerifikasiKegiatanModel extends Model
{
    protected $table            = 'verifikasi_kegiatan';
    protected $primaryKey       = 'id';
    protected $useSoftDeletes   = false;
    protected $allowedFields    = ['id_kegiatan', 'id_laporan', 'nip_pengguna', 'nip_verifikator', 'status', 'batch', 'keterangan', 'created_at', 'updated_at', 'deleted_at'];

    // Dates
    protected $useTimestamps = false;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = 'deleted_at';

}
