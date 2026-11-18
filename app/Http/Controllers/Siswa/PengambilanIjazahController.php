<?php

namespace App\Http\Controllers\Siswa;

use App\Http\Controllers\Controller;
use App\Models\PengambilanIjazah;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PengambilanIjazahController extends Controller
{
    public function create(Request $request): View
    {
        $siswa = $request->user()->siswa;

        return view('siswa.ijazah.create', [
            'siswa' => $siswa,
            'lulus' => $siswa?->isLulus() ?? false,
            'sudahAda' => $siswa
                ? PengambilanIjazah::where('siswa_id', $siswa->id)->whereIn('status', PengambilanIjazah::AKTIF)->exists()
                : false,
            'pengambilans' => PengambilanIjazah::where('siswa_id', $siswa?->id ?? 0)
                ->latest()->latest('id')->paginate(8)->withQueryString(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $siswa = $request->user()->siswa;
        abort_unless($siswa, 403, 'Akun Anda belum terhubung ke data siswa. Hubungi Admin.');

        if (!$siswa->isLulus()) {
            return back()->withInput()->with('error', 'Pengambilan ijazah asli hanya dapat diajukan oleh siswa yang sudah lulus.');
        }

        if (PengambilanIjazah::where('siswa_id', $siswa->id)->whereIn('status', PengambilanIjazah::AKTIF)->exists()) {
            return back()->withInput()->with('error', 'Anda sudah punya pengajuan pengambilan ijazah yang berjalan atau sudah selesai.');
        }

        $diwakilkan = $request->input('pengambil') === 'Diwakilkan';

        $data = $request->validate([
            'tanggal_pengambilan' => ['required', 'date', 'after_or_equal:today'],
            'pengambil' => ['required', Rule::in(PengambilanIjazah::PENGAMBIL)],
            'nama_wakil' => [Rule::requiredIf($diwakilkan), 'nullable', 'string', 'max:255'],
            'hubungan_wakil' => [Rule::requiredIf($diwakilkan), 'nullable', 'string', 'max:100'],
            'keterangan' => ['nullable', 'string', 'max:500'],
        ], [
            'tanggal_pengambilan.required' => 'Tanggal pengambilan wajib diisi.',
            'tanggal_pengambilan.after_or_equal' => 'Tanggal pengambilan tidak boleh sebelum hari ini.',
            'pengambil.required' => 'Pilih siapa yang mengambil.',
            'nama_wakil.required' => 'Nama perwakilan wajib diisi.',
            'hubungan_wakil.required' => 'Hubungan dengan perwakilan wajib diisi.',
        ]);

        if (!$diwakilkan) {
            $data['nama_wakil'] = null;
            $data['hubungan_wakil'] = null;
        }

        PengambilanIjazah::create($data + [
            'siswa_id' => $siswa->id,
            'status' => PengambilanIjazah::STATUS_MENUNGGU,
        ]);

        return redirect()->route('siswa.ijazah.create')
            ->with('success', 'Pengajuan pengambilan ijazah terkirim. Tunggu persetujuan Admin.');
    }

    public function batal(Request $request, PengambilanIjazah $pengambilan): RedirectResponse
    {
        abort_unless($request->user()->siswa_id !== null && $pengambilan->siswa_id === $request->user()->siswa_id, 403);

        if ($pengambilan->status !== PengambilanIjazah::STATUS_MENUNGGU) {
            return back()->with('error', 'Pengajuan yang sudah diproses tidak dapat dibatalkan.');
        }

        $pengambilan->update(['status' => PengambilanIjazah::STATUS_DIBATALKAN]);

        return back()->with('success', 'Pengajuan pengambilan ijazah dibatalkan.');
    }
}
