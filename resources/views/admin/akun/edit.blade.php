@extends('layouts.panel')

@section('title', 'Ubah Akun | MAAKA')
@section('heading', 'Ubah Akun')
@section('subheading', 'Perbarui email atau atur ulang kata sandi akun ini.')

@section('content')

<section class="card form-card">
    <form method="POST" action="{{ route('admin.akun.update', $akun) }}">
        @csrf
        @method('PUT')

        <div class="form-body">
            <div class="field">
                <label>Role</label>
                <input type="text" value="{{ ['admin' => 'Administrator', 'guru' => 'Guru', 'siswa' => 'Siswa'][$akun->role] ?? $akun->role }}" readonly>
                <div class="hint">Role tidak dapat diubah.</div>
            </div>

            <div class="field">
                <label for="name">Nama</label>
                @if ($akun->role === 'admin')
                    <input id="name" type="text" name="name" value="{{ old('name', $akun->name) }}"
                           class="{{ $errors->has('name') ? 'invalid' : '' }}" required>
                    @error('name') <div class="error">{{ $message }}</div> @enderror
                @else
                    <input id="name" type="text" value="{{ $akun->name }}" readonly>
                    <div class="hint">Nama mengikuti data {{ $akun->role === 'siswa' ? 'siswa' : 'guru' }}.</div>
                @endif
            </div>

            <div class="field full">
                <label for="email">Email (dipakai untuk login)</label>
                <input id="email" type="email" name="email" value="{{ old('email', $akun->email) }}"
                       class="{{ $errors->has('email') ? 'invalid' : '' }}" required>
                @error('email') <div class="error">{{ $message }}</div> @enderror
            </div>

            <div class="field">
                <label for="password">Kata Sandi Baru</label>
                <input id="password" type="password" name="password" autocomplete="new-password"
                       class="{{ $errors->has('password') ? 'invalid' : '' }}">
                @error('password') <div class="error">{{ $message }}</div> @enderror
                <div class="hint">Kosongkan jika tidak ingin mengubah. Minimal 8 karakter.</div>
            </div>

            <div class="field">
                <label for="password_confirmation">Ulangi Kata Sandi Baru</label>
                <input id="password_confirmation" type="password" name="password_confirmation" autocomplete="new-password">
            </div>
        </div>

        <div class="form-actions">
            <a href="{{ route('admin.akun.index') }}" class="btn btn-light">Batal</a>
            <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
        </div>
    </form>
</section>

@endsection
