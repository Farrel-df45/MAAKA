<?php

namespace App\Http\Controllers;

use App\Models\Dokumen;
use App\Models\Legalisir;
use App\Models\Siswa;

class DashboardController extends Controller
{
    public function index()
    {
        $totalSiswa = Siswa::count();
        $totalDokumen = Dokumen::count();
        $menungguVerifikasi = Dokumen::where('status', 'Menunggu Verifikasi')->count();
        $menungguLegalisir = Legalisir::where('status', 'Menunggu')->count();

        $dokumenTerbaru = Dokumen::with('siswa')
            ->latest()
            ->take(5)
            ->get();

        return view('dashboard', compact(
            'totalSiswa',
            'totalDokumen',
            'menungguVerifikasi',
            'menungguLegalisir',
            'dokumenTerbaru'
        ));
    }
}