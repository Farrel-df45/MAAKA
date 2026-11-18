<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Kelas extends Model
{
    protected $table = 'kelas';

    public const TINGKAT = ['X', 'XI', 'XII'];

    protected $fillable = ['nama', 'tingkat', 'jurusan'];

    public function riwayat(): HasMany
    {
        return $this->hasMany(RiwayatKelas::class);
    }

    public function waliKelas(): HasMany
    {
        return $this->hasMany(WaliKelas::class);
    }

    // Wali kelas pada tahun ajaran aktif
    public function waliAktif(): HasOne
    {
        return $this->hasOne(WaliKelas::class)
            ->where('tahun_ajaran_id', TahunAjaran::aktif()?->id ?? 0);
    }

    // Siswa yang berada di kelas ini pada tahun ajaran aktif
    public function siswaAktif(): HasMany
    {
        return $this->hasMany(RiwayatKelas::class)
            ->where('tahun_ajaran_id', TahunAjaran::aktif()?->id ?? 0);
    }
}
