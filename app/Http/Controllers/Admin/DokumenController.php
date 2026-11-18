<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Dokumen;
use App\Models\Siswa;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class DokumenController extends Controller
{
    private const DISK = 'local';

    // Daftar dokumen + pencarian + filter status & kategori
    public function index(Request $request): View
    {
        $q = trim((string) $request->input('q', ''));
        $status = $request->input('status');
        $kategori = $request->input('kategori');

        $dokumens = Dokumen::query()
            ->with(['siswa', 'pengunggah'])
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($w) use ($q) {
                    $w->where('nama_dokumen', 'like', "%{$q}%")
                        ->orWhereHas('siswa', function ($s) use ($q) {
                            $s->where('nama', 'like', "%{$q}%")
                                ->orWhere('nis', 'like', "%{$q}%");
                        });
                });
            })
            ->when(in_array($status, Dokumen::STATUSES, true), fn ($query) => $query->where('status', $status))
            ->when(in_array($kategori, Dokumen::KATEGORI, true), fn ($query) => $query->where('kategori', $kategori))
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('admin.dokumen.index', compact('dokumens', 'q', 'status', 'kategori'));
    }

    public function create(): View
    {
        return view('admin.dokumen.form', [
            'dokumen' => new Dokumen(['status' => Dokumen::STATUS_TERVERIFIKASI]),
            'siswas' => $this->siswaOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $data['file_path'] = $request->file('berkas')->store('dokumen', self::DISK);
        $data['diunggah_oleh'] = $request->user()->id;
        unset($data['berkas']);

        $dokumen = Dokumen::create($data);

        return redirect()
            ->route('admin.dokumen.index')
            ->with('success', "Dokumen {$dokumen->nama_dokumen} berhasil ditambahkan.");
    }

    public function edit(Dokumen $dokumen): View
    {
        return view('admin.dokumen.form', [
            'dokumen' => $dokumen,
            'siswas' => $this->siswaOptions(),
        ]);
    }

    public function update(Request $request, Dokumen $dokumen): RedirectResponse
    {
        $data = $this->validated($request, fileRequired: false);
        unset($data['berkas']);

        $oldPath = $dokumen->file_path;

        if ($request->hasFile('berkas')) {
            $data['file_path'] = $request->file('berkas')->store('dokumen', self::DISK);
        }

        $dokumen->update($data);

        // Hapus berkas lama hanya setelah berkas baru berhasil disimpan
        if ($request->hasFile('berkas') && $oldPath !== $dokumen->file_path) {
            Storage::disk(self::DISK)->delete($oldPath);
        }

        return redirect()
            ->route('admin.dokumen.index')
            ->with('success', "Dokumen {$dokumen->nama_dokumen} berhasil diperbarui.");
    }

    // Hapus dokumen + berkasnya (legalisir terkait ikut terhapus oleh database)
    public function destroy(Dokumen $dokumen): RedirectResponse
    {
        $nama = $dokumen->nama_dokumen;
        $path = $dokumen->file_path;

        $dokumen->delete();
        Storage::disk(self::DISK)->delete($path);

        return redirect()
            ->route('admin.dokumen.index')
            ->with('success', "Dokumen {$nama} berhasil dihapus.");
    }

    // Verifikasi / tolak dokumen dari daftar (penolakan boleh disertai alasan)
    public function updateStatus(Request $request, Dokumen $dokumen): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(Dokumen::STATUSES)],
            'catatan' => ['nullable', 'string', 'max:500'],
        ]);

        $dokumen->update([
            'status' => $validated['status'],
            'catatan' => $validated['status'] === Dokumen::STATUS_DITOLAK ? ($validated['catatan'] ?? null) : null,
        ]);

        return back()->with('success', "Status {$dokumen->nama_dokumen} diubah menjadi {$validated['status']}.");
    }

    // Verifikasi / tolak banyak dokumen sekaligus
    public function massal(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:dokumens,id'],
            'aksi' => ['required', Rule::in(['verifikasi', 'tolak'])],
            'catatan' => ['nullable', 'string', 'max:500', 'required_if:aksi,tolak'],
        ], [
            'ids.required' => 'Pilih minimal satu dokumen.',
            'catatan.required_if' => 'Alasan penolakan wajib diisi.',
        ]);

        $tolak = $validated['aksi'] === 'tolak';

        $jumlah = 0;
        foreach (Dokumen::whereIn('id', $validated['ids'])->get() as $dokumen) {
            // lewat model (bukan update massal) agar siswa menerima notifikasi
            $dokumen->update([
                'status' => $tolak ? Dokumen::STATUS_DITOLAK : Dokumen::STATUS_TERVERIFIKASI,
                'catatan' => $tolak ? $validated['catatan'] : null,
            ]);
            $jumlah++;
        }

        return back()->with('success', $jumlah . ' dokumen berhasil ' . ($tolak ? 'ditolak.' : 'diverifikasi.'));
    }

    // Tampilkan berkas (PDF/gambar) langsung di browser
    public function berkas(Dokumen $dokumen): Response|RedirectResponse
    {
        /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
        $disk = Storage::disk(self::DISK);

        if (!$disk->exists($dokumen->file_path)) {
            return back()->with('error', "Berkas {$dokumen->nama_dokumen} tidak ditemukan di penyimpanan.");
        }

        return $disk->response($dokumen->file_path);
    }

    private function siswaOptions()
    {
        return Siswa::orderBy('nama')->get(['id', 'nis', 'nama', 'kelas']);
    }

    private function validated(Request $request, bool $fileRequired = true): array
    {
        return $request->validate([
            'siswa_id' => ['required', 'exists:siswas,id'],
            'nama_dokumen' => ['required', 'string', 'max:255'],
            'kategori' => ['required', Rule::in(Dokumen::KATEGORI)],
            'status' => ['required', Rule::in(Dokumen::STATUSES)],
            'berkas' => [
                $fileRequired ? 'required' : 'nullable',
                'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120',
            ],
        ], [
            'siswa_id.required' => 'Pilih siswa pemilik dokumen.',
            'siswa_id.exists' => 'Siswa tidak ditemukan.',
            'nama_dokumen.required' => 'Nama dokumen wajib diisi.',
            'kategori.required' => 'Kategori wajib dipilih.',
            'kategori.in' => 'Kategori tidak valid.',
            'berkas.required' => 'Berkas wajib diunggah.',
            'berkas.mimes' => 'Berkas harus berformat PDF, JPG, atau PNG.',
            'berkas.max' => 'Ukuran berkas maksimal 5 MB.',
        ]);
    }
}
