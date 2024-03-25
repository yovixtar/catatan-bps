<?php

namespace App\Models;

use CodeIgniter\Model;

class VerifikasiModel extends Model
{
    protected $table            = 'verifikasi';
    protected $primaryKey       = 'id';
    protected $useSoftDeletes   = false;
    protected $allowedFields    = ['id_laporan', 'nip_pengguna', 'status', 'keterangan', 'created_at', 'updated_at', 'deleted_at'];

    // Dates
    protected $useTimestamps = false;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = 'deleted_at';

}
