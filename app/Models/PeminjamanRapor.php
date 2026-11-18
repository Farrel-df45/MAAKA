<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PeminjamanRapor extends Model
{
    public const STATUS_MENUNGGU = 'Menunggu';
    public const STATUS_DISETUJUI = 'Disetujui';
    public const STATUS_DIPINJAM = 'Dipinjam';
    public const STATUS_DIKEMBALIKAN = 'Dikembalikan';
    public const STATUS_DITOLAK = 'Ditolak';
    public const STATUS_DIBATALKAN = 'Dibatalkan';

    public const STATUSES = [
        self::STATUS_MENUNGGU,
        self::STATUS_DISETUJUI,
        self::STATUS_DIPINJAM,
        self::STATUS_DIKEMBALIKAN,
        self::STATUS_DITOLAK,
        self::STATUS_DIBATALKAN,
    ];

    // Status yang masih berjalan (dokumen tidak boleh dipinjam ganda)
    public const AKTIF = [self::STATUS_MENUNGGU, self::STATUS_DISETUJUI, self::STATUS_DIPINJAM];

    // Maksimal lama peminjaman (hari)
    public const MAKS_HARI = 14;

    // Perpindahan status yang boleh dilakukan admin
    public const TRANSISI = [
        self::STATUS_MENUNGGU => [self::STATUS_DISETUJUI, self::STATUS_DITOLAK],
        self::STATUS_DISETUJUI => [self::STATUS_DIPINJAM, self::STATUS_DITOLAK],
        self::STATUS_DIPINJAM => [self::STATUS_DIKEMBALIKAN],
    ];

    protected $fillable = [
        'siswa_id', 'dokumen_id', 'tanggal_pinjam', 'tanggal_kembali', 'alasan',
        'status', 'catatan', 'dipinjam_pada', 'dikembalikan_pada',
    ];

    protected $casts = [
        'tanggal_pinjam' => 'date',
        'tanggal_kembali' => 'date',
        'dipinjam_pada' => 'datetime',
        'dikembalikan_pada' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updated(function (PeminjamanRapor $m) {
            Notifikasi::karenaStatus($m, 'Pinjam rapor', route('siswa.rapor.create'));
        });
    }

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class);
    }

    public function dokumen(): BelongsTo
    {
        return $this->belongsTo(Dokumen::class);
    }

    // Terlambat: masih dipinjam padahal rencana kembali sudah lewat
    public function terlambat(): bool
    {
        return $this->status === self::STATUS_DIPINJAM
            && $this->tanggal_kembali->lt(now()->startOfDay());
    }

    public function statusBerikutnya(): array
    {
        return self::TRANSISI[$this->status] ?? [];
    }
}
