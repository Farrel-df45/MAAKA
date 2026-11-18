@extends('layouts.panel')

@php $label = $jenis === 'siswa' ? 'Siswa' : 'Guru'; @endphp

@section('title', "Impor {$label} | MAAKA")
@section('heading', "Impor Data {$label}")
@section('subheading', 'Tambahkan banyak data sekaligus dari berkas CSV (bisa disimpan dari Excel).')

@section('content')

@if (session('gagal') && count(session('gagal')))
    <div class="alert error" style="margin-bottom:16px">
        <strong>Baris yang dilewati:</strong>
        <ul style="margin:8px 0 0 18px">
            @foreach (array_slice(session('gagal'), 0, 50) as $g)
                <li>{{ $g }}</li>
            @endforeach
        </ul>
        @if (count(session('gagal')) > 50)
            <small>... dan {{ count(session('gagal')) - 50 }} baris lainnya.</small>
        @endif
    </div>
@endif

<section class="card form-card">
    <form method="POST" action="{{ route('admin.impor.store', $jenis) }}" enctype="multipart/form-data">
        @csrf
        <div class="form-body">
            <div class="field">
                <label for="berkas">Berkas CSV</label>
                <input type="file" id="berkas" name="berkas" accept=".csv,.txt" required>
                @error('berkas') <span class="error">{{ $message }}</span> @enderror
                <p class="hint">Maksimal 1000 baris dan 2 MB. Pemisah kolom boleh koma atau titik koma.</p>
            </div>

            <div class="field">
                <label>Kolom yang dibaca (baris pertama sebagai header)</label>
                <p class="hint"><code>{{ implode(' ; ', $kolom) }}</code></p>
                <ul class="hint" style="margin:6px 0 0 18px">
                    @if ($jenis === 'siswa')
                        <li>Kolom <code>kelas</code> harus sama dengan nama kelas yang sudah dibuat di menu Kelas.</li>
                        <li>Siswa ditempatkan di tahun ajaran yang sedang aktif.</li>
                        <li>Akun login hanya dibuat bila <code>password</code> (min. 8 karakter) dan <code>email</code> diisi.</li>
                    @else
                        <li><code>nip</code>, <code>nama</code>, dan <code>email</code> wajib diisi.</li>
                        <li>Akun login hanya dibuat bila <code>password</code> (min. 8 karakter) diisi.</li>
                        <li>Wali kelas diatur terpisah lewat menu Data Guru atau Kelas.</li>
                    @endif
                    <li>Baris yang bermasalah dilewati dan dilaporkan; baris yang benar tetap diimpor.</li>
                </ul>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Impor</button>
            <a href="{{ route('admin.impor.contoh', $jenis) }}" class="btn btn-light">Unduh contoh CSV</a>
            <a href="{{ route($jenis === 'siswa' ? 'admin.siswa.index' : 'admin.guru.index') }}" class="btn btn-light">Kembali</a>
        </div>
    </form>
</section>

@endsection
