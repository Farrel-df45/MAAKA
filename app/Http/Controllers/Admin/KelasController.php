<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Guru;
use App\Models\Kelas;
use App\Models\TahunAjaran;
use App\Models\WaliKelas;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class KelasController extends Controller
{
    // Daftar kelas + wali kelas dan jumlah siswa pada tahun ajaran aktif
    public function index(Request $request): View
    {
        $q = trim((string) $request->input('q', ''));

        $kelas = Kelas::query()
            ->with('waliAktif')
            ->withCount('siswaAktif')
            ->when($q !== '', fn ($query) => $query->where('nama', 'like', "%{$q}%"))
            ->orderBy('tingkat')
            ->orderBy('nama')
            ->paginate(15)
            ->withQueryString();

        return view('admin.kelas.index', [
            'kelas' => $kelas,
            'q' => $q,
            'tahun' => TahunAjaran::aktif(),
        ]);
    }

    public function create(): View
    {
        return view('admin.kelas.form', $this->formData(new Kelas()));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        DB::transaction(function () use ($data) {
            $kelas = Kelas::create(collect($data)->only(['nama', 'tingkat', 'jurusan'])->all());
            $this->sinkronWali($kelas, $data['guru_id'] ?? null);
        });

        return redirect()->route('admin.kelas.index')
            ->with('success', "Kelas {$data['nama']} berhasil ditambahkan.");
    }

    public function edit(Kelas $kelas): View
    {
        return view('admin.kelas.form', $this->formData($kelas));
    }

    public function update(Request $request, Kelas $kelas): RedirectResponse
    {
        $data = $this->validated($request, $kelas);

        DB::transaction(function () use ($kelas, $data) {
            $kelas->update(collect($data)->only(['nama', 'tingkat', 'jurusan'])->all());
            $this->sinkronWali($kelas, $data['guru_id'] ?? null);
        });

        return redirect()->route('admin.kelas.index')
            ->with('success', "Kelas {$kelas->nama} berhasil diperbarui.");
    }

    // Kelas yang sudah punya riwayat siswa atau wali tidak boleh dihapus agar histori tetap utuh
    public function destroy(Kelas $kelas): RedirectResponse
    {
        if ($kelas->riwayat()->exists() || $kelas->waliKelas()->exists()) {
            return back()->with('error', "Kelas {$kelas->nama} sudah punya riwayat siswa/wali sehingga tidak bisa dihapus.");
        }

        $nama = $kelas->nama;
        $kelas->delete();

        return redirect()->route('admin.kelas.index')->with('success', "Kelas {$nama} berhasil dihapus.");
    }

    private function formData(Kelas $kelas): array
    {
        $tahun = TahunAjaran::aktif();

        return [
            'kelas' => $kelas,
            'tahun' => $tahun,
            'guruAktifId' => $kelas->exists ? $kelas->waliAktif?->guru_id : null,
            'gurus' => Guru::orderBy('nama')->get(['id', 'nip', 'nama']),
        ];
    }

    // Tetapkan / lepas wali kelas pada tahun ajaran aktif
    private function sinkronWali(Kelas $kelas, mixed $guruId): void
    {
        $tahun = TahunAjaran::aktif();
        if (!$tahun) {
            return;
        }

        if (blank($guruId)) {
            WaliKelas::where('kelas_id', $kelas->id)->where('tahun_ajaran_id', $tahun->id)->delete();

            return;
        }

        WaliKelas::tetapkan(Guru::findOrFail($guruId), $kelas, $tahun);
    }

    private function validated(Request $request, ?Kelas $kelas = null): array
    {
        $data = $request->validate([
            'nama' => ['required', 'string', 'max:50', Rule::unique('kelas', 'nama')->ignore($kelas?->id)],
            'tingkat' => ['nullable', Rule::in(Kelas::TINGKAT)],
            'jurusan' => ['nullable', 'string', 'max:255'],
            'guru_id' => ['nullable', 'exists:gurus,id'],
        ], [
            'nama.required' => 'Nama kelas wajib diisi.',
            'nama.unique' => 'Nama kelas ini sudah ada.',
            'tingkat.in' => 'Tingkat harus X, XI, atau XII.',
        ]);

        // Guru yang sudah menjadi wali kelas lain tidak boleh dipilih lagi
        $tahun = TahunAjaran::aktif();
        if ($tahun && filled($data['guru_id'] ?? null)) {
            $dipakai = WaliKelas::where('tahun_ajaran_id', $tahun->id)
                ->where('guru_id', $data['guru_id'])
                ->when($kelas, fn ($q) => $q->where('kelas_id', '!=', $kelas->id))
                ->with('kelas')->first();

            if ($dipakai) {
                throw ValidationException::withMessages([
                    'guru_id' => "Guru ini sudah menjadi wali kelas {$dipakai->kelas->nama} pada tahun ajaran {$tahun->nama}.",
                ]);
            }
        }

        return $data;
    }
}
