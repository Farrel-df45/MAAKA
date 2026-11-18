<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WaliKelas extends Model
{
    protected $table = 'wali_kelas';

    protected $fillable = ['guru_id', 'guru_nama', 'guru_nip', 'kelas_id', 'tahun_ajaran_id'];

    public function guru(): BelongsTo
    {
        return $this->belongsTo(Guru::class);
    }

    public function kelas(): BelongsTo
    {
        return $this->belongsTo(Kelas::class);
    }

    public function tahunAjaran(): BelongsTo
    {
        return $this->belongsTo(TahunAjaran::class);
    }

    // Tetapkan guru sebagai wali kelas pada tahun ajaran tertentu (menggantikan wali sebelumnya)
    public static function tetapkan(Guru $guru, Kelas $kelas, TahunAjaran $tahun): self
    {
        // Satu guru hanya memegang satu kelas per tahun ajaran
        static::where('guru_id', $guru->id)
            ->where('tahun_ajaran_id', $tahun->id)
            ->where('kelas_id', '!=', $kelas->id)
            ->delete();

        return static::updateOrCreate(
            ['kelas_id' => $kelas->id, 'tahun_ajaran_id' => $tahun->id],
            ['guru_id' => $guru->id, 'guru_nama' => $guru->nama, 'guru_nip' => $guru->nip],
        );
    }
}
