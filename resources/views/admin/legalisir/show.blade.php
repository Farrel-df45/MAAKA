@extends('layouts.panel')

@section('title', 'Detail Legalisir | MAAKA')
@section('heading', 'Detail Permohonan Legalisir')
@section('subheading', 'Tinjau permohonan dan perbarui statusnya.')

@push('styles')
<style>
    .show-grid { display: grid; grid-template-columns: minmax(0, 3fr) minmax(0, 2fr); gap: 18px; align-items: start; }
    @media (max-width: 900px) { .show-grid { grid-template-columns: minmax(0, 1fr); } }
    .status-form { padding: 20px 22px 22px; display: grid; gap: 16px; }
    .back-link { display: inline-block; margin-bottom: 16px; font-size: 14px; color: var(--blue); }
</style>
@endpush

@section('content')

<a href="{{ route('admin.legalisir.index') }}" class="back-link">‹ Kembali ke daftar</a>

<div class="show-grid">
    <section class="card">
        <div class="card-head">
            <div>
                <h2>Informasi Permohonan</h2>
                <p>Diajukan {{ $legalisir->created_at?->format('d M Y, H:i') }}</p>
            </div>
            @include('admin.partials.badge', ['status' => $legalisir->status])
        </div>

        <dl class="detail-list">
            <div class="detail-row">
                <dt>Siswa</dt>
                <dd>{{ $legalisir->siswa?->nama ?? '-' }} <small style="display:block;color:var(--muted)">{{ $legalisir->siswa?->nis }} · {{ $legalisir->siswa?->kelas }}</small></dd>
            </div>
            <div class="detail-row">
                <dt>Dokumen</dt>
                <dd>
                    {{ $legalisir->dokumen?->nama_dokumen ?? '-' }}
                    @if ($legalisir->dokumen)
                        <small style="display:block;color:var(--muted)">{{ $legalisir->dokumen->kategori }} · {{ $legalisir->dokumen->status }}</small>
                        <a href="{{ route('admin.dokumen.berkas', $legalisir->dokumen) }}" target="_blank" style="color:var(--blue);font-size:13px">Lihat berkas</a>
                    @endif
                </dd>
            </div>
            <div class="detail-row">
                <dt>Jumlah</dt>
                <dd>{{ $legalisir->jumlah }} lembar</dd>
            </div>
            <div class="detail-row">
                <dt>Keperluan</dt>
                <dd>{{ $legalisir->keperluan }}</dd>
            </div>
            @if ($legalisir->catatan)
                <div class="detail-row">
                    <dt>Catatan admin</dt>
                    <dd>{{ $legalisir->catatan }}</dd>
                </div>
            @endif
        </dl>
    </section>

    <section class="card">
        <div class="card-head">
            <div>
                <h2>Perbarui Status</h2>
                <p>Catatan wajib diisi jika ditolak.</p>
            </div>
        </div>

        <form method="POST" action="{{ route('admin.legalisir.status', $legalisir) }}" class="status-form">
            @csrf
            @method('PATCH')

            <div class="field">
                <label for="status">Status</label>
                <select id="status" name="status" class="{{ $errors->has('status') ? 'invalid' : '' }}" required>
                    @foreach (\App\Models\Legalisir::STATUSES as $s)
                        <option value="{{ $s }}" @selected(old('status', $legalisir->status) === $s)>{{ $s }}</option>
                    @endforeach
                </select>
                @error('status') <div class="error">{{ $message }}</div> @enderror
            </div>

            <div class="field">
                <label for="catatan">Catatan</label>
                <textarea id="catatan" name="catatan" class="{{ $errors->has('catatan') ? 'invalid' : '' }}"
                          placeholder="Alasan penolakan atau info pengambilan...">{{ old('catatan', $legalisir->catatan) }}</textarea>
                @error('catatan') <div class="error">{{ $message }}</div> @enderror
            </div>

            <button type="submit" class="btn btn-primary">Simpan Status</button>
        </form>
    </section>
</div>

@endsection
