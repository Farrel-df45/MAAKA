@extends('layouts.panel')

@php
    $isEdit = $dokumen->exists;
@endphp

@section('title', ($isEdit ? 'Ubah Dokumen' : 'Unggah Dokumen') . ' | MAAKA')
@section('heading', $isEdit ? 'Ubah Dokumen' : 'Unggah Dokumen')
@section('subheading', $isEdit ? 'Perubahan akan membuat dokumen diverifikasi ulang oleh Admin.' : 'Dokumen akan diperiksa Admin sebelum berstatus terverifikasi.')

@section('content')

<section class="card form-card">
    <form method="POST" enctype="multipart/form-data"
          action="{{ $isEdit ? route('guru.dokumen.update', $dokumen) : route('guru.dokumen.store') }}">
        @csrf
        @if ($isEdit)
            @method('PUT')
        @endif

        <div class="form-body">
            <div class="field full">
                <label for="siswa_id">Siswa</label>
                <select id="siswa_id" name="siswa_id" class="{{ $errors->has('siswa_id') ? 'invalid' : '' }}" required>
                    <option value="">— Pilih siswa —</option>
                    @foreach ($siswas as $siswa)
                        <option value="{{ $siswa->id }}" @selected((string) old('siswa_id', $dokumen->siswa_id) === (string) $siswa->id)>
                            {{ $siswa->nama }} ({{ $siswa->nis }} · {{ $siswa->kelas }})
                        </option>
                    @endforeach
                </select>
                @error('siswa_id') <div class="error">{{ $message }}</div> @enderror
            </div>

            <div class="field">
                <label for="nama_dokumen">Nama Dokumen</label>
                <input id="nama_dokumen" type="text" name="nama_dokumen" value="{{ old('nama_dokumen', $dokumen->nama_dokumen) }}"
                       placeholder="Contoh: Rapor Semester 6"
                       class="{{ $errors->has('nama_dokumen') ? 'invalid' : '' }}" required>
                @error('nama_dokumen') <div class="error">{{ $message }}</div> @enderror
            </div>

            <div class="field">
                <label for="kategori">Kategori</label>
                <select id="kategori" name="kategori" class="{{ $errors->has('kategori') ? 'invalid' : '' }}" required>
                    <option value="">— Pilih kategori —</option>
                    @foreach (\App\Models\Dokumen::KATEGORI as $k)
                        <option value="{{ $k }}" @selected(old('kategori', $dokumen->kategori) === $k)>{{ $k }}</option>
                    @endforeach
                </select>
                @error('kategori') <div class="error">{{ $message }}</div> @enderror
            </div>

            <div class="field full">
                <label for="berkas">Berkas {{ $isEdit ? '(kosongkan jika tidak diganti)' : '' }}</label>
                <input id="berkas" type="file" name="berkas" accept=".pdf,.jpg,.jpeg,.png"
                       class="{{ $errors->has('berkas') ? 'invalid' : '' }}" {{ $isEdit ? '' : 'required' }}>
                @error('berkas') <div class="error">{{ $message }}</div> @enderror
                <div class="hint">
                    PDF, JPG, atau PNG, maksimal 5 MB.
                    @if ($isEdit)
                        <a href="{{ route('guru.dokumen.berkas', $dokumen) }}" target="_blank" style="color:var(--blue)">Lihat berkas saat ini</a>
                    @endif
                </div>
            </div>
        </div>

        <div class="form-actions">
            <a href="{{ route('guru.dokumen.index') }}" class="btn btn-light">Batal</a>
            <button type="submit" class="btn btn-primary">{{ $isEdit ? 'Simpan Perubahan' : 'Unggah Dokumen' }}</button>
        </div>
    </form>
</section>

@endsection
