@extends('layouts.panel')

@section('title', 'Pinjam Rapor | MAAKA')
@section('heading', 'Pinjam Rapor')
@section('subheading', 'Pinjam rapor asli maksimal ' . $maksHari . ' hari dan wajib dikembalikan tepat waktu.')

@section('content')

@unless ($siswa)
    <div class="alert error">Akun ini belum terhubung ke data siswa. Hubungi Admin.</div>
@endunless

@if ($adaTerlambat)
    <div class="alert error">Anda masih punya rapor yang terlambat dikembalikan. Kembalikan ke Admin sebelum meminjam lagi.</div>
@endif

<section class="card form-card" style="margin-bottom:18px">
    <form method="POST" action="{{ route('siswa.rapor.store') }}">
        @csrf

        <div class="form-body">
            <div class="field full">
                <label for="dokumen_id">Rapor</label>
                <select id="dokumen_id" name="dokumen_id" class="{{ $errors->has('dokumen_id') ? 'invalid' : '' }}" required>
                    <option value="">— Pilih rapor —</option>
                    @foreach ($dokumens as $d)
                        <option value="{{ $d->id }}" @selected((string) old('dokumen_id') === (string) $d->id)>{{ $d->nama_dokumen }}</option>
                    @endforeach
                </select>
                @error('dokumen_id') <div class="error">{{ $message }}</div> @enderror
                @if ($siswa && $dokumens->isEmpty())
                    <div class="hint">Belum ada rapor yang bisa dipinjam. Rapor harus berstatus Terverifikasi dan tidak sedang diajukan atau dipinjam.</div>
                @endif
            </div>

            <div class="field">
                <label for="tanggal_pinjam">Tanggal pinjam</label>
                <input id="tanggal_pinjam" type="date" name="tanggal_pinjam" min="{{ now()->toDateString() }}"
                       value="{{ old('tanggal_pinjam', now()->toDateString()) }}" class="{{ $errors->has('tanggal_pinjam') ? 'invalid' : '' }}" required>
                @error('tanggal_pinjam') <div class="error">{{ $message }}</div> @enderror
            </div>

            <div class="field">
                <label for="tanggal_kembali">Rencana tanggal kembali</label>
                <input id="tanggal_kembali" type="date" name="tanggal_kembali" min="{{ now()->toDateString() }}"
                       value="{{ old('tanggal_kembali', now()->addDays(7)->toDateString()) }}" class="{{ $errors->has('tanggal_kembali') ? 'invalid' : '' }}" required>
                @error('tanggal_kembali') <div class="error">{{ $message }}</div> @enderror
            </div>

            <div class="field full">
                <label for="alasan">Alasan peminjaman</label>
                <input id="alasan" type="text" name="alasan" value="{{ old('alasan') }}" maxlength="500"
                       placeholder="Contoh: Pendaftaran beasiswa" class="{{ $errors->has('alasan') ? 'invalid' : '' }}" required>
                @error('alasan') <div class="error">{{ $message }}</div> @enderror
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary" @disabled(! $siswa || $dokumens->isEmpty() || $adaTerlambat)>Kirim Pengajuan</button>
        </div>
    </form>
</section>

<section class="card">
    <div class="card-head">
        <div><h2>Peminjaman Saya</h2><p>Pantau status dan batas pengembalian.</p></div>
    </div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Rapor</th><th>Pinjam</th><th>Kembali</th><th>Alasan</th><th>Status</th><th></th></tr></thead>
            <tbody>
                @forelse ($peminjamans as $p)
                    <tr>
                        <td>{{ $p->dokumen?->nama_dokumen ?? '-' }}</td>
                        <td>{{ $p->tanggal_pinjam->format('d M Y') }}</td>
                        <td>{{ $p->tanggal_kembali->format('d M Y') }}
                            @if ($p->dikembalikan_pada)<small>Dikembalikan {{ $p->dikembalikan_pada->format('d M Y') }}</small>@endif
                        </td>
                        <td>{{ $p->alasan }}@if ($p->catatan)<small>Catatan: {{ $p->catatan }}</small>@endif</td>
                        <td>
                            @if ($p->terlambat())
                                @include('admin.partials.badge', ['status' => 'Terlambat'])
                            @else
                                @include('admin.partials.badge', ['status' => $p->status])
                            @endif
                        </td>
                        <td class="actions">
                            @if ($p->status === \App\Models\PeminjamanRapor::STATUS_MENUNGGU)
                                <form method="POST" action="{{ route('siswa.rapor.batal', $p) }}" data-confirm="Batalkan pengajuan peminjaman rapor ini?">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="btn btn-light btn-sm">Batalkan</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="empty">Belum ada peminjaman.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @include('admin.partials.pager', ['paginator' => $peminjamans, 'noun' => 'peminjaman'])
</section>

@endsection
