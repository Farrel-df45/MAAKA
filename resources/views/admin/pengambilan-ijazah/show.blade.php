@extends('layouts.panel')

@section('title', 'Detail Pengambilan Ijazah | MAAKA')
@section('heading', 'Detail Pengambilan Ijazah')
@section('subheading', 'Tinjau pengajuan dan perbarui statusnya.')

@push('styles')
<style>
    .show-grid { display: grid; grid-template-columns: minmax(0, 3fr) minmax(0, 2fr); gap: 18px; align-items: start; }
    @media (max-width: 900px) { .show-grid { grid-template-columns: minmax(0, 1fr); } }
    .status-form { padding: 20px 22px 22px; display: grid; gap: 16px; }
    .back-link { display: inline-block; margin-bottom: 16px; font-size: 14px; color: var(--blue); }
</style>
@endpush

@section('content')

<a href="{{ route('admin.pengambilan-ijazah.index') }}" class="back-link">‹ Kembali ke daftar</a>

<div class="show-grid">
    <section class="card">
        <div class="card-head">
            <div><h2>Informasi Pengajuan</h2><p>Diajukan {{ $pengambilan->created_at?->format('d M Y, H:i') }}</p></div>
            @include('admin.partials.badge', ['status' => $pengambilan->status])
        </div>
        <dl class="detail-list">
            <div class="detail-row"><dt>Siswa</dt><dd>{{ $pengambilan->siswa?->nama ?? '-' }}<small style="display:block;color:var(--muted)">NIS {{ $pengambilan->siswa?->nis }} · {{ $pengambilan->siswa?->jurusan }}</small></dd></div>
            <div class="detail-row"><dt>Tanggal pengambilan</dt><dd>{{ $pengambilan->tanggal_pengambilan->format('d M Y') }}</dd></div>
            <div class="detail-row"><dt>Diambil oleh</dt><dd>{{ $pengambilan->pengambil }}
                @if ($pengambilan->nama_wakil)<small style="display:block;color:var(--muted)">{{ $pengambilan->nama_wakil }} ({{ $pengambilan->hubungan_wakil }}) — pastikan membawa surat kuasa dan identitas</small>@endif
            </dd></div>
            @if ($pengambilan->keterangan)<div class="detail-row"><dt>Keterangan</dt><dd>{{ $pengambilan->keterangan }}</dd></div>@endif
            @if ($pengambilan->diambil_pada)<div class="detail-row"><dt>Diambil pada</dt><dd>{{ $pengambilan->diambil_pada->format('d M Y, H:i') }}</dd></div>@endif
            @if ($pengambilan->catatan)<div class="detail-row"><dt>Catatan admin</dt><dd>{{ $pengambilan->catatan }}</dd></div>@endif
        </dl>
    </section>

    <section class="card">
        <div class="card-head"><div><h2>Perbarui Status</h2><p>Catatan wajib diisi jika ditolak.</p></div></div>

        @if ($pengambilan->statusBerikutnya() === [])
            <div class="empty">Status {{ $pengambilan->status }} sudah final dan tidak dapat diubah.</div>
        @else
            <form method="POST" action="{{ route('admin.pengambilan-ijazah.status', $pengambilan) }}" class="status-form">
                @csrf
                @method('PATCH')
                <div class="field">
                    <label for="status">Ubah menjadi</label>
                    <select id="status" name="status" class="{{ $errors->has('status') ? 'invalid' : '' }}" required>
                        @foreach ($pengambilan->statusBerikutnya() as $s)
                            <option value="{{ $s }}" @selected(old('status') === $s)>{{ $s }}</option>
                        @endforeach
                    </select>
                    @error('status') <div class="error">{{ $message }}</div> @enderror
                    <div class="hint">Disetujui → Sudah Diambil (setelah ijazah diserahkan).</div>
                </div>
                <div class="field">
                    <label for="catatan">Catatan</label>
                    <textarea id="catatan" name="catatan" class="{{ $errors->has('catatan') ? 'invalid' : '' }}"
                              placeholder="Alasan penolakan atau jam pengambilan...">{{ old('catatan', $pengambilan->catatan) }}</textarea>
                    @error('catatan') <div class="error">{{ $message }}</div> @enderror
                </div>
                <button type="submit" class="btn btn-primary">Simpan Status</button>
            </form>
        @endif
    </section>
</div>

@endsection
