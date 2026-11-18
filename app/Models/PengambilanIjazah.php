<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PengambilanIjazah extends Model
{
    public const STATUS_MENUNGGU = 'Menunggu';
    public const STATUS_DISETUJUI = 'Disetujui';
    public const STATUS_DIAMBIL = 'Sudah Diambil';
    public const STATUS_DITOLAK = 'Ditolak';
    public const STATUS_DIBATALKAN = 'Dibatalkan';

    public const STATUSES = [
        self::STATUS_MENUNGGU,
        self::STATUS_DISETUJUI,
        self::STATUS_DIAMBIL,
        self::STATUS_DITOLAK,
        self::STATUS_DIBATALKAN,
    ];

    // Pengajuan yang masih berjalan atau sudah selesai (tidak boleh diajukan lagi)
    public const AKTIF = [self::STATUS_MENUNGGU, self::STATUS_DISETUJUI, self::STATUS_DIAMBIL];

    public const PENGAMBIL = ['Sendiri', 'Diwakilkan'];

    public const TRANSISI = [
        self::STATUS_MENUNGGU => [self::STATUS_DISETUJUI, self::STATUS_DITOLAK],
        self::STATUS_DISETUJUI => [self::STATUS_DIAMBIL, self::STATUS_DITOLAK],
    ];

    protected $table = 'pengambilan_ijazahs';

    protected $fillable = [
        'siswa_id', 'tanggal_pengambilan', 'pengambil', 'nama_wakil', 'hubungan_wakil',
        'keterangan', 'status', 'catatan', 'diambil_pada',
    ];

    protected $casts = [
        'tanggal_pengambilan' => 'date',
        'diambil_pada' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updated(function (PengambilanIjazah $m) {
            Notifikasi::karenaStatus($m, 'Ambil ijazah', route('siswa.ijazah.create'));
        });
    }

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class);
    }

    public function statusBerikutnya(): array
    {
        return self::TRANSISI[$this->status] ?? [];
    }
}
