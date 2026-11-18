<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Legalisir extends Model
{
    public const STATUS_MENUNGGU = 'Menunggu';
    public const STATUS_DIPROSES = 'Diproses';
    public const STATUS_SELESAI = 'Selesai';
    public const STATUS_DITOLAK = 'Ditolak';
    public const STATUS_DIBATALKAN = 'Dibatalkan'; // dibatalkan oleh siswa

    public const STATUSES = [
        self::STATUS_MENUNGGU,
        self::STATUS_DIPROSES,
        self::STATUS_SELESAI,
        self::STATUS_DITOLAK,
        self::STATUS_DIBATALKAN,
    ];

    protected $fillable = [
        'siswa_id',
        'dokumen_id',
        'jumlah',
        'keperluan',
        'status',
        'catatan',
    ];

    protected static function booted(): void
    {
        static::updated(function (Legalisir $l) {
            Notifikasi::karenaStatus($l, 'Legalisir ' . ($l->dokumen?->nama_dokumen ?? ''), route('siswa.legalisir.create'));
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
}