<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Dokumen;
use App\Models\PeminjamanRapor;
use App\Models\Siswa;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

// Ekspor CSV (pemisah titik koma + BOM UTF-8 agar langsung terbaca benar di Excel)
class EksporController extends Controller
{
    public const JENIS = ['siswa', 'dokumen', 'peminjaman-rapor', 'kelengkapan'];

    public function __invoke(Request $request, string $jenis): StreamedResponse
    {
        abort_unless(in_array($jenis, self::JENIS, true), 404);

        [$header, $baris] = match ($jenis) {
            'siswa' => $this->siswa(),
            'dokumen' => $this->dokumen(),
            'peminjaman-rapor' => $this->peminjaman(),
            'kelengkapan' => $this->kelengkapan($request),
        };

        $nama = "maaka-{$jenis}-" . now()->format('Ymd-His') . '.csv';

        return response()->streamDownload(function () use ($header, $baris) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $header, ';');
            foreach ($baris as $b) {
                fputcsv($out, array_map([self::class, 'aman'], $b), ';');
            }
            fclose($out);
        }, $nama, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    // Cegah formula injection saat dibuka di Excel
    public static function aman(mixed $v): mixed
    {
        return is_string($v) && $v !== '' && str_contains('=+-@', $v[0]) ? "'" . $v : $v;
    }

    private function siswa(): array
    {
        $rows = Siswa::with('riwayatAktif.kelas')->withCount('dokumens')->orderBy('nama')->get()
            ->map(fn ($s) => [$s->nis, $s->nama, $s->riwayatAktif?->kelas?->nama ?? ($s->kelas ?: '-'), $s->jurusan, $s->alamat, $s->dokumens_count]);

        return [['NIS', 'Nama', 'Kelas', 'Jurusan', 'Alamat', 'Jumlah Dokumen'], $rows];
    }

    private function dokumen(): array
    {
        $rows = Dokumen::with(['siswa', 'kelas', 'tahunAjaran'])->latest()->get()
            ->map(fn ($d) => [
                $d->nama_dokumen, $d->kategori, $d->status, $d->siswa?->nis, $d->siswa?->nama,
                $d->kelas?->nama, $d->tahunAjaran?->nama, $d->catatan, $d->created_at?->format('Y-m-d H:i'),
            ]);

        return [['Dokumen', 'Kategori', 'Status', 'NIS', 'Siswa', 'Kelas', 'Tahun Ajaran', 'Catatan', 'Dibuat'], $rows];
    }

    private function peminjaman(): array
    {
        $rows = PeminjamanRapor::with(['siswa', 'dokumen'])->latest()->get()
            ->map(fn ($p) => [
                $p->siswa?->nis, $p->siswa?->nama, $p->dokumen?->nama_dokumen,
                $p->tanggal_pinjam?->format('Y-m-d'), $p->tanggal_kembali?->format('Y-m-d'),
                $p->terlambat() ? 'Terlambat' : $p->status, $p->dikembalikan_pada?->format('Y-m-d'),
            ]);

        return [['NIS', 'Siswa', 'Dokumen', 'Tgl Pinjam', 'Batas Kembali', 'Status', 'Dikembalikan'], $rows];
    }

    private function kelengkapan(Request $request): array
    {
        $rows = KelengkapanController::data($request)['baris']->map(fn ($r) => [
            $r['siswa']->nis, $r['siswa']->nama, $r['kelas']?->nama,
            implode(', ', $r['hasil']['wajib']), implode(', ', $r['hasil']['terpenuhi']),
            implode(', ', $r['hasil']['menunggu']), implode(', ', $r['hasil']['ditolak']),
            implode(', ', $r['hasil']['kurang']), $r['hasil']['persen'] . '%',
        ]);

        return [['NIS', 'Nama', 'Kelas', 'Wajib', 'Terverifikasi', 'Menunggu', 'Ditolak', 'Belum Ada', 'Kelengkapan'], $rows];
    }
}
