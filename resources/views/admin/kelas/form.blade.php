@extends('layouts.panel')

@php
    $isEdit = $kelas->exists;
@endphp

@section('title', ($isEdit ? 'Ubah Kelas' : 'Tambah Kelas') . ' | MAAKA')
@section('heading', $isEdit ? 'Ubah Kelas' : 'Tambah Kelas')
@section('subheading', 'Wali kelas berlaku untuk tahun ajaran ' . ($tahun?->nama ?? '(belum ada)') . '.')

@section('content')

<section class="card form-card">
    <form method="POST" action="{{ $isEdit ? route('admin.kelas.update', $kelas) : route('admin.kelas.store') }}">
        @csrf
        @if ($isEdit)
            @method('PUT')
        @endif

        <div class="form-body">
            <div class="field">
                <label for="nama">Nama Kelas</label>
                <input id="nama" type="text" name="nama" value="{{ old('nama', $kelas->nama) }}"
                       placeholder="Contoh: X RPL A" class="{{ $errors->has('nama') ? 'invalid' : '' }}" required autofocus>
                @error('nama') <div class="error">{{ $message }}</div> @enderror
            </div>

            <div class="field">
                <label for="tingkat">Tingkat</label>
                <select id="tingkat" name="tingkat" class="{{ $errors->has('tingkat') ? 'invalid' : '' }}">
                    <option value="">— Pilih tingkat —</option>
                    @foreach (\App\Models\Kelas::TINGKAT as $t)
                        <option value="{{ $t }}" @selected(old('tingkat', $kelas->tingkat) === $t)>{{ $t }}</option>
                    @endforeach
                </select>
                @error('tingkat') <div class="error">{{ $message }}</div> @enderror
                <div class="hint">Dipakai untuk saran kelas tujuan saat naik kelas.</div>
            </div>

            <div class="field full">
                <label for="jurusan">Jurusan <span style="font-weight:400;color:var(--muted)">(opsional)</span></label>
                <input id="jurusan" type="text" name="jurusan" value="{{ old('jurusan', $kelas->jurusan) }}"
                       class="{{ $errors->has('jurusan') ? 'invalid' : '' }}">
                @error('jurusan') <div class="error">{{ $message }}</div> @enderror
            </div>

            <div class="field full">
                <label for="guru_id">Wali Kelas</label>
                <select id="guru_id" name="guru_id" class="{{ $errors->has('guru_id') ? 'invalid' : '' }}">
                    <option value="">— Belum ada wali kelas —</option>
                    @foreach ($gurus as $g)
                        <option value="{{ $g->id }}" @selected((string) old('guru_id', $guruAktifId) === (string) $g->id)>
                            {{ $g->nama }}{{ $g->nip ? ' (' . $g->nip . ')' : '' }}
                        </option>
                    @endforeach
                </select>
                @error('guru_id') <div class="error">{{ $message }}</div> @enderror
                <div class="hint">Guru ini hanya dapat mengakses siswa dan dokumen kelas ini.</div>
            </div>
        </div>

        <div class="form-actions">
            <a href="{{ route('admin.kelas.index') }}" class="btn btn-light">Batal</a>
            <button type="submit" class="btn btn-primary">{{ $isEdit ? 'Simpan Perubahan' : 'Simpan Kelas' }}</button>
        </div>
    </form>
</section>

@endsection
