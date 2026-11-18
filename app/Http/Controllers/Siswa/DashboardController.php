<?php

namespace App\Http\Controllers\Siswa;

use App\Http\Controllers\Controller;
use App\Models\Dokumen;
use App\Models\Legalisir;
use App\Models\PeminjamanRapor;
use App\Models\PengambilanIjazah;
use App\Models\WaliKelas;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $siswa = $request->user()->siswa;

        $dokumens = Dokumen::query()->where('siswa_id', $siswa?->id ?? 0);
        $legalisirs = Legalisir::query()->where('siswa_id', $siswa?->id ?? 0);

        $penempatan = $siswa?->riwayatAktif?->load(['kelas', 'tahunAjaran']);

        return view('siswa.dashboard', [
            'siswa' => $siswa,
            'penempatan' => $penempatan,
            'wali' => $penempatan
                ? WaliKelas::where('kelas_id', $penempatan->kelas_id)
                    ->where('tahun_ajaran_id', $penempatan->tahun_ajaran_id)->first()
                : null,
            'kelengkapan' => $penempatan
                ? \App\Support\DokumenWajib::hitung($siswa, $penempatan->kelas?->tingkat)
                : null,
            'pengingat' => PeminjamanRapor::with('dokumen')
                ->where('siswa_id', $siswa?->id ?? 0)
                ->where('status', PeminjamanRapor::STATUS_DIPINJAM)
                ->whereDate('tanggal_kembali', '<=', now()->addDays(2)->toDateString())
                ->orderBy('tanggal_kembali')->get(),
            'pengajuanBerjalan' => (clone $legalisirs)->whereIn('status', [Legalisir::STATUS_MENUNGGU, Legalisir::STATUS_DIPROSES])->count()
                + PeminjamanRapor::where('siswa_id', $siswa?->id ?? 0)->whereIn('status', PeminjamanRapor::AKTIF)->count()
                + PengambilanIjazah::where('siswa_id', $siswa?->id ?? 0)->whereIn('status', [PengambilanIjazah::STATUS_MENUNGGU, PengambilanIjazah::STATUS_DISETUJUI])->count(),
            'totalDokumen' => (clone $dokumens)->count(),
            'terverifikasi' => (clone $dokumens)->where('status', Dokumen::STATUS_TERVERIFIKASI)->count(),
            'menunggu' => (clone $dokumens)->where('status', Dokumen::STATUS_MENUNGGU)->count(),
            'legalisirBerjalan' => (clone $legalisirs)
                ->whereIn('status', [Legalisir::STATUS_MENUNGGU, Legalisir::STATUS_DIPROSES])->count(),
            'dokumenTerbaru' => (clone $dokumens)->with('kelas', 'tahunAjaran')->latest()->take(5)->get(),
            'legalisirTerbaru' => (clone $legalisirs)->with('dokumen')->latest()->take(5)->get(),
        ]);
    }
}
