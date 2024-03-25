<?php

namespace App\Models;

use CodeIgniter\Model;

class KegiatanModel extends Model
{
    protected $table            = 'kegiatan';
    protected $primaryKey       = 'id';
    protected $useSoftDeletes   = false;
    protected $allowedFields    = ['id_laporan', 'terealisasi', 'tanggal', 'target', 'nama', 'nip_pengguna', 'realisasi', 'keterangan', 'created_at', 'updated_at', 'deleted_at'];

    // Dates
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = 'deleted_at';

}
