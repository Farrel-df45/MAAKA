<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Guru extends Model
{
    protected $fillable = [
        'nip',
        'nama',
        'email',
        'nomor_telepon',
        'jabatan',
    ];

    public function user(): HasOne
    {
        return $this->hasOne(User::class);
    }

    public function waliKelas(): HasMany
    {
        return $this->hasMany(WaliKelas::class);
    }

    // Penugasan wali kelas pada tahun ajaran aktif (null jika belum ditugaskan)
    public function waliAktif(): HasOne
    {
        return $this->hasOne(WaliKelas::class)
            ->where('tahun_ajaran_id', TahunAjaran::aktif()?->id ?? 0);
    }

    public function aktivitas(): HasMany
    {
        return $this->hasMany(AktivitasGuru::class);
    }
}
