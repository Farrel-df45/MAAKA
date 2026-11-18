<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Guru;
use App\Models\Siswa;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AkunController extends Controller
{
    private const ROLES = ['admin', 'guru', 'siswa'];

    // Daftar akun + pencarian + filter role
    public function index(Request $request): View
    {
        $q = trim((string) $request->input('q', ''));
        $role = $request->input('role');

        $akuns = User::query()
            ->with(['siswa:id,nis,nama', 'guru:id,nip,nama'])
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($w) use ($q) {
                    $w->where('name', 'like', "%{$q}%")
                        ->orWhere('email', 'like', "%{$q}%");
                });
            })
            ->when(in_array($role, self::ROLES, true), fn ($query) => $query->where('role', $role))
            ->orderBy('role')
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();

        return view('admin.akun.index', compact('akuns', 'q', 'role'));
    }

    public function create(): View
    {
        return view('admin.akun.create', [
            // Hanya siswa/guru yang belum punya akun
            'siswas' => Siswa::doesntHave('user')->orderBy('nama')->get(['id', 'nis', 'nama', 'kelas']),
            'gurus' => Guru::doesntHave('user')->orderBy('nama')->get(['id', 'nip', 'nama']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'role' => ['required', Rule::in(self::ROLES)],
            'siswa_id' => [
                'required_if:role,siswa', 'nullable', 'exists:siswas,id',
                Rule::unique('users', 'siswa_id'),
            ],
            'guru_id' => [
                'required_if:role,guru', 'nullable', 'exists:gurus,id',
                Rule::unique('users', 'guru_id'),
            ],
            'name' => ['required_if:role,admin', 'nullable', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], $this->messages());

        // Nama siswa/guru diambil dari data induknya agar selalu konsisten
        $name = match ($data['role']) {
            'siswa' => Siswa::findOrFail($data['siswa_id'])->nama,
            'guru' => Guru::findOrFail($data['guru_id'])->nama,
            default => $data['name'],
        };

        $akun = User::create([
            'name' => $name,
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role' => $data['role'],
            'siswa_id' => $data['role'] === 'siswa' ? $data['siswa_id'] : null,
            'guru_id' => $data['role'] === 'guru' ? $data['guru_id'] : null,
        ]);

        return redirect()
            ->route('admin.akun.index')
            ->with('success', "Akun {$akun->email} berhasil dibuat.");
    }

    public function edit(User $akun): View
    {
        $akun->load(['siswa:id,nis,nama', 'guru:id,nip,nama']);

        return view('admin.akun.edit', compact('akun'));
    }

    // Ubah email / nama (admin) dan atur ulang kata sandi (opsional)
    public function update(Request $request, User $akun): RedirectResponse
    {
        $data = $request->validate([
            'name' => [$akun->role === 'admin' ? 'required' : 'nullable', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($akun->id)],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ], $this->messages());

        $update = ['email' => $data['email']];

        if ($akun->role === 'admin') {
            $update['name'] = $data['name'];
        }

        if (!empty($data['password'])) {
            $update['password'] = Hash::make($data['password']);
        }

        $akun->update($update);

        return redirect()
            ->route('admin.akun.index')
            ->with('success', "Akun {$akun->email} berhasil diperbarui.");
    }

    public function destroy(Request $request, User $akun): RedirectResponse
    {
        if ($akun->is($request->user())) {
            return back()->with('error', 'Akun yang sedang Anda gunakan tidak dapat dihapus.');
        }

        if ($akun->role === 'admin' && User::where('role', 'admin')->count() <= 1) {
            return back()->with('error', 'Akun Administrator terakhir tidak dapat dihapus.');
        }

        $email = $akun->email;
        $akun->delete();

        return redirect()
            ->route('admin.akun.index')
            ->with('success', "Akun {$email} berhasil dihapus.");
    }

    private function messages(): array
    {
        return [
            'role.required' => 'Pilih role akun.',
            'siswa_id.required_if' => 'Pilih siswa pemilik akun.',
            'siswa_id.unique' => 'Siswa ini sudah memiliki akun.',
            'guru_id.required_if' => 'Pilih guru pemilik akun.',
            'guru_id.unique' => 'Guru ini sudah memiliki akun.',
            'name.required' => 'Nama wajib diisi.',
            'name.required_if' => 'Nama wajib diisi untuk akun Administrator.',
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'email.unique' => 'Email ini sudah digunakan.',
            'password.required' => 'Kata sandi wajib diisi.',
            'password.min' => 'Kata sandi minimal 8 karakter.',
            'password.confirmed' => 'Konfirmasi kata sandi tidak sama.',
        ];
    }
}
