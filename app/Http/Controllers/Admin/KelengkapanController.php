<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Kelas;
use App\Models\Siswa;
use App\Support\DokumenWajib;
use Illuminate\Http\Request;
use Illuminate\View\View;

class KelengkapanController extends Controller
{
    public function index(Request $request): View
    {
        $data = self::data($request);

        $perHalaman = 15;
        $halaman = max(1, $request->integer('page', 1));
        $paginator = new \Illuminate\Pagination\LengthAwarePaginator(
            $data['baris']->forPage($halaman, $perHalaman)->values(),
            $data['baris']->count(),
            $perHalaman,
            $halaman,
            ['path' => $request->url(), 'query' => $request->query()],
        );

        return view('admin.kelengkapan.index', [
            'baris' => $paginator,
            'ringkasan' => $data['ringkasan'],
            'q' => $data['q'],
            'kelasId' => $data['kelasId'],
            'filter' => $data['filter'],
            'daftarKelas' => Kelas::orderBy('tingkat')->orderBy('nama')->get(['id', 'nama']),
            'wajibPerTingkat' => DokumenWajib::PER_TINGKAT,
        ]);
    }

    // Dipakai juga oleh ekspor: hitung kelengkapan siswa yang punya kelas aktif
    public static function data(Request $request): array
    {
        $q = trim((string) $request->input('q', ''));
        $kelasId = $request->integer('kelas_id') ?: null;
        $filter = in_array($request->input('filter'), ['lengkap', 'belum'], true) ? $request->input('filter') : '';

        $siswas = Siswa::query()
            ->whereHas('riwayatAktif', fn ($r) => $kelasId ? $r->where('kelas_id', $kelasId) : $r)
            ->with(['riwayatAktif.kelas', 'dokumens'])
            ->when($q !== '', fn ($query) => $query->where(
                fn ($w) => $w->where('nama', 'like', "%{$q}%")->orWhere('nis', 'like', "%{$q}%")
            ))
            ->orderBy('nama')
            ->get();

        $semua = $siswas->map(function (Siswa $s) {
            $kelas = $s->riwayatAktif->kelas;

            return ['siswa' => $s, 'kelas' => $kelas, 'hasil' => DokumenWajib::hitung($s, $kelas?->tingkat)];
        });

        $ringkasan = [
            'total' => $semua->count(),
            'lengkap' => $semua->where('hasil.lengkap', true)->count(),
            'belum' => $semua->where('hasil.lengkap', false)->count(),
        ];

        $baris = $semua
            ->when($filter === 'lengkap', fn ($c) => $c->where('hasil.lengkap', true))
            ->when($filter === 'belum', fn ($c) => $c->where('hasil.lengkap', false))
            ->values();

        return compact('baris', 'ringkasan', 'q', 'kelasId', 'filter');
    }
}
