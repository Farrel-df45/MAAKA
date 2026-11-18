<?php

namespace App\Support;

use App\Models\Dokumen;
use App\Models\Siswa;

// Kategori dokumen yang wajib terverifikasi untuk tiap tingkat. Ubah daftar ini sesuai kebijakan sekolah.
class DokumenWajib
{
    public const PER_TINGKAT = [
        'X' => ['Rapor'],
        'XI' => ['Rapor'],
        'XII' => ['Rapor', 'Transkrip Nilai'],
    ];

    public const DEFAULT = ['Rapor'];

    public static function untukTingkat(?string $tingkat): array
    {
        return self::PER_TINGKAT[strtoupper((string) $tingkat)] ?? self::DEFAULT;
    }

    // Hasil: wajib, terpenuhi (terverifikasi), menunggu, ditolak, kurang
    public static function hitung(Siswa $siswa, ?string $tingkat, $dokumens = null): array
    {
        $wajib = self::untukTingkat($tingkat);
        $dokumens ??= $siswa->dokumens;

        $terpenuhi = $menunggu = $ditolak = $kurang = [];

        foreach ($wajib as $kategori) {
            $ada = $dokumens->where('kategori', $kategori);

            if ($ada->where('status', Dokumen::STATUS_TERVERIFIKASI)->isNotEmpty()) {
                $terpenuhi[] = $kategori;
            } elseif ($ada->where('status', Dokumen::STATUS_MENUNGGU)->isNotEmpty()) {
                $menunggu[] = $kategori;
            } elseif ($ada->where('status', Dokumen::STATUS_DITOLAK)->isNotEmpty()) {
                $ditolak[] = $kategori;
            } else {
                $kurang[] = $kategori;
            }
        }

        return [
            'wajib' => $wajib,
            'terpenuhi' => $terpenuhi,
            'menunggu' => $menunggu,
            'ditolak' => $ditolak,
            'kurang' => $kurang,
            'lengkap' => count($terpenuhi) === count($wajib),
            'persen' => count($wajib) ? (int) round(count($terpenuhi) / count($wajib) * 100) : 100,
        ];
    }
}
