@extends('layouts.panel')

@php
    $isEdit = $siswa->exists;
@endphp

@section('title', ($isEdit ? 'Ubah Siswa' : 'Tambah Siswa') . ' | MAAKA')
@section('heading', $isEdit ? 'Ubah Data Siswa' : 'Tambah Siswa')
@section('subheading', $isEdit ? 'Perbarui data siswa ' . $siswa->nama . '.' : 'Isi data siswa baru dengan lengkap.')

@push('styles')
<style>
    .btn {
        display: inline-flex; align-items: center; justify-content: center; gap: 6px;
        padding: 10px 18px;
        border: 1px solid transparent;
        border-radius: 9px;
        font-size: 14px; font-weight: 600; font-family: inherit;
        cursor: pointer;
    }
    .btn-primary { background: var(--blue); color: #fff; }
    .btn-primary:hover { background: #1e40af; }
    .btn-light { background: #fff; color: var(--text); border-color: var(--line); }
    .btn-light:hover { background: #f1f5fb; }

    .form-card { max-width: 760px; }
    .form-body { padding: 24px 22px 8px; display: grid; grid-template-columns: 1fr 1fr; gap: 18px 20px; }
    .field.full { grid-column: 1 / -1; }
    .field label { display: block; margin-bottom: 7px; font-size: 14px; font-weight: 600; }
    .field input {
        width: 100%;
        padding: 11px 14px;
        border: 1px solid #cbd5e1;
        border-radius: 9px;
        font-size: 15px; font-family: inherit;
        background: #fff;
    }
    .field input:focus { outline: none; border-color: var(--accent); box-shadow: 0 0 0 3px rgba(59,130,246,.15); }
    .field input.invalid { border-color: #f87171; }
    .field .hint  { margin-top: 5px; font-size: 12px; color: var(--muted); }
    .field .error { margin-top: 5px; font-size: 13px; color: var(--red); }
    .form-actions {
        display: flex; justify-content: flex-end; gap: 10px;
        padding: 18px 22px 22px;
    }
    @media (max-width: 640px) { .form-body { grid-template-columns: minmax(0, 1fr); } }
</style>
@endpush

@section('content')

<section class="card form-card">
    <form method="POST"
          action="{{ $isEdit ? route('admin.siswa.update', $siswa) : route('admin.siswa.store') }}">
        @csrf
        @if ($isEdit)
            @method('PUT')
        @endif

        <div class="form-body">
            <div class="field">
                <label for="nis">NIS</label>
                <input id="nis" type="text" name="nis" value="{{ old('nis', $siswa->nis) }}"
                       class="{{ $errors->has('nis') ? 'invalid' : '' }}" required autofocus>
                @error('nis') <div class="error">{{ $message }}</div> @enderror
            </div>

            <div class="field">
                <label for="nama">Nama Lengkap</label>
                <input id="nama" type="text" name="nama" value="{{ old('nama', $siswa->nama) }}"
                       class="{{ $errors->has('nama') ? 'invalid' : '' }}" required>
                @error('nama') <div class="error">{{ $message }}</div> @enderror
            </div>

            <div class="field">
                <label for="kelas_id">Kelas {{ $tahun ? '(' . $tahun->nama . ')' : '' }}</label>
                <select id="kelas_id" name="kelas_id" class="{{ $errors->has('kelas_id') ? 'invalid' : '' }}"
                        @required(! $isEdit || $kelasAktifId)>
                    <option value="">— {{ $isEdit && ! $kelasAktifId ? 'Tanpa kelas (alumni)' : 'Pilih kelas' }} —</option>
                    @foreach ($daftarKelas as $k)
                        <option value="{{ $k->id }}" @selected((string) old('kelas_id', $kelasAktifId) === (string) $k->id)>{{ $k->nama }}</option>
                    @endforeach
                </select>
                @error('kelas_id') <div class="error">{{ $message }}</div> @enderror
                @if ($daftarKelas->isEmpty())
                    <div class="hint">Belum ada kelas. <a href="{{ route('admin.kelas.create') }}" style="color:var(--blue)">Buat kelas dulu</a>.</div>
                @else
                    <div class="hint">Akun siswa otomatis masuk ke kelas ini; riwayat kelas sebelumnya tidak berubah.</div>
                @endif
            </div>

            <div class="field">
                <label for="jurusan">Jurusan</label>
                <input id="jurusan" type="text" name="jurusan" value="{{ old('jurusan', $siswa->jurusan) }}"
                       placeholder="Contoh: Rekayasa Perangkat Lunak"
                       class="{{ $errors->has('jurusan') ? 'invalid' : '' }}" required>
                @error('jurusan') <div class="error">{{ $message }}</div> @enderror
            </div>

            <div class="field full">
                <label for="alamat">Alamat <span style="font-weight:400;color:var(--muted)">(opsional)</span></label>
                <input id="alamat" type="text" name="alamat" value="{{ old('alamat', $siswa->alamat) }}"
                       class="{{ $errors->has('alamat') ? 'invalid' : '' }}">
                @error('alamat') <div class="error">{{ $message }}</div> @enderror
            </div>

            <div class="field full" style="border-top:1px solid var(--line);padding-top:18px">
                <strong style="font-size:15px">Akun Login Siswa</strong>
                <div class="hint" style="margin-top:4px">
                    @if ($siswa->user)
                        Siswa ini sudah punya akun. Login memakai NIS atau email di bawah. Isi kata sandi hanya jika ingin menggantinya.
                    @else
                        Isi email dan kata sandi untuk membuat akun. Siswa login memakai <b>NIS</b> (atau email) dan kata sandi ini. Kosongkan jika akun belum diperlukan.
                    @endif
                </div>
            </div>

            <div class="field full">
                <label for="email">Email Akun</label>
                <input id="email" type="email" name="email" value="{{ old('email', $siswa->user?->email) }}"
                       class="{{ $errors->has('email') ? 'invalid' : '' }}">
                @error('email') <div class="error">{{ $message }}</div> @enderror
            </div>

            <div class="field">
                <label for="password">{{ $siswa->user ? 'Kata Sandi Baru' : 'Kata Sandi' }}</label>
                <input id="password" type="password" name="password" autocomplete="new-password"
                       class="{{ $errors->has('password') ? 'invalid' : '' }}">
                @error('password') <div class="error">{{ $message }}</div> @enderror
                <div class="hint">Minimal 8 karakter.</div>
            </div>

            <div class="field">
                <label for="password_confirmation">Ulangi Kata Sandi</label>
                <input id="password_confirmation" type="password" name="password_confirmation" autocomplete="new-password">
            </div>
        </div>

        <div class="form-actions">
            <a href="{{ route('admin.siswa.index') }}" class="btn btn-light">Batal</a>
            <button type="submit" class="btn btn-primary">{{ $isEdit ? 'Simpan Perubahan' : 'Simpan Siswa' }}</button>
        </div>
    </form>
</section>

@endsection