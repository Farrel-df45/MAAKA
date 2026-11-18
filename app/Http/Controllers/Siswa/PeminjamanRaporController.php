<?php

namespace App\Http\Controllers\Siswa;

use App\Http\Controllers\Controller;
use App\Models\Dokumen;
use App\Models\PeminjamanRapor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PeminjamanRaporController extends Controller
{
    public function create(Request $request): View
    {
        $siswa = $request->user()->siswa;

        return view('siswa.rapor.create', [
            'siswa' => $siswa,
            'dokumens' => $this->raporBisaDipinjam($siswa?->id),
            'peminjamans' => PeminjamanRapor::with('dokumen')
                ->where('siswa_id', $siswa?->id ?? 0)
                ->latest()->latest('id')->paginate(8)->withQueryString(),
            'maksHari' => PeminjamanRapor::MAKS_HARI,
            'adaTerlambat' => $siswa ? $this->punyaTerlambat($siswa->id) : false,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $siswa = $request->user()->siswa;
        abort_unless($siswa, 403, 'Akun Anda belum terhubung ke data siswa. Hubungi Admin.');

        if ($this->punyaTerlambat($siswa->id)) {
            return back()->withInput()->with('error', 'Anda masih punya rapor yang terlambat dikembalikan. Kembalikan terlebih dahulu sebelum meminjam lagi.');
        }

        $data = $request->validate([
            'dokumen_id' => ['required', Rule::in($this->raporBisaDipinjam($siswa->id)->pluck('id')->all())],
            'tanggal_pinjam' => ['required', 'date', 'after_or_equal:today'],
            'tanggal_kembali' => ['required', 'date', 'after:tanggal_pinjam'],
            'alasan' => ['required', 'string', 'max:500'],
        ], [
            'dokumen_id.required' => 'Pilih rapor yang akan dipinjam.',
            'dokumen_id.in' => 'Rapor tidak dapat dipinjam. Pastikan sudah terverifikasi dan belum ada peminjaman yang berjalan.',
            'tanggal_pinjam.required' => 'Tanggal pinjam wajib diisi.',
            'tanggal_pinjam.after_or_equal' => 'Tanggal pinjam tidak boleh sebelum hari ini.',
            'tanggal_kembali.required' => 'Tanggal pengembalian wajib diisi.',
            'tanggal_kembali.after' => 'Tanggal pengembalian harus setelah tanggal pinjam.',
            'alasan.required' => 'Alasan peminjaman wajib diisi.',
            'alasan.max' => 'Alasan maksimal 500 karakter.',
        ]);

        $lama = (int) \Illuminate\Support\Carbon::parse($data['tanggal_pinjam'])
            ->diffInDays(\Illuminate\Support\Carbon::parse($data['tanggal_kembali']));

        if ($lama > PeminjamanRapor::MAKS_HARI) {
            throw ValidationException::withMessages([
                'tanggal_kembali' => 'Lama peminjaman maksimal ' . PeminjamanRapor::MAKS_HARI . ' hari.',
            ]);
        }

        PeminjamanRapor::create($data + [
            'siswa_id' => $siswa->id,
            'status' => PeminjamanRapor::STATUS_MENUNGGU,
        ]);

        return redirect()->route('siswa.rapor.create')
            ->with('success', 'Pengajuan peminjaman rapor terkirim. Tunggu persetujuan Admin.');
    }

    // Siswa hanya dapat membatalkan pengajuannya sendiri selama masih Menunggu
    public function batal(Request $request, PeminjamanRapor $peminjaman): RedirectResponse
    {
        abort_unless($request->user()->siswa_id !== null && $peminjaman->siswa_id === $request->user()->siswa_id, 403);

        if ($peminjaman->status !== PeminjamanRapor::STATUS_MENUNGGU) {
            return back()->with('error', 'Pengajuan yang sudah diproses tidak dapat dibatalkan.');
        }

        $peminjaman->update(['status' => PeminjamanRapor::STATUS_DIBATALKAN]);

        return back()->with('success', 'Pengajuan peminjaman rapor dibatalkan.');
    }

    // Rapor milik siswa, sudah terverifikasi, dan belum sedang dipinjam/diajukan
    private function raporBisaDipinjam(?int $siswaId)
    {
        if (!$siswaId) {
            return collect();
        }

        return Dokumen::query()
            ->where('siswa_id', $siswaId)
            ->where('kategori', 'Rapor')
            ->where('status', Dokumen::STATUS_TERVERIFIKASI)
            ->whereNotIn('id', PeminjamanRapor::whereIn('status', PeminjamanRapor::AKTIF)->select('dokumen_id'))
            ->orderBy('nama_dokumen')
            ->get(['id', 'nama_dokumen']);
    }

    private function punyaTerlambat(int $siswaId): bool
    {
        return PeminjamanRapor::where('siswa_id', $siswaId)
            ->where('status', PeminjamanRapor::STATUS_DIPINJAM)
            ->whereDate('tanggal_kembali', '<', now()->toDateString())
            ->exists();
    }
}
