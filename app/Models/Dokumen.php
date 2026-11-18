<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Dokumen extends Model
{
    public const STATUS_MENUNGGU = 'Menunggu Verifikasi';
    public const STATUS_TERVERIFIKASI = 'Terverifikasi';
    public const STATUS_DITOLAK = 'Ditolak';

    public const STATUSES = [
        self::STATUS_MENUNGGU,
        self::STATUS_TERVERIFIKASI,
        self::STATUS_DITOLAK,
    ];

    public const KATEGORI = ['Rapor', 'Ijazah', 'Sertifikat', 'Transkrip Nilai', 'Lainnya'];

    protected $fillable = [
        'siswa_id',
        'kelas_id',
        'tahun_ajaran_id',
        'diunggah_oleh',
        'nama_dokumen',
        'kategori',
        'file_path',
        'status',
        'catatan',
    ];

    // Cap kelas & tahun ajaran dari penempatan siswa saat dokumen dibuat,
    // supaya dokumen tetap tercatat di kelas lama setelah siswa naik kelas.
    protected static function booted(): void
    {
        static::creating(function (Dokumen $dokumen) {
            if ($dokumen->kelas_id || !$dokumen->siswa_id) {
                return;
            }

            $penempatan = Siswa::find($dokumen->siswa_id)?->riwayatAktif;

            if ($penempatan) {
                $dokumen->kelas_id = $penempatan->kelas_id;
                $dokumen->tahun_ajaran_id = $penempatan->tahun_ajaran_id;
            }
        });

        // Beri tahu siswa saat dokumennya diverifikasi atau ditolak
        static::updated(function (Dokumen $dokumen) {
            if ($dokumen->status !== self::STATUS_MENUNGGU) {
                Notifikasi::karenaStatus($dokumen, 'Dokumen ' . $dokumen->nama_dokumen, route('siswa.dokumen.index'));
            }
        });
    }

    public function kelas(): BelongsTo
    {
        return $this->belongsTo(Kelas::class);
    }

    public function tahunAjaran(): BelongsTo
    {
        return $this->belongsTo(TahunAjaran::class);
    }

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class);
    }

    public function pengunggah(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diunggah_oleh');
    }

    public function legalisirs()
    {
        return $this->hasMany(Legalisir::class);
    }
}