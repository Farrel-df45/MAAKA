<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Siswa extends Model
{
    protected $fillable = [
        'nis',
        'nama',
        'kelas',
        'jurusan',
        'alamat',
    ];

    public function user(): HasOne
    {
        return $this->hasOne(User::class);
    }

    public function riwayatKelas(): HasMany
    {
        return $this->hasMany(RiwayatKelas::class);
    }

    // Kelas siswa pada tahun ajaran aktif
    public function riwayatAktif(): HasOne
    {
        return $this->hasOne(RiwayatKelas::class)
            ->where('tahun_ajaran_id', TahunAjaran::aktif()?->id ?? 0);
    }

    // Tempatkan siswa di kelas pada tahun ajaran tertentu dan samakan label kelas saat ini
    public function tempatkanDiKelas(Kelas $kelas, TahunAjaran $tahun): RiwayatKelas
    {
        $riwayat = RiwayatKelas::updateOrCreate(
            ['siswa_id' => $this->id, 'tahun_ajaran_id' => $tahun->id],
            ['kelas_id' => $kelas->id],
        );

        if ($tahun->aktif) {
            $this->forceFill(['kelas' => $kelas->nama])->save();
        }

        return $riwayat;
    }

    public function dokumens(): HasMany
    {
        return $this->hasMany(Dokumen::class);
    }

    public function legalisirs(): HasMany
    {
        return $this->hasMany(Legalisir::class);
    }

    public function peminjamanRapors(): HasMany
    {
        return $this->hasMany(PeminjamanRapor::class);
    }

    public function pengambilanIjazahs(): HasMany
    {
        return $this->hasMany(PengambilanIjazah::class);
    }

    // Lulus: pernah punya penempatan kelas, tetapi tidak lagi punya kelas pada tahun ajaran aktif
    public function isLulus(): bool
    {
        return $this->riwayatAktif === null && $this->riwayatKelas()->exists();
    }
}