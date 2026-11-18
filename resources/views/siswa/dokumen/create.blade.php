@extends('layouts.panel')

@section('title', 'Unggah Dokumen | MAAKA')
@section('heading', 'Unggah Dokumen')
@section('subheading', 'Kirim dokumen Anda untuk diverifikasi Admin.')

@section('content')

@unless ($siswa)
    <div class="alert error">Akun ini belum terhubung ke data siswa. Hubungi Admin.</div>
@endunless

<section class="card form-card">
    <form method="POST" action="{{ route('siswa.dokumen.store') }}" enctype="multipart/form-data">
        @csrf
        <div class="form-body">
            <div class="field full">
                <label for="nama_dokumen">Nama dokumen</label>
                <input id="nama_dokumen" type="text" name="nama_dokumen" value="{{ old('nama_dokumen') }}" maxlength="255"
                       placeholder="Contoh: Rapor Semester 1" class="{{ $errors->has('nama_dokumen') ? 'invalid' : '' }}" required>
                @error('nama_dokumen') <div class="error">{{ $message }}</div> @enderror
            </div>

            <div class="field">
                <label for="kategori">Kategori</label>
                <select id="kategori" name="kategori" class="{{ $errors->has('kategori') ? 'invalid' : '' }}" required>
                    <option value="">— Pilih kategori —</option>
                    @foreach (\App\Models\Dokumen::KATEGORI as $k)
                        <option value="{{ $k }}" @selected(old('kategori', $kategori) === $k)>{{ $k }}</option>
                    @endforeach
                </select>
                @error('kategori') <div class="error">{{ $message }}</div> @enderror
            </div>

            <div class="field">
                <label for="berkas">Berkas</label>
                <input id="berkas" type="file" name="berkas" accept=".pdf,.jpg,.jpeg,.png" class="{{ $errors->has('berkas') ? 'invalid' : '' }}" required>
                @error('berkas') <div class="error">{{ $message }}</div> @enderror
                <div class="hint">PDF, JPG, atau PNG, maksimal 5 MB.</div>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary" @disabled(! $siswa)>Kirim</button>
            <a href="{{ route('siswa.dokumen.index') }}" class="btn btn-light">Batal</a>
        </div>
    </form>
</section>

@endsection
