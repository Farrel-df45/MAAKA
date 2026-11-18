<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PeminjamanRapor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PeminjamanRaporController extends Controller
{
    // Daftar peminjaman + pencarian + filter status (termasuk "Terlambat")
    public function index(Request $request): View
    {
        $q = trim((string) $request->input('q', ''));
        $status = $request->input('status');

        $peminjamans = PeminjamanRapor::query()
            ->with(['siswa', 'dokumen'])
            ->when($q !== '', function ($query) use ($q) {
                $query->whereHas('siswa', fn ($s) => $s->where('nama', 'like', "%{$q}%")->orWhere('nis', 'like', "%{$q}%"));
            })
            ->when($status === 'Terlambat', fn ($query) => $query
                ->where('status', PeminjamanRapor::STATUS_DIPINJAM)
                ->whereDate('tanggal_kembali', '<', now()->toDateString()))
            ->when(in_array($status, PeminjamanRapor::STATUSES, true), fn ($query) => $query->where('status', $status))
            ->latest()->latest('id')
            ->paginate(10)
            ->withQueryString();

        return view('admin.peminjaman-rapor.index', compact('peminjamans', 'q', 'status'));
    }

    public function show(PeminjamanRapor $peminjaman): View
    {
        $peminjaman->load(['siswa', 'dokumen']);

        return view('admin.peminjaman-rapor.show', compact('peminjaman'));
    }

    // Menunggu -> Disetujui/Ditolak, Disetujui -> Dipinjam/Ditolak, Dipinjam -> Dikembalikan
    public function updateStatus(Request $request, PeminjamanRapor $peminjaman): RedirectResponse
    {
        $boleh = $peminjaman->statusBerikutnya();

        if ($boleh === []) {
            return back()->with('error', "Peminjaman berstatus {$peminjaman->status} tidak dapat diubah lagi.");
        }

        $data = $request->validate([
            'status' => ['required', Rule::in($boleh)],
            'catatan' => [
                Rule::requiredIf($request->input('status') === PeminjamanRapor::STATUS_DITOLAK),
                'nullable', 'string', 'max:1000',
            ],
        ], [
            'status.required' => 'Pilih status berikutnya.',
            'status.in' => 'Perpindahan status tidak diizinkan.',
            'catatan.required' => 'Alasan penolakan wajib diisi.',
            'catatan.max' => 'Catatan maksimal 1000 karakter.',
        ]);

        $update = ['status' => $data['status'], 'catatan' => $data['catatan'] ?? $peminjaman->catatan];

        if ($data['status'] === PeminjamanRapor::STATUS_DIPINJAM) {
            $update['dipinjam_pada'] = now();
        }
        if ($data['status'] === PeminjamanRapor::STATUS_DIKEMBALIKAN) {
            $update['dikembalikan_pada'] = now();
        }

        $peminjaman->update($update);

        return redirect()->route('admin.peminjaman-rapor.show', $peminjaman)
            ->with('success', "Status peminjaman diubah menjadi {$data['status']}.");
    }
}
