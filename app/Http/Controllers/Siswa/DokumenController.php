<?php

namespace App\Http\Controllers\Siswa;

use App\Http\Controllers\Controller;
use App\Models\Dokumen;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

// Siswa hanya dapat melihat dokumen miliknya sendiri (semua periode kelas).
class DokumenController extends Controller
{
    private const DISK = 'local';
    private const MAKS_MENUNGGU = 10;

    public function index(Request $request): View
    {
        $siswa = $request->user()->siswa;
        $status = $request->input('status');

        $dokumens = Dokumen::query()
            ->with(['kelas', 'tahunAjaran'])
            ->where('siswa_id', $siswa?->id ?? 0)
            ->when(in_array($status, Dokumen::STATUSES, true), fn ($q) => $q->where('status', $status))
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('siswa.dokumen.index', compact('siswa', 'dokumens', 'status'));
    }

    public function create(Request $request): View
    {
        return view('siswa.dokumen.create', [
            'siswa' => $request->user()->siswa,
            'kategori' => $request->input('kategori'),
        ]);
    }

    // Siswa mengunggah dokumennya sendiri; selalu berstatus Menunggu Verifikasi
    public function store(Request $request): RedirectResponse
    {
        $siswa = $request->user()->siswa;
        abort_unless($siswa, 403, 'Akun Anda belum terhubung ke data siswa. Hubungi Admin.');

        $data = $request->validate([
            'nama_dokumen' => ['required', 'string', 'max:255'],
            'kategori' => ['required', Rule::in(Dokumen::KATEGORI)],
            'berkas' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ], [
            'nama_dokumen.required' => 'Nama dokumen wajib diisi.',
            'kategori.required' => 'Kategori wajib dipilih.',
            'kategori.in' => 'Kategori tidak valid.',
            'berkas.required' => 'Berkas wajib diunggah.',
            'berkas.mimes' => 'Berkas harus berformat PDF, JPG, atau PNG.',
            'berkas.max' => 'Ukuran berkas maksimal 5 MB.',
        ]);

        $menunggu = Dokumen::where('siswa_id', $siswa->id)->where('status', Dokumen::STATUS_MENUNGGU)->count();
        if ($menunggu >= self::MAKS_MENUNGGU) {
            return back()->withInput()->with('error', 'Masih ada ' . $menunggu . ' dokumen yang menunggu verifikasi. Tunggu hingga diperiksa Admin sebelum mengunggah lagi.');
        }

        Dokumen::create([
            'siswa_id' => $siswa->id,
            'nama_dokumen' => $data['nama_dokumen'],
            'kategori' => $data['kategori'],
            'status' => Dokumen::STATUS_MENUNGGU,
            'diunggah_oleh' => $request->user()->id,
            'file_path' => $request->file('berkas')->store('dokumen', self::DISK),
        ]);

        return redirect()->route('siswa.dokumen.index')
            ->with('success', "Dokumen {$data['nama_dokumen']} terkirim dan menunggu verifikasi Admin.");
    }

    // Hanya dokumen sendiri yang belum terverifikasi yang boleh dihapus
    public function destroy(Request $request, Dokumen $dokumen): RedirectResponse
    {
        abort_unless($request->user()->siswa_id !== null && $dokumen->siswa_id === $request->user()->siswa_id, 403);

        if ($dokumen->status === Dokumen::STATUS_TERVERIFIKASI) {
            return back()->with('error', 'Dokumen yang sudah terverifikasi tidak dapat dihapus. Hubungi Admin.');
        }

        $path = $dokumen->file_path;
        $nama = $dokumen->nama_dokumen;
        $dokumen->delete();
        Storage::disk(self::DISK)->delete($path);

        return back()->with('success', "Dokumen {$nama} dihapus.");
    }

    public function berkas(Request $request, Dokumen $dokumen): Response|RedirectResponse
    {
        abort_unless($request->user()->siswa_id !== null && $dokumen->siswa_id === $request->user()->siswa_id, 403);

        /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
        $disk = Storage::disk(self::DISK);

        if (!$disk->exists($dokumen->file_path)) {
            return back()->with('error', "Berkas {$dokumen->nama_dokumen} tidak ditemukan di penyimpanan.");
        }

        return $disk->response($dokumen->file_path);
    }
}
