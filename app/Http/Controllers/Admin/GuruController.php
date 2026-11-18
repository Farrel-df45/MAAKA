<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AktivitasGuru;
use App\Models\Guru;
use App\Models\Kelas;
use App\Models\RiwayatKelas;
use App\Models\TahunAjaran;
use App\Models\WaliKelas;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class GuruController extends Controller
{
    // Daftar guru + pencarian (nama / NIP / email)
    public function index(Request $request): View
    {
        $q = trim((string) $request->input('q', ''));

        $gurus = Guru::query()
            ->with(['user:id,guru_id', 'waliAktif.kelas'])
            ->withCount('aktivitas')
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($w) use ($q) {
                    $w->where('nama', 'like', "%{$q}%")
                        ->orWhere('nip', 'like', "%{$q}%")
                        ->orWhere('email', 'like', "%{$q}%");
                });
            })
            ->orderBy('nama')
            ->paginate(10)
            ->withQueryString();

        return view('admin.guru.index', compact('gurus', 'q'));
    }

    public function create(): View
    {
        return view('admin.guru.form', $this->formData(new Guru(['jabatan' => 'Guru/Wali Kelas'])));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $password = $data['password'] ?? null;

        $guru = DB::transaction(function () use ($data, $password) {
            $guru = Guru::create($this->guruAttributes($data));
            $this->sinkronWali($guru, $data['kelas_id'] ?? null);

            if (filled($password)) {
                $this->buatAkun($guru, $password);
            }

            return $guru;
        });

        $pesan = "Guru {$guru->nama} berhasil ditambahkan.";
        if (filled($password)) {
            $pesan .= " Akun login dibuat: masuk dengan NIP {$guru->nip} atau email.";
        }

        return redirect()->route('admin.guru.index')->with('success', $pesan);
    }

    public function edit(Guru $guru): View
    {
        $guru->load('user:id,guru_id');

        return view('admin.guru.form', $this->formData($guru));
    }

    public function update(Request $request, Guru $guru): RedirectResponse
    {
        $data = $this->validated($request, $guru);
        $password = $data['password'] ?? null;

        DB::transaction(function () use ($guru, $data, $password) {
            $guru->update($this->guruAttributes($data));
            $this->sinkronWali($guru, $data['kelas_id'] ?? null);

            if ($guru->user) {
                // Samakan nama & email akun dengan data guru
                $akun = ['name' => $guru->nama, 'email' => $guru->email];
                if (filled($password)) {
                    $akun['password'] = Hash::make($password);
                }
                $guru->user->update($akun);
            } elseif (filled($password)) {
                $this->buatAkun($guru, $password);
            }
        });

        return redirect()
            ->route('admin.guru.index')
            ->with('success', "Data {$guru->nama} berhasil diperbarui.");
    }

    // Hapus guru beserta akun login miliknya (riwayat aktivitas tetap tersimpan)
    public function destroy(Guru $guru): RedirectResponse
    {
        $nama = $guru->nama;

        DB::transaction(function () use ($guru) {
            $guru->user?->delete();
            $guru->delete();
        });

        return redirect()
            ->route('admin.guru.index')
            ->with('success', "Guru {$nama} berhasil dihapus.");
    }

    // Halaman pantau guru: riwayat unggah / ubah / hapus dokumen (semua guru, atau satu guru)
    public function aktivitas(Request $request, ?Guru $guru = null): View
    {
        $aksi = $request->input('aksi');
        $guruId = $guru?->id ?? $request->integer('guru_id') ?: null;
        $q = trim((string) $request->input('q', ''));
        $kelas = trim((string) $request->input('kelas', ''));
        $tahun = trim((string) $request->input('tahun', ''));

        $dasar = AktivitasGuru::query()
            ->when($guruId, fn ($query) => $query->where('guru_id', $guruId));

        $aktivitas = (clone $dasar)
            ->when(in_array($aksi, AktivitasGuru::AKSI, true), fn ($query) => $query->where('aksi', $aksi))
            ->when($kelas !== '', fn ($query) => $query->where('kelas_nama', $kelas))
            ->when($tahun !== '', fn ($query) => $query->where('tahun_ajaran_nama', $tahun))
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($w) use ($q) {
                    $w->where('nama_dokumen', 'like', "%{$q}%")
                        ->orWhere('nama_siswa', 'like', "%{$q}%");
                });
            })
            ->latest()
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        // Ringkasan untuk memantau perilaku guru
        $ringkasan = [
            'total' => (clone $dasar)->count(),
            'hariIni' => (clone $dasar)->where('created_at', '>=', now()->startOfDay())->count(),
            'mingguIni' => (clone $dasar)->where('created_at', '>=', now()->subDays(7))->count(),
            'hapus' => (clone $dasar)->where('aksi', AktivitasGuru::HAPUS)->count(),
        ];

        $perGuru = $guru ? collect() : AktivitasGuru::query()
            ->selectRaw("guru_id, guru_nama, guru_nip, count(*) as total,
                sum(case when aksi = 'Unggah' then 1 else 0 end) as unggah,
                sum(case when aksi = 'Ubah' then 1 else 0 end) as ubah,
                sum(case when aksi = 'Hapus' then 1 else 0 end) as hapus,
                max(created_at) as terakhir")
            ->groupBy('guru_id', 'guru_nama', 'guru_nip')
            ->orderByDesc('terakhir')
            ->limit(10)
            ->get();

        return view('admin.guru.aktivitas', [
            'aktivitas' => $aktivitas,
            'guru' => $guru,
            'gurus' => $guru ? collect() : Guru::orderBy('nama')->get(['id', 'nama', 'nip']),
            'guruId' => $guruId,
            'aksi' => $aksi,
            'q' => $q,
            'kelas' => $kelas,
            'tahun' => $tahun,
            'daftarKelas' => AktivitasGuru::whereNotNull('kelas_nama')->distinct()->orderBy('kelas_nama')->pluck('kelas_nama'),
            'daftarTahun' => TahunAjaran::orderByDesc('id')->pluck('nama'),
            'ringkasan' => $ringkasan,
            'perGuru' => $perGuru,
        ]);
    }

    // Riwayat wali kelas seorang guru: kelas mana saja, siswa siapa saja, dan dokumen yang ia unggah
    public function waliKelas(Guru $guru): View
    {
        $penugasan = WaliKelas::where('guru_id', $guru->id)
            ->with(['kelas', 'tahunAjaran'])
            ->orderByDesc('tahun_ajaran_id')
            ->get()
            ->map(function ($w) use ($guru) {
                $siswas = RiwayatKelas::where('kelas_id', $w->kelas_id)
                    ->where('tahun_ajaran_id', $w->tahun_ajaran_id)
                    ->with(['siswa' => fn ($s) => $s->withCount([
                        'dokumens as dokumen_periode_count' => fn ($d) => $d
                            ->where('tahun_ajaran_id', $w->tahun_ajaran_id)
                            ->where('kelas_id', $w->kelas_id),
                    ])])
                    ->get()->pluck('siswa')->filter()->sortBy('nama')->values();

                return [
                    'wali' => $w,
                    'siswas' => $siswas,
                    'diunggahGuru' => \App\Models\Dokumen::where('kelas_id', $w->kelas_id)
                        ->where('tahun_ajaran_id', $w->tahun_ajaran_id)
                        ->whereIn('diunggah_oleh', $guru->user ? [$guru->user->id] : [0])
                        ->count(),
                ];
            });

        return view('admin.guru.wali', compact('guru', 'penugasan'));
    }

    private function formData(Guru $guru): array
    {
        $tahun = TahunAjaran::aktif();

        return [
            'guru' => $guru,
            'tahun' => $tahun,
            'kelasAktifId' => $guru->exists ? $guru->waliAktif?->kelas_id : null,
            'daftarKelas' => Kelas::with('waliAktif')->orderBy('tingkat')->orderBy('nama')->get(),
        ];
    }

    // Tetapkan / lepas guru sebagai wali kelas pada tahun ajaran aktif
    private function sinkronWali(Guru $guru, mixed $kelasId): void
    {
        $tahun = TahunAjaran::aktif();
        if (!$tahun) {
            return;
        }

        if (blank($kelasId)) {
            WaliKelas::where('guru_id', $guru->id)->where('tahun_ajaran_id', $tahun->id)->delete();

            return;
        }

        WaliKelas::tetapkan($guru, Kelas::findOrFail($kelasId), $tahun);
    }

    private function buatAkun(Guru $guru, string $password): User
    {
        return User::create([
            'name' => $guru->nama,
            'email' => $guru->email,
            'password' => Hash::make($password),
            'role' => 'guru',
            'guru_id' => $guru->id,
        ]);
    }

    private function guruAttributes(array $data): array
    {
        return collect($data)->only(['nip', 'nama', 'email', 'nomor_telepon', 'jabatan'])->all();
    }

    private function validated(Request $request, ?Guru $guru = null): array
    {
        $akunId = $guru?->user?->id;
        // Email guru dipakai juga sebagai email akun, jadi harus unik di tabel users
        $cekEmailAkun = $akunId || $request->filled('password');

        $emailRules = [
            'required', 'email', 'max:255',
            Rule::unique('gurus', 'email')->ignore($guru?->id),
        ];
        if ($cekEmailAkun) {
            $emailRules[] = Rule::unique('users', 'email')->ignore($akunId);
        }

        $data = $request->validate([
            'kelas_id' => ['nullable', 'exists:kelas,id'],
            'nip' => [
                'required_with:password', 'nullable', 'string', 'max:50',
                Rule::unique('gurus', 'nip')->ignore($guru?->id),
            ],
            'nama' => ['required', 'string', 'max:255'],
            'email' => $emailRules,
            'nomor_telepon' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+\-\s()]+$/'],
            'jabatan' => ['required', 'string', 'max:100'],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ], [
            'nip.required_with' => 'NIP wajib diisi jika membuat akun login.',
            'nip.unique' => 'NIP ini sudah terdaftar.',
            'nama.required' => 'Nama wajib diisi.',
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'email.unique' => 'Email ini sudah terdaftar.',
            'nomor_telepon.regex' => 'Nomor telepon hanya boleh berisi angka, spasi, +, -, dan tanda kurung.',
            'jabatan.required' => 'Jabatan wajib diisi.',
            'password.min' => 'Kata sandi minimal 8 karakter.',
            'password.confirmed' => 'Konfirmasi kata sandi tidak sama.',
        ]);

        // Satu kelas hanya punya satu wali pada tahun ajaran aktif
        $tahun = TahunAjaran::aktif();
        if ($tahun && filled($data['kelas_id'] ?? null)) {
            $sudah = WaliKelas::where('tahun_ajaran_id', $tahun->id)
                ->where('kelas_id', $data['kelas_id'])
                ->when($guru, fn ($q) => $q->where(fn ($w) => $w->where('guru_id', '!=', $guru->id)->orWhereNull('guru_id')))
                ->first();

            if ($sudah) {
                throw ValidationException::withMessages([
                    'kelas_id' => "Kelas ini sudah punya wali kelas ({$sudah->guru_nama}) pada tahun ajaran {$tahun->nama}.",
                ]);
            }
        }

        return $data;
    }
}
