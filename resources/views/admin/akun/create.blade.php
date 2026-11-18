@extends('layouts.panel')

@section('title', 'Tambah Akun | MAAKA')
@section('heading', 'Tambah Akun')
@section('subheading', 'Buat akun login baru untuk Administrator, Guru, atau Siswa.')

@section('content')

<section class="card form-card">
    <form method="POST" action="{{ route('admin.akun.store') }}">
        @csrf

        <div class="form-body">
            <div class="field full">
                <label for="role">Role</label>
                <select id="role" name="role" class="{{ $errors->has('role') ? 'invalid' : '' }}" required>
                    <option value="">— Pilih role —</option>
                    <option value="admin" @selected(old('role') === 'admin')>Administrator</option>
                    <option value="guru" @selected(old('role') === 'guru')>Guru</option>
                    <option value="siswa" @selected(old('role') === 'siswa')>Siswa</option>
                </select>
                @error('role') <div class="error">{{ $message }}</div> @enderror
            </div>

            <div class="field full" data-role="siswa" hidden>
                <label for="siswa_id">Siswa</label>
                <select id="siswa_id" name="siswa_id" class="{{ $errors->has('siswa_id') ? 'invalid' : '' }}">
                    <option value="">— Pilih siswa —</option>
                    @foreach ($siswas as $siswa)
                        <option value="{{ $siswa->id }}" @selected((string) old('siswa_id') === (string) $siswa->id)>
                            {{ $siswa->nama }} ({{ $siswa->nis }} · {{ $siswa->kelas }})
                        </option>
                    @endforeach
                </select>
                @error('siswa_id') <div class="error">{{ $message }}</div> @enderror
                <div class="hint">Hanya siswa yang belum punya akun. {{ $siswas->isEmpty() ? 'Semua siswa sudah memiliki akun.' : '' }}</div>
            </div>

            <div class="field full" data-role="guru" hidden>
                <label for="guru_id">Guru</label>
                <select id="guru_id" name="guru_id" class="{{ $errors->has('guru_id') ? 'invalid' : '' }}">
                    <option value="">— Pilih guru —</option>
                    @foreach ($gurus as $guru)
                        <option value="{{ $guru->id }}" @selected((string) old('guru_id') === (string) $guru->id)>
                            {{ $guru->nama }}{{ $guru->nip ? ' (' . $guru->nip . ')' : '' }}
                        </option>
                    @endforeach
                </select>
                @error('guru_id') <div class="error">{{ $message }}</div> @enderror
                <div class="hint">Hanya guru yang belum punya akun. {{ $gurus->isEmpty() ? 'Semua guru sudah memiliki akun.' : '' }}</div>
            </div>

            <div class="field full" data-role="admin" hidden>
                <label for="name">Nama</label>
                <input id="name" type="text" name="name" value="{{ old('name') }}"
                       class="{{ $errors->has('name') ? 'invalid' : '' }}">
                @error('name') <div class="error">{{ $message }}</div> @enderror
            </div>

            <div class="field full">
                <label for="email">Email (dipakai untuk login)</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}"
                       class="{{ $errors->has('email') ? 'invalid' : '' }}" required>
                @error('email') <div class="error">{{ $message }}</div> @enderror
            </div>

            <div class="field">
                <label for="password">Kata Sandi</label>
                <input id="password" type="password" name="password" autocomplete="new-password"
                       class="{{ $errors->has('password') ? 'invalid' : '' }}" required>
                @error('password') <div class="error">{{ $message }}</div> @enderror
                <div class="hint">Minimal 8 karakter.</div>
            </div>

            <div class="field">
                <label for="password_confirmation">Ulangi Kata Sandi</label>
                <input id="password_confirmation" type="password" name="password_confirmation" autocomplete="new-password" required>
            </div>
        </div>

        <div class="form-actions">
            <a href="{{ route('admin.akun.index') }}" class="btn btn-light">Batal</a>
            <button type="submit" class="btn btn-primary">Simpan Akun</button>
        </div>
    </form>
</section>

@endsection

@push('scripts')
<script>
    // Tampilkan isian sesuai role yang dipilih
    (function () {
        var role = document.getElementById('role');
        var blocks = document.querySelectorAll('[data-role]');

        function sync() {
            blocks.forEach(function (el) {
                var show = el.dataset.role === role.value;
                el.hidden = !show;
                el.querySelectorAll('input, select').forEach(function (f) { f.disabled = !show; });
            });
        }

        role.addEventListener('change', sync);
        sync();
    })();
</script>
@endpush
