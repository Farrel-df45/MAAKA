<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Kelas;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SiswaController extends Controller
{
    // Daftar siswa + pencarian (nama / NIS)
    public function index(Request $request): View
    {
        $q = trim((string) $request->input('q', ''));
        $kelasId = $request->integer('kelas_id') ?: null;

        $siswas = Siswa::query()
            ->with('user:id,siswa_id')
            ->withCount('dokumens')
            ->when($kelasId, fn ($query) => $query->whereHas('riwayatAktif', fn ($r) => $r->where('kelas_id', $kelasId)))
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($w) use ($q) {
                    $w->where('nama', 'like', "%{$q}%")
                        ->orWhere('nis', 'like', "%{$q}%");
                });
            })
            ->orderBy('nama')
            ->paginate(10)
            ->withQueryString();

        return view('admin.siswa.index', [
            'siswas' => $siswas,
            'q' => $q,
            'kelasId' => $kelasId,
            'daftarKelas' => Kelas::orderBy('tingkat')->orderBy('nama')->get(['id', 'nama']),
        ]);
    }

    // Form tambah siswa
    public function create(): View
    {
        return view('admin.siswa.form', $this->formData(new Siswa()));
    }

    // Simpan siswa baru (+ akun login bila kata sandi diisi)
    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $tahun = $this->tahunAktif();

        $siswa = DB::transaction(function () use ($data, $tahun) {
            $siswa = Siswa::create($this->siswaAttributes($data));
            $siswa->tempatkanDiKelas(Kelas::findOrFail($data['kelas_id']), $tahun);

            if (filled($data['password'] ?? null)) {
                $this->buatAkun($siswa, $data['email'], $data['password']);
            }

            return $siswa;
        });

        $pesan = "Siswa {$siswa->nama} berhasil ditambahkan.";
        if (filled($data['password'] ?? null)) {
            $pesan .= " Akun login dibuat: masuk dengan NIS {$siswa->nis} atau email.";
        }

        return redirect()->route('admin.siswa.index')->with('success', $pesan);
    }

    // Form ubah siswa
    public function edit(Siswa $siswa): View
    {
        $siswa->load('user:id,siswa_id,email');

        return view('admin.siswa.form', $this->formData($siswa));
    }

    // Simpan perubahan siswa (+ akun login)
    public function update(Request $request, Siswa $siswa): RedirectResponse
    {
        $data = $this->validated($request, $siswa);

        $tahun = filled($data['kelas_id'] ?? null) ? $this->tahunAktif() : null;

        DB::transaction(function () use ($siswa, $data, $tahun) {
            $siswa->update($this->siswaAttributes($data));

            // Alumni (tanpa kelas aktif) boleh diubah datanya tanpa ditempatkan kembali
            if ($tahun) {
                $siswa->tempatkanDiKelas(Kelas::findOrFail($data['kelas_id']), $tahun);
            }

            if ($siswa->user) {
                $akun = ['name' => $siswa->nama];
                if (filled($data['email'] ?? null)) {
                    $akun['email'] = $data['email'];
                }
                if (filled($data['password'] ?? null)) {
                    $akun['password'] = Hash::make($data['password']);
                }
                $siswa->user->update($akun);
            } elseif (filled($data['password'] ?? null)) {
                $this->buatAkun($siswa, $data['email'], $data['password']);
            }
        });

        return redirect()
            ->route('admin.siswa.index')
            ->with('success', "Data {$siswa->nama} berhasil diperbarui.");
    }

    // Hapus siswa beserta akun loginnya (dokumen dan legalisir ikut terhapus oleh database)
    public function destroy(Siswa $siswa): RedirectResponse
    {
        $nama = $siswa->nama;

        DB::transaction(function () use ($siswa) {
            $siswa->user?->delete();
            $siswa->delete();
        });

        return redirect()
            ->route('admin.siswa.index')
            ->with('success', "Siswa {$nama} berhasil dihapus.");
    }

    // Riwayat kelas siswa per tahun ajaran beserta wali kelas dan dokumen pada tiap periode
    public function riwayat(Siswa $siswa): View
    {
        $siswa->load(['riwayatKelas.kelas', 'riwayatKelas.tahunAjaran']);

        $periode = $siswa->riwayatKelas
            ->sortByDesc('tahun_ajaran_id')
            ->values()
            ->map(function ($r) use ($siswa) {
                return [
                    'riwayat' => $r,
                    'wali' => \App\Models\WaliKelas::where('kelas_id', $r->kelas_id)
                        ->where('tahun_ajaran_id', $r->tahun_ajaran_id)->first(),
                    'dokumens' => $siswa->dokumens()
                        ->where('tahun_ajaran_id', $r->tahun_ajaran_id)
                        ->with('pengunggah:id,name')
                        ->latest()->get(),
                ];
            });

        // Dokumen lama yang belum punya cap kelas/tahun ajaran
        $tanpaPeriode = $siswa->dokumens()->whereNull('tahun_ajaran_id')->latest()->get();

        return view('admin.siswa.riwayat', compact('siswa', 'periode', 'tanpaPeriode'));
    }

    private function tahunAktif(): TahunAjaran
    {
        $tahun = TahunAjaran::aktif();

        if (!$tahun) {
            throw ValidationException::withMessages([
                'kelas_id' => 'Belum ada tahun ajaran aktif. Buat tahun ajaran terlebih dahulu.',
            ]);
        }

        return $tahun;
    }

    private function formData(Siswa $siswa): array
    {
        return [
            'siswa' => $siswa,
            'daftarKelas' => Kelas::orderBy('tingkat')->orderBy('nama')->get(['id', 'nama', 'jurusan']),
            'kelasAktifId' => $siswa->exists ? $siswa->riwayatAktif?->kelas_id : null,
            'tahun' => TahunAjaran::aktif(),
        ];
    }

    private function buatAkun(Siswa $siswa, string $email, string $password): User
    {
        return User::create([
            'name' => $siswa->nama,
            'email' => $email,
            'password' => Hash::make($password),
            'role' => 'siswa',
            'siswa_id' => $siswa->id,
        ]);
    }

    private function siswaAttributes(array $data): array
    {
        $atribut = collect($data)->only(['nis', 'nama', 'jurusan', 'alamat'])->all();
        // Label kelas diisi dari kelas yang dipilih (disinkronkan lagi saat penempatan)
        if (filled($data['kelas_id'] ?? null)) {
            $atribut['kelas'] = Kelas::find($data['kelas_id'])?->nama ?? '';
        }

        return $atribut;
    }

    // Aturan validasi bersama untuk tambah dan ubah
    private function validated(Request $request, ?Siswa $siswa = null): array
    {
        $akunId = $siswa?->user?->id;

        return $request->validate([
            'nis' => [
                'required', 'string', 'max:50',
                Rule::unique('siswas', 'nis')->ignore($siswa?->id),
            ],
            'nama' => ['required', 'string', 'max:255'],
            // Wajib kecuali mengubah alumni yang sudah tidak punya kelas aktif
            'kelas_id' => [
                Rule::requiredIf(fn () => !$siswa || $siswa->riwayatAktif !== null),
                'nullable', 'exists:kelas,id',
            ],
            'jurusan' => ['required', 'string', 'max:255'],
            'alamat' => ['nullable', 'string', 'max:255'],
            // Email wajib bila membuat akun baru (kata sandi diisi tanpa akun yang ada)
            'email' => [
                Rule::requiredIf(fn () => $request->filled('password') && !$akunId),
                'nullable', 'email', 'max:255',
                Rule::unique('users', 'email')->ignore($akunId),
            ],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ], [
            'nis.required' => 'NIS wajib diisi.',
            'nis.unique' => 'NIS ini sudah terdaftar.',
            'nama.required' => 'Nama wajib diisi.',
            'kelas_id.required' => 'Pilih kelas siswa.',
            'kelas_id.exists' => 'Kelas yang dipilih tidak ditemukan.',
            'jurusan.required' => 'Jurusan wajib diisi.',
            'email.required' => 'Email akun wajib diisi untuk membuat akun login.',
            'email.email' => 'Format email tidak valid.',
            'email.unique' => 'Email ini sudah digunakan akun lain.',
            'password.min' => 'Kata sandi minimal 8 karakter.',
            'password.confirmed' => 'Konfirmasi kata sandi tidak sama.',
        ]);
    }
}
