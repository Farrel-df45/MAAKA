<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AktivitasGuru extends Model
{
    public const UNGGAH = 'Unggah';
    public const UBAH = 'Ubah';
    public const HAPUS = 'Hapus';

    public const AKSI = [self::UNGGAH, self::UBAH, self::HAPUS];

    protected $fillable = [
        'guru_id', 'guru_nama', 'guru_nip', 'kelas_nama', 'tahun_ajaran_nama', 'aksi',
        'dokumen_id', 'nama_dokumen', 'nama_siswa', 'keterangan',
    ];

    public function guru(): BelongsTo
    {
        return $this->belongsTo(Guru::class);
    }

    public function dokumen(): BelongsTo
    {
        return $this->belongsTo(Dokumen::class);
    }

    // Catat satu aktivitas guru terhadap sebuah dokumen
    public static function catat(Guru $guru, string $aksi, Dokumen $dokumen, ?string $keterangan = null): self
    {
        $wali = $guru->waliAktif()->with(['kelas', 'tahunAjaran'])->first();

        return self::create([
            'guru_id' => $guru->id,
            'guru_nama' => $guru->nama,
            'guru_nip' => $guru->nip,
            // Salinan kelas saat aksi terjadi agar riwayat tetap benar setelah rolling
            'kelas_nama' => $wali?->kelas?->nama,
            'tahun_ajaran_nama' => $wali?->tahunAjaran?->nama,
            'aksi' => $aksi,
            // Dokumen yang baru dihapus sudah tidak ada di database
            'dokumen_id' => $aksi === self::HAPUS ? null : $dokumen->id,
            'nama_dokumen' => $dokumen->nama_dokumen,
            'nama_siswa' => $dokumen->siswa?->nama,
            'keterangan' => $keterangan,
        ]);
    }
}
