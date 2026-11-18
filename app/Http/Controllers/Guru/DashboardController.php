<?php

namespace App\Http\Controllers\Guru;

use App\Http\Controllers\Controller;
use App\Models\AktivitasGuru;
use App\Models\Dokumen;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $wali = $user->guru?->waliAktif?->load(['kelas', 'tahunAjaran']);

        // Dokumen dibatasi pada kelas yang dipegang guru di tahun ajaran aktif
        $milik = Dokumen::query()->when(
            $wali,
            fn ($q) => $q->where('kelas_id', $wali->kelas_id)->where('tahun_ajaran_id', $wali->tahun_ajaran_id),
            fn ($q) => $q->whereRaw('1 = 0'),
        );

        return view('guru.dashboard', [
            'guru' => $user->guru,
            'wali' => $wali,
            'jumlahSiswa' => $wali
                ? \App\Models\RiwayatKelas::where('kelas_id', $wali->kelas_id)
                    ->where('tahun_ajaran_id', $wali->tahun_ajaran_id)->count()
                : 0,
            'totalDokumen' => (clone $milik)->count(),
            'menunggu' => (clone $milik)->where('status', Dokumen::STATUS_MENUNGGU)->count(),
            'terverifikasi' => (clone $milik)->where('status', Dokumen::STATUS_TERVERIFIKASI)->count(),
            'ditolak' => (clone $milik)->where('status', Dokumen::STATUS_DITOLAK)->count(),
            'aktivitasTerbaru' => AktivitasGuru::where('guru_id', $user->guru_id)
                ->latest()->latest('id')->take(5)->get(),
        ]);
    }
}
