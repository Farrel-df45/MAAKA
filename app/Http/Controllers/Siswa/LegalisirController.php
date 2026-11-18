<?php

namespace App\Http\Controllers\Siswa;

use App\Http\Controllers\Controller;
use App\Models\Dokumen;
use App\Models\Legalisir;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class LegalisirController extends Controller
{
    // Form pengajuan + riwayat pengajuan milik siswa
    public function create(Request $request): View
    {
        $siswa = $request->user()->siswa;

        return view('siswa.legalisir.create', [
            'siswa' => $siswa,
            'dokumens' => $this->dokumenBisaDilegalisir($siswa?->id),
            'legalisirs' => Legalisir::with('dokumen')
                ->where('siswa_id', $siswa?->id ?? 0)
                ->latest()->latest('id')->paginate(8)->withQueryString(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $siswa = $request->user()->siswa;
        abort_unless($siswa, 403, 'Akun Anda belum terhubung ke data siswa. Hubungi Admin.');

        $dokumens = $this->dokumenBisaDilegalisir($siswa->id);

        $data = $request->validate([
            'dokumen_id' => ['required', Rule::in($dokumens->pluck('id')->all())],
            'jumlah' => ['required', 'integer', 'min:1', 'max:10'],
            'keperluan' => ['required', 'string', 'max:500'],
        ], [
            'dokumen_id.required' => 'Pilih dokumen yang akan dilegalisir.',
            'dokumen_id.in' => 'Dokumen tidak dapat diajukan. Pastikan dokumen sudah terverifikasi dan belum ada pengajuan yang berjalan.',
            'jumlah.required' => 'Jumlah salinan wajib diisi.',
            'jumlah.min' => 'Jumlah minimal 1 salinan.',
            'jumlah.max' => 'Jumlah maksimal 10 salinan.',
            'keperluan.required' => 'Keperluan wajib diisi.',
            'keperluan.max' => 'Keperluan maksimal 500 karakter.',
        ]);

        Legalisir::create([
            'siswa_id' => $siswa->id,
            'dokumen_id' => $data['dokumen_id'],
            'jumlah' => $data['jumlah'],
            'keperluan' => trim($data['keperluan']),
            'status' => Legalisir::STATUS_MENUNGGU,
        ]);

        return redirect()->route('siswa.legalisir.create')
            ->with('success', 'Pengajuan legalisir terkirim. Pantau statusnya di bawah.');
    }

    // Siswa hanya dapat membatalkan pengajuannya sendiri selama masih Menunggu
    public function batal(Request $request, Legalisir $legalisir): RedirectResponse
    {
        abort_unless($request->user()->siswa_id !== null && $legalisir->siswa_id === $request->user()->siswa_id, 403);

        if ($legalisir->status !== Legalisir::STATUS_MENUNGGU) {
            return back()->with('error', 'Pengajuan yang sudah diproses tidak dapat dibatalkan.');
        }

        $legalisir->update(['status' => Legalisir::STATUS_DIBATALKAN]);

        return back()->with('success', 'Pengajuan legalisir dibatalkan.');
    }

    // Hanya dokumen milik siswa yang sudah terverifikasi dan belum punya pengajuan berjalan
    private function dokumenBisaDilegalisir(?int $siswaId)
    {
        if (!$siswaId) {
            return collect();
        }

        return Dokumen::query()
            ->where('siswa_id', $siswaId)
            ->where('status', Dokumen::STATUS_TERVERIFIKASI)
            ->whereDoesntHave('legalisirs', fn ($q) => $q->whereIn('status', [Legalisir::STATUS_MENUNGGU, Legalisir::STATUS_DIPROSES]))
            ->orderBy('nama_dokumen')
            ->get(['id', 'nama_dokumen', 'kategori']);
    }
}
