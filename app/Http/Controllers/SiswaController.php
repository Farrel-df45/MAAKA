<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Siswa;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SiswaController extends Controller
{
    // Daftar siswa + pencarian (nama / NIS)
    public function index(Request $request): View
    {
        $q = trim((string) $request->input('q', ''));

        $siswas = Siswa::query()
            ->withCount('dokumens')
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($w) use ($q) {
                    $w->where('nama', 'like', "%{$q}%")
                        ->orWhere('nis', 'like', "%{$q}%");
                });
            })
            ->orderBy('nama')
            ->paginate(10)
            ->withQueryString();

        return view('admin.siswa.index', compact('siswas', 'q'));
    }

    // Form tambah siswa
    public function create(): View
    {
        return view('admin.siswa.form', ['siswa' => new Siswa()]);
    }

    // Simpan siswa baru
    public function store(Request $request): RedirectResponse
    {
        $siswa = Siswa::create($this->validated($request));

        return redirect()
            ->route('admin.siswa.index')
            ->with('success', "Siswa {$siswa->nama} berhasil ditambahkan.");
    }

    // Form ubah siswa
    public function edit(Siswa $siswa): View
    {
        return view('admin.siswa.form', compact('siswa'));
    }

    // Simpan perubahan siswa
    public function update(Request $request, Siswa $siswa): RedirectResponse
    {
        $siswa->update($this->validated($request, $siswa));

        return redirect()
            ->route('admin.siswa.index')
            ->with('success', "Data {$siswa->nama} berhasil diperbarui.");
    }

    // Hapus siswa (dokumen dan legalisir milik siswa ikut terhapus oleh database)
    public function destroy(Siswa $siswa): RedirectResponse
    {
        $nama = $siswa->nama;
        $siswa->delete();

        return redirect()
            ->route('admin.siswa.index')
            ->with('success', "Siswa {$nama} berhasil dihapus.");
    }

    // Aturan validasi bersama untuk tambah dan ubah
    private function validated(Request $request, ?Siswa $siswa = null): array
    {
        return $request->validate([
            'nis' => [
                'required', 'string', 'max:50',
                Rule::unique('siswas', 'nis')->ignore($siswa?->id),
            ],
            'nama' => ['required', 'string', 'max:255'],
            'kelas' => ['required', 'string', 'max:50'],
            'jurusan' => ['required', 'string', 'max:255'],
            'alamat' => ['nullable', 'string', 'max:255'],
        ], [
            'nis.required' => 'NIS wajib diisi.',
            'nis.unique' => 'NIS ini sudah terdaftar.',
            'nama.required' => 'Nama wajib diisi.',
            'kelas.required' => 'Kelas wajib diisi.',
            'jurusan.required' => 'Jurusan wajib diisi.',
        ]);
    }
}