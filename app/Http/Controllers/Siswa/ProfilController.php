<?php

namespace App\Http\Controllers\Siswa;

use App\Http\Controllers\Controller;
use App\Models\Dokumen;
use App\Models\WaliKelas;
use Illuminate\Http\Request;
use Illuminate\View\View;

// Profil siswa (hanya baca): data diri dan riwayat kelas per tahun ajaran
class ProfilController extends Controller
{
    public function index(Request $request): View
    {
        $siswa = $request->user()->siswa;
        $periode = collect();

        if ($siswa) {
            $periode = $siswa->riwayatKelas()
                ->with(['kelas', 'tahunAjaran'])
                ->orderByDesc('tahun_ajaran_id')
                ->get()
                ->map(fn ($r) => [
                    'riwayat' => $r,
                    'wali' => WaliKelas::where('kelas_id', $r->kelas_id)
                        ->where('tahun_ajaran_id', $r->tahun_ajaran_id)->first(),
                    'dokumen' => Dokumen::where('siswa_id', $siswa->id)
                        ->where('tahun_ajaran_id', $r->tahun_ajaran_id)->count(),
                ]);
        }

        return view('siswa.profil', [
            'siswa' => $siswa,
            'lulus' => $siswa?->isLulus() ?? false,
            'periode' => $periode,
        ]);
    }
}
