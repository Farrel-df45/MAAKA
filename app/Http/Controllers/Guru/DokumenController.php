<?php

namespace App\Http\Controllers\Guru;

use App\Http\Controllers\Controller;
use App\Models\AktivitasGuru;
use App\Models\Dokumen;
use App\Models\Guru;
use App\Models\RiwayatKelas;
use App\Models\Siswa;
use App\Models\WaliKelas;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

// Guru hanya mengakses siswa & dokumen di kelas yang ia walikan pada tahun ajaran aktif,
// dan hanya dapat mengubah/menghapus dokumen yang ia unggah sendiri.
// Setiap unggah / ubah / hapus dicatat ke riwayat yang dapat dilihat Admin.
class DokumenController extends Controller
{
    private const DISK = 'local';

    private const LABEL = [
        'siswa_id' => 'siswa',
        'nama_dokumen' => 'nama dokumen',
        'kategori' => 'kategori',
        'file_path' => 'berkas',
    ];

    public function index(Request $request): View
    {
        $q = trim((string) $request->input('q', ''));
        $status = $request->input('status');

        $wali = $request->user()->guru?->waliAktif;

        $dokumens = Dokumen::query()
            ->with(['siswa', 'pengunggah:id,name'])
            ->tap(fn ($query) => $this->batasiKelas($query, $wali))
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($w) use ($q) {
                    $w->where('nama_dokumen', 'like', "%{$q}%")
                        ->orWhereHas('siswa', fn ($s) => $s->where('nama', 'like', "%{$q}%")->orWhere('nis', 'like', "%{$q}%"));
                });
            })
            ->when(in_array($status, Dokumen::STATUSES, true), fn ($query) => $query->where('status', $status))
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('guru.dokumen.index', [
            'dokumens' => $dokumens,
            'q' => $q,
            'status' => $status,
            'wali' => $wali?->load(['kelas', 'tahunAjaran']),
        ]);
    }

    public function create(Request $request): View
    {
        return view('guru.dokumen.form', ['dokumen' => new Dokumen(), 'siswas' => $this->siswaOptions($request)]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->guruLogin($request);
        $this->waliLogin($request);
        $user = $request->user();
        $data = $this->validated($request);

        $dokumen = Dokumen::create([
            'siswa_id' => $data['siswa_id'],
            'diunggah_oleh' => $user->id,
            'nama_dokumen' => $data['nama_dokumen'],
            'kategori' => $data['kategori'],
            'file_path' => $request->file('berkas')->store('dokumen', self::DISK),
            // Dokumen dari guru selalu menunggu verifikasi Admin
            'status' => Dokumen::STATUS_MENUNGGU,
        ]);

        $this->catat($request, AktivitasGuru::UNGGAH, $dokumen);

        return redirect()
            ->route('guru.dokumen.index')
            ->with('success', "Dokumen {$dokumen->nama_dokumen} berhasil diunggah dan menunggu verifikasi Admin.");
    }

    public function edit(Request $request, Dokumen $dokumen): View
    {
        $this->pastikanMilik($request, $dokumen);

        return view('guru.dokumen.form', ['dokumen' => $dokumen, 'siswas' => $this->siswaOptions($request)]);
    }

    public function update(Request $request, Dokumen $dokumen): RedirectResponse
    {
        $this->pastikanMilik($request, $dokumen);
        $this->guruLogin($request);
        $data = $this->validated($request, fileRequired: false);

        $oldPath = $dokumen->file_path;
        $dokumen->fill([
            'siswa_id' => $data['siswa_id'],
            'nama_dokumen' => $data['nama_dokumen'],
            'kategori' => $data['kategori'],
        ]);

        if ($request->hasFile('berkas')) {
            $dokumen->file_path = $request->file('berkas')->store('dokumen', self::DISK);
        }

        $berubah = collect(array_keys($dokumen->getDirty()))
            ->map(fn ($kolom) => self::LABEL[$kolom] ?? null)
            ->filter()->values();

        if ($berubah->isEmpty()) {
            return redirect()->route('guru.dokumen.index')->with('success', 'Tidak ada perubahan yang disimpan.');
        }

        // Dokumen yang diubah harus diverifikasi ulang oleh Admin
        $dokumen->status = Dokumen::STATUS_MENUNGGU;
        $dokumen->save();

        if ($request->hasFile('berkas')) {
            Storage::disk(self::DISK)->delete($oldPath);
        }

        $this->catat($request, AktivitasGuru::UBAH, $dokumen, 'Mengubah: ' . $berubah->implode(', '));

        return redirect()
            ->route('guru.dokumen.index')
            ->with('success', "Dokumen {$dokumen->nama_dokumen} diperbarui dan menunggu verifikasi ulang Admin.");
    }

    public function destroy(Request $request, Dokumen $dokumen): RedirectResponse
    {
        $this->pastikanMilik($request, $dokumen);
        $this->guruLogin($request);

        // Catat sebelum dihapus agar nama dokumen & siswa masih tersedia
        $this->catat($request, AktivitasGuru::HAPUS, $dokumen);

        $nama = $dokumen->nama_dokumen;
        $path = $dokumen->file_path;
        $dokumen->delete();
        Storage::disk(self::DISK)->delete($path);

        return redirect()
            ->route('guru.dokumen.index')
            ->with('success', "Dokumen {$nama} berhasil dihapus.");
    }

    public function berkas(Request $request, Dokumen $dokumen): Response|RedirectResponse
    {
        $this->pastikanDiKelas($request, $dokumen);
        /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
        $disk = Storage::disk(self::DISK);

        if (!$disk->exists($dokumen->file_path)) {
            return back()->with('error', "Berkas {$dokumen->nama_dokumen} tidak ditemukan di penyimpanan.");
        }

        return $disk->response($dokumen->file_path);
    }

    // Ubah / hapus: dokumen harus ada di kelas aktif guru DAN diunggah oleh guru ini
    private function pastikanMilik(Request $request, Dokumen $dokumen): void
    {
        $this->pastikanDiKelas($request, $dokumen);
        abort_unless($dokumen->diunggah_oleh === $request->user()->id, 403);
    }

    // Lihat: dokumen harus milik siswa di kelas yang sedang dipegang guru pada tahun ajaran aktif
    private function pastikanDiKelas(Request $request, Dokumen $dokumen): void
    {
        $wali = $request->user()->guru?->waliAktif;

        abort_unless(
            $wali
                && $dokumen->kelas_id === $wali->kelas_id
                && $dokumen->tahun_ajaran_id === $wali->tahun_ajaran_id,
            403
        );
    }

    // Guru wajib sudah ditugaskan sebagai wali kelas pada tahun ajaran aktif untuk menulis data
    private function waliLogin(Request $request): WaliKelas
    {
        $wali = $request->user()->guru?->waliAktif;

        abort_unless($wali, 403, 'Anda belum ditugaskan sebagai wali kelas pada tahun ajaran aktif. Hubungi Admin.');

        return $wali;
    }

    private function batasiKelas($query, ?WaliKelas $wali): void
    {
        if (!$wali) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->where('kelas_id', $wali->kelas_id)->where('tahun_ajaran_id', $wali->tahun_ajaran_id);
    }

    // Akun guru wajib terhubung ke data guru agar riwayat bisa dicatat.
    // Dicek di awal setiap aksi tulis, sebelum ada data/berkas yang tersimpan.
    private function guruLogin(Request $request): Guru
    {
        $guru = $request->user()->guru;

        abort_unless($guru, 403, 'Akun ini belum terhubung ke data guru.');

        return $guru;
    }

    private function catat(Request $request, string $aksi, Dokumen $dokumen, ?string $keterangan = null): void
    {
        $dokumen->loadMissing('siswa');
        AktivitasGuru::catat($this->guruLogin($request), $aksi, $dokumen, $keterangan);
    }

    // Hanya siswa di kelas yang dipegang guru pada tahun ajaran aktif
    private function siswaOptions(Request $request)
    {
        $wali = $request->user()->guru?->waliAktif;

        if (!$wali) {
            return collect();
        }

        return Siswa::query()
            ->whereIn('id', RiwayatKelas::where('kelas_id', $wali->kelas_id)
                ->where('tahun_ajaran_id', $wali->tahun_ajaran_id)->pluck('siswa_id'))
            ->orderBy('nama')
            ->get(['id', 'nis', 'nama', 'kelas']);
    }

    private function validated(Request $request, bool $fileRequired = true): array
    {
        return $request->validate([
            'siswa_id' => ['required', Rule::in($this->siswaOptions($request)->pluck('id')->all())],
            'nama_dokumen' => ['required', 'string', 'max:255'],
            'kategori' => ['required', Rule::in(Dokumen::KATEGORI)],
            'berkas' => [
                $fileRequired ? 'required' : 'nullable',
                'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120',
            ],
        ], [
            'siswa_id.required' => 'Pilih siswa pemilik dokumen.',
            'siswa_id.in' => 'Siswa tersebut tidak berada di kelas Anda.',
            'nama_dokumen.required' => 'Nama dokumen wajib diisi.',
            'kategori.required' => 'Kategori wajib dipilih.',
            'berkas.required' => 'Berkas wajib diunggah.',
            'berkas.mimes' => 'Berkas harus berformat PDF, JPG, atau PNG.',
            'berkas.max' => 'Ukuran berkas maksimal 5 MB.',
        ]);
    }
}
