<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PengambilanIjazah;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PengambilanIjazahController extends Controller
{
    public function index(Request $request): View
    {
        $q = trim((string) $request->input('q', ''));
        $status = $request->input('status');

        $pengambilans = PengambilanIjazah::query()
            ->with('siswa')
            ->when($q !== '', function ($query) use ($q) {
                $query->whereHas('siswa', fn ($s) => $s->where('nama', 'like', "%{$q}%")->orWhere('nis', 'like', "%{$q}%"));
            })
            ->when(in_array($status, PengambilanIjazah::STATUSES, true), fn ($query) => $query->where('status', $status))
            ->latest()->latest('id')
            ->paginate(10)
            ->withQueryString();

        return view('admin.pengambilan-ijazah.index', compact('pengambilans', 'q', 'status'));
    }

    public function show(PengambilanIjazah $pengambilan): View
    {
        $pengambilan->load('siswa');

        return view('admin.pengambilan-ijazah.show', compact('pengambilan'));
    }

    // Menunggu -> Disetujui/Ditolak, Disetujui -> Sudah Diambil/Ditolak
    public function updateStatus(Request $request, PengambilanIjazah $pengambilan): RedirectResponse
    {
        $boleh = $pengambilan->statusBerikutnya();

        if ($boleh === []) {
            return back()->with('error', "Pengajuan berstatus {$pengambilan->status} tidak dapat diubah lagi.");
        }

        $data = $request->validate([
            'status' => ['required', Rule::in($boleh)],
            'catatan' => [
                Rule::requiredIf($request->input('status') === PengambilanIjazah::STATUS_DITOLAK),
                'nullable', 'string', 'max:1000',
            ],
        ], [
            'status.required' => 'Pilih status berikutnya.',
            'status.in' => 'Perpindahan status tidak diizinkan.',
            'catatan.required' => 'Alasan penolakan wajib diisi.',
            'catatan.max' => 'Catatan maksimal 1000 karakter.',
        ]);

        $update = ['status' => $data['status'], 'catatan' => $data['catatan'] ?? $pengambilan->catatan];

        if ($data['status'] === PengambilanIjazah::STATUS_DIAMBIL) {
            $update['diambil_pada'] = now();
        }

        $pengambilan->update($update);

        return redirect()->route('admin.pengambilan-ijazah.show', $pengambilan)
            ->with('success', "Status pengajuan diubah menjadi {$data['status']}.");
    }
}
