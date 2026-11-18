@extends('layouts.panel')

@section('title', 'Detail Peminjaman Rapor | MAAKA')
@section('heading', 'Detail Peminjaman Rapor')
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

<a href="{{ route('admin.peminjaman-rapor.index') }}" class="back-link">‹ Kembali ke daftar</a>

<div class="show-grid">
    <section class="card">
        <div class="card-head">
            <div><h2>Informasi Peminjaman</h2><p>Diajukan {{ $peminjaman->created_at?->format('d M Y, H:i') }}</p></div>
            <div>
                @include('admin.partials.badge', ['status' => $peminjaman->status])
                @if ($peminjaman->terlambat()) @include('admin.partials.badge', ['status' => 'Terlambat']) @endif
            </div>
        </div>
        <dl class="detail-list">
            <div class="detail-row"><dt>Siswa</dt><dd>{{ $peminjaman->siswa?->nama ?? '-' }}<small style="display:block;color:var(--muted)">{{ $peminjaman->siswa?->nis }} · {{ $peminjaman->siswa?->kelas }}</small></dd></div>
            <div class="detail-row"><dt>Rapor</dt><dd>
                {{ $peminjaman->dokumen?->nama_dokumen ?? '-' }}
                @if ($peminjaman->dokumen)<a href="{{ route('admin.dokumen.berkas', $peminjaman->dokumen) }}" target="_blank" style="display:block;color:var(--blue);font-size:13px">Lihat berkas</a>@endif
            </dd></div>
            <div class="detail-row"><dt>Tanggal pinjam</dt><dd>{{ $peminjaman->tanggal_pinjam->format('d M Y') }}</dd></div>
            <div class="detail-row"><dt>Rencana kembali</dt><dd>{{ $peminjaman->tanggal_kembali->format('d M Y') }}</dd></div>
            <div class="detail-row"><dt>Alasan</dt><dd>{{ $peminjaman->alasan }}</dd></div>
            @if ($peminjaman->dipinjam_pada)<div class="detail-row"><dt>Diserahkan</dt><dd>{{ $peminjaman->dipinjam_pada->format('d M Y, H:i') }}</dd></div>@endif
            @if ($peminjaman->dikembalikan_pada)<div class="detail-row"><dt>Dikembalikan</dt><dd>{{ $peminjaman->dikembalikan_pada->format('d M Y, H:i') }}</dd></div>@endif
            @if ($peminjaman->catatan)<div class="detail-row"><dt>Catatan admin</dt><dd>{{ $peminjaman->catatan }}</dd></div>@endif
        </dl>
    </section>

    <section class="card">
        <div class="card-head"><div><h2>Perbarui Status</h2><p>Catatan wajib diisi jika ditolak.</p></div></div>

        @if ($peminjaman->statusBerikutnya() === [])
            <div class="empty">Status {{ $peminjaman->status }} sudah final dan tidak dapat diubah.</div>
        @else
            <form method="POST" action="{{ route('admin.peminjaman-rapor.status', $peminjaman) }}" class="status-form">
                @csrf
                @method('PATCH')
                <div class="field">
                    <label for="status">Ubah menjadi</label>
                    <select id="status" name="status" class="{{ $errors->has('status') ? 'invalid' : '' }}" required>
                        @foreach ($peminjaman->statusBerikutnya() as $s)
                            <option value="{{ $s }}" @selected(old('status') === $s)>{{ $s }}</option>
                        @endforeach
                    </select>
                    @error('status') <div class="error">{{ $message }}</div> @enderror
                    <div class="hint">Disetujui → Dipinjam (rapor diserahkan) → Dikembalikan.</div>
                </div>
                <div class="field">
                    <label for="catatan">Catatan</label>
                    <textarea id="catatan" name="catatan" class="{{ $errors->has('catatan') ? 'invalid' : '' }}"
                              placeholder="Alasan penolakan atau info pengambilan...">{{ old('catatan', $peminjaman->catatan) }}</textarea>
                    @error('catatan') <div class="error">{{ $message }}</div> @enderror
                </div>
                <button type="submit" class="btn btn-primary">Simpan Status</button>
            </form>
        @endif
    </section>
</div>

@endsection
