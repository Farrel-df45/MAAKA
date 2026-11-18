@extends('layouts.panel')

@php
    $isEdit = $guru->exists;
@endphp

@section('title', ($isEdit ? 'Ubah Guru' : 'Tambah Guru') . ' | MAAKA')
@section('heading', $isEdit ? 'Ubah Data Guru' : 'Tambah Guru')
@section('subheading', $isEdit ? 'Perbarui data guru ' . $guru->nama . '.' : 'Isi data guru baru dengan lengkap.')

@section('content')

<section class="card form-card">
    <form method="POST" action="{{ $isEdit ? route('admin.guru.update', $guru) : route('admin.guru.store') }}">
        @csrf
        @if ($isEdit)
            @method('PUT')
        @endif

        <div class="form-body">
            <div class="field">
                <label for="nip">NIP <span style="font-weight:400;color:var(--muted)">(wajib jika membuat akun)</span></label>
                <input id="nip" type="text" name="nip" value="{{ old('nip', $guru->nip) }}"
                       class="{{ $errors->has('nip') ? 'invalid' : '' }}" autofocus>
                @error('nip') <div class="error">{{ $message }}</div> @enderror
            </div>

            <div class="field">
                <label for="nama">Nama Lengkap</label>
                <input id="nama" type="text" name="nama" value="{{ old('nama', $guru->nama) }}"
                       class="{{ $errors->has('nama') ? 'invalid' : '' }}" required>
                @error('nama') <div class="error">{{ $message }}</div> @enderror
            </div>

            <div class="field">
                <label for="email">Email</label>
                <input id="email" type="email" name="email" value="{{ old('email', $guru->email) }}"
                       class="{{ $errors->has('email') ? 'invalid' : '' }}" required>
                @error('email') <div class="error">{{ $message }}</div> @enderror
            </div>

            <div class="field">
                <label for="nomor_telepon">Nomor Telepon <span style="font-weight:400;color:var(--muted)">(opsional)</span></label>
                <input id="nomor_telepon" type="text" name="nomor_telepon" value="{{ old('nomor_telepon', $guru->nomor_telepon) }}"
                       class="{{ $errors->has('nomor_telepon') ? 'invalid' : '' }}">
                @error('nomor_telepon') <div class="error">{{ $message }}</div> @enderror
            </div>

            <div class="field full">
                <label for="kelas_id">Wali Kelas {{ $tahun ? '(' . $tahun->nama . ')' : '' }}</label>
                <select id="kelas_id" name="kelas_id" class="{{ $errors->has('kelas_id') ? 'invalid' : '' }}">
                    <option value="">— Belum menjadi wali kelas —</option>
                    @foreach ($daftarKelas as $k)
                        <option value="{{ $k->id }}" @selected((string) old('kelas_id', $kelasAktifId) === (string) $k->id)>
                            {{ $k->nama }}{{ $k->waliAktif && $k->waliAktif->guru_id !== $guru->id ? ' — wali: ' . $k->waliAktif->guru_nama : '' }}
                        </option>
                    @endforeach
                </select>
                @error('kelas_id') <div class="error">{{ $message }}</div> @enderror
                <div class="hint">Guru hanya dapat mengakses siswa dan dokumen kelas ini selama tahun ajaran berjalan.</div>
            </div>

            <div class="field full">
                <label for="jabatan">Jabatan</label>
                <input id="jabatan" type="text" name="jabatan" value="{{ old('jabatan', $guru->jabatan) }}"
                       class="{{ $errors->has('jabatan') ? 'invalid' : '' }}" required>
                @error('jabatan') <div class="error">{{ $message }}</div> @enderror
            </div>

            <div class="field full" style="border-top:1px solid var(--line);padding-top:18px">
                <strong style="font-size:15px">Akun Login Guru</strong>
                <div class="hint" style="margin-top:4px">
                    @if ($guru->user)
                        Guru ini sudah punya akun. Login memakai NIP atau email di atas. Isi kata sandi hanya jika ingin menggantinya.
                    @else
                        Isi kata sandi untuk membuat akun. Guru login di halaman Admin &amp; Guru memakai <b>NIP</b> (atau email) dan kata sandi ini. Kosongkan jika akun belum diperlukan.
                    @endif
                </div>
            </div>

            <div class="field">
                <label for="password">{{ $guru->user ? 'Kata Sandi Baru' : 'Kata Sandi' }}</label>
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
            <a href="{{ route('admin.guru.index') }}" class="btn btn-light">Batal</a>
            <button type="submit" class="btn btn-primary">{{ $isEdit ? 'Simpan Perubahan' : 'Simpan Guru' }}</button>
        </div>
    </form>
</section>

@endsection
