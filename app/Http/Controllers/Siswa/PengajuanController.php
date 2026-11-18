<?php

namespace App\Http\Controllers\Siswa;

use App\Http\Controllers\Controller;
use App\Models\Legalisir;
use App\Models\PeminjamanRapor;
use App\Models\PengambilanIjazah;
use Illuminate\Http\Request;
use Illuminate\View\View;

// Satu halaman untuk melacak semua pengajuan siswa beserta tahapannya
class PengajuanController extends Controller
{
    private const LANGKAH = [
        'Legalisir' => ['Menunggu', 'Diproses', 'Selesai'],
        'Pinjam Rapor' => ['Menunggu', 'Disetujui', 'Dipinjam', 'Dikembalikan'],
        'Ambil Ijazah' => ['Menunggu', 'Disetujui', 'Sudah Diambil'],
    ];

    private const BERHENTI = ['Ditolak', 'Dibatalkan'];

    public function index(Request $request): View
    {
        $siswaId = $request->user()->siswa_id ?? 0;
        $semua = $request->input('tampil') === 'semua';

        $daftar = collect()
            ->merge(Legalisir::with('dokumen')->where('siswa_id', $siswaId)->get()->map(fn ($l) => $this->baris(
                'Legalisir', $l->dokumen?->nama_dokumen ?? '-', $l->jumlah . ' lembar', $l->status, $l->catatan, $l->created_at, $l->updated_at, route('siswa.legalisir.create')
            )))
            ->merge(PeminjamanRapor::with('dokumen')->where('siswa_id', $siswaId)->get()->map(fn ($p) => $this->baris(
                'Pinjam Rapor', $p->dokumen?->nama_dokumen ?? '-',
                'Kembali paling lambat ' . $p->tanggal_kembali->format('d M Y') . ($p->terlambat() ? ' (terlambat)' : ''),
                $p->status, $p->catatan, $p->created_at, $p->updated_at, route('siswa.rapor.create')
            )))
            ->merge(PengambilanIjazah::where('siswa_id', $siswaId)->get()->map(fn ($i) => $this->baris(
                'Ambil Ijazah', 'Ijazah asli', 'Diambil ' . strtolower($i->pengambil) . ' · ' . $i->tanggal_pengambilan->format('d M Y'),
                $i->status, $i->catatan, $i->created_at, $i->updated_at, route('siswa.ijazah.create')
            )))
            ->sortByDesc('dibuat')
            ->values();

        $berjalan = $daftar->where('selesai', false);

        return view('siswa.pengajuan', [
            'daftar' => $semua ? $daftar : $berjalan->values(),
            'semua' => $semua,
            'jumlahBerjalan' => $berjalan->count(),
            'jumlahSemua' => $daftar->count(),
        ]);
    }

    private function baris(string $jenis, string $judul, string $info, string $status, ?string $catatan, $dibuat, $diubah, string $url): array
    {
        $langkah = self::LANGKAH[$jenis];
        $berhenti = in_array($status, self::BERHENTI, true);
        $indeks = array_search($status, $langkah, true);

        return [
            'jenis' => $jenis, 'judul' => $judul, 'info' => $info, 'status' => $status, 'catatan' => $catatan,
            'dibuat' => $dibuat, 'diubah' => $diubah, 'url' => $url,
            'langkah' => $langkah, 'indeks' => $berhenti ? -1 : ($indeks === false ? 0 : $indeks),
            'berhenti' => $berhenti,
            'selesai' => $berhenti || $status === end($langkah),
        ];
    }
}
