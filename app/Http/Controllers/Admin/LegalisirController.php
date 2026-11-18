<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Legalisir;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class LegalisirController extends Controller
{
    // Daftar permohonan + pencarian + filter status
    public function index(Request $request): View
    {
        $q = trim((string) $request->input('q', ''));
        $status = $request->input('status');

        $legalisirs = Legalisir::query()
            ->with(['siswa', 'dokumen'])
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($w) use ($q) {
                    $w->whereHas('siswa', function ($s) use ($q) {
                        $s->where('nama', 'like', "%{$q}%")
                            ->orWhere('nis', 'like', "%{$q}%");
                    })->orWhereHas('dokumen', function ($d) use ($q) {
                        $d->where('nama_dokumen', 'like', "%{$q}%");
                    });
                });
            })
            ->when(in_array($status, Legalisir::STATUSES, true), fn ($query) => $query->where('status', $status))
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('admin.legalisir.index', compact('legalisirs', 'q', 'status'));
    }

    public function show(Legalisir $legalisir): View
    {
        $legalisir->load(['siswa', 'dokumen']);

        return view('admin.legalisir.show', compact('legalisir'));
    }

    // Proses permohonan: Diproses / Selesai / Ditolak (catatan wajib saat ditolak)
    public function updateStatus(Request $request, Legalisir $legalisir): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(Legalisir::STATUSES)],
            'catatan' => [
                Rule::requiredIf($request->input('status') === Legalisir::STATUS_DITOLAK),
                'nullable', 'string', 'max:1000',
            ],
        ], [
            'status.required' => 'Pilih status permohonan.',
            'catatan.required' => 'Alasan penolakan wajib diisi.',
            'catatan.max' => 'Catatan maksimal 1000 karakter.',
        ]);

        $legalisir->update([
            'status' => $validated['status'],
            'catatan' => $validated['catatan'] ?? null,
        ]);

        return redirect()
            ->route('admin.legalisir.show', $legalisir)
            ->with('success', "Status permohonan diubah menjadi {$validated['status']}.");
    }

    public function destroy(Legalisir $legalisir): RedirectResponse
    {
        $legalisir->delete();

        return redirect()
            ->route('admin.legalisir.index')
            ->with('success', 'Permohonan legalisir berhasil dihapus.');
    }
}
