<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notifikasi extends Model
{
    protected $fillable = ['user_id', 'judul', 'pesan', 'url', 'dibaca_pada'];

    protected $casts = ['dibaca_pada' => 'datetime'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Kirim notifikasi ke akun siswa (bila siswa punya akun login)
    public static function untukSiswa(?int $siswaId, string $judul, ?string $pesan = null, ?string $url = null): void
    {
        if (!$siswaId) {
            return;
        }

        $userId = User::where('siswa_id', $siswaId)->value('id');

        if ($userId) {
            self::create(['user_id' => $userId, 'judul' => $judul, 'pesan' => $pesan, 'url' => $url]);
        }
    }

    // Dipakai model: kirim notifikasi hanya saat kolom status benar-benar berubah
    public static function karenaStatus(Model $model, string $judul, string $url): void
    {
        if (!$model->wasChanged('status')) {
            return;
        }

        try {
            self::untukSiswa(
                $model->siswa_id,
                $judul . ': ' . $model->status,
                $model->catatan ? 'Catatan: ' . $model->catatan : null,
                $url,
            );
        } catch (\Throwable $e) {
            report($e); // notifikasi tidak boleh menggagalkan perubahan status
        }
    }
}
