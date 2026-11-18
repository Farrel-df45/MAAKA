@extends('layouts.panel')

@section('title', 'Dashboard Siswa | MAAKA')
@section('heading', 'Dashboard Siswa')
@section('subheading', $siswa
    ? $siswa->nama . ' · NIS ' . $siswa->nis
    : 'Ringkasan dokumen akademik Anda.')

@push('styles')
<style>
    .stats { display: grid; grid-template-columns: repeat(4, 1fr); gap: 18px; margin-bottom: 22px; }
    .stat { padding: 20px 22px; }
    .stat span { font-size: 14px; color: var(--muted); }
    a.stat:hover { border-color: var(--accent); }
    .stat strong { display: block; margin: 10px 0 2px; font-size: 32px; line-height: 1.1; }
    .stat small { font-size: 13px; color: var(--muted); }
    .kelas-card { padding: 20px 22px; margin-bottom: 22px; display: flex; gap: 28px; flex-wrap: wrap; }
    .kelas-card div span { display: block; font-size: 13px; color: var(--muted); }
    .kelas-card div strong { font-size: 17px; }
    .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; }
    @media (max-width: 1100px) { .grid-2 { grid-template-columns: 1fr; } }
    @media (max-width: 1100px) { .stats { grid-template-columns: repeat(2, 1fr); } }
    @media (max-width: 520px)  { .stats { grid-template-columns: 1fr; } }
</style>
@endpush

@section('content')

@unless ($siswa)
    <div class="alert error">Akun ini belum terhubung ke data siswa. Hubungi Admin.</div>
@endunless

@if ($siswa)
    <section class="card kelas-card">
        <div><span>Kelas saat ini</span><strong>{{ $penempatan?->kelas->nama ?? 'Tidak ada kelas aktif' }}</strong></div>
        <div><span>Tahun ajaran</span><strong>{{ $penempatan?->tahunAjaran->nama ?? '-' }}</strong></div>
        <div><span>Wali kelas</span><strong>{{ $wali?->guru_nama ?? 'Belum ditetapkan' }}</strong></div>
    </section>
@endif

@foreach ($pengingat as $p)
    @php $sisa = (int) now()->startOfDay()->diffInDays($p->tanggal_kembali->startOfDay(), false); @endphp
    <div class="alert {{ $sisa < 0 ? 'error' : '' }}" style="{{ $sisa >= 0 ? 'background:var(--amber-bg);color:var(--amber)' : '' }}">
        <strong>Pengingat:</strong> rapor "{{ $p->dokumen?->nama_dokumen }}"
        @if ($sisa < 0) terlambat {{ abs($sisa) }} hari dikembalikan. Segera serahkan ke Admin.
        @elseif ($sisa === 0) harus dikembalikan hari ini.
        @else harus dikembalikan {{ $sisa }} hari lagi ({{ $p->tanggal_kembali->format('d M Y') }}).
        @endif
    </div>
@endforeach
C:\laragon\www\MAAKA\resources\views\siswa\dashboard.blade.php
@if ($siswa && $kelengkapan)
    <section class="card" style="margin-bottom:22px">
        <div class="card-head">
            <div>
                <h2>Kelengkapan Dokumen</h2>
                <p>{{ count($kelengkapan['terpenuhi']) }} dari {{ count($kelengkapan['wajib']) }} dokumen wajib sudah terverifikasi.</p>
            </div>
            <a href="{{ route('siswa.dokumen.create') }}" class="btn btn-primary btn-sm">+ Unggah</a>
        </div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Dokumen wajib</th><th>Status</th><th></th></tr></thead>
                <tbody>
                    @foreach ($kelengkapan['wajib'] as $k)
                        <tr>
                            <td>{{ $k }}</td>
                            <td>
                                @if (in_array($k, $kelengkapan['terpenuhi'], true)) <span class="badge success">Terverifikasi</span>
                                @elseif (in_array($k, $kelengkapan['menunggu'], true)) <span class="badge pending">Menunggu verifikasi</span>
                                @elseif (in_array($k, $kelengkapan['ditolak'], true)) <span class="badge danger">Ditolak, unggah ulang</span>
                                @else <span class="badge muted">Belum diunggah</span>
                                @endif
                            </td>
                            <td class="actions">
                                @unless (in_array($k, $kelengkapan['terpenuhi'], true) || in_array($k, $kelengkapan['menunggu'], true))
                                    <a href="{{ route('siswa.dokumen.create', ['kategori' => $k]) }}" class="btn btn-light btn-sm">Unggah {{ $k }}</a>
                                @endunless
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
@endif

@if ($pengajuanBerjalan > 0)
    <p class="hint" style="margin:0 0 14px">Ada {{ $pengajuanBerjalan }} pengajuan yang sedang berjalan. <a href="{{ route('siswa.pengajuan') }}">Lihat status</a></p>
@endif

<div class="stats">
    <a href="{{ route('siswa.dokumen.index') }}" class="card stat">
        <span>Total Dokumen</span>
        <strong>{{ $totalDokumen }}</strong>
        <small>Semua tahun ajaran</small>
    </a>
    <a href="{{ route('siswa.dokumen.index', ['status' => \App\Models\Dokumen::STATUS_TERVERIFIKASI]) }}" class="card stat">
        <span>Terverifikasi</span>
        <strong>{{ $terverifikasi }}</strong>
        <small>Dapat diajukan legalisir</small>
    </a>
    <a href="{{ route('siswa.dokumen.index', ['status' => \App\Models\Dokumen::STATUS_MENUNGGU]) }}" class="card stat">
        <span>Menunggu Verifikasi</span>
        <strong>{{ $menunggu }}</strong>
        <small>Sedang diperiksa Admin</small>
    </a>
    <a href="{{ route('siswa.legalisir.create') }}" class="card stat">
        <span>Legalisir Berjalan</span>
        <strong>{{ $legalisirBerjalan }}</strong>
        <small>Menunggu / diproses</small>
    </a>
</div>

<div class="grid-2">
    <section class="card">
        <div class="card-head">
            <div><h2>Dokumen Terbaru</h2><p>5 dokumen terakhir Anda.</p></div>
            <a href="{{ route('siswa.dokumen.index') }}" class="btn btn-light btn-sm">Lihat semua</a>
        </div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Dokumen</th><th>Kelas</th><th>Status</th></tr></thead>
                <tbody>
                    @forelse ($dokumenTerbaru as $d)
                        <tr>
                            <td>{{ $d->nama_dokumen }}<small>{{ $d->kategori }}</small></td>
                            <td>{{ $d->kelas?->nama ?? '-' }}<small>{{ $d->tahunAjaran?->nama }}</small></td>
                            <td>@include('admin.partials.badge', ['status' => $d->status])</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="empty">Belum ada dokumen.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="card">
        <div class="card-head">
            <div><h2>Pengajuan Legalisir</h2><p>5 pengajuan terakhir.</p></div>
            <a href="{{ route('siswa.legalisir.create') }}" class="btn btn-primary btn-sm">Ajukan</a>
        </div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Dokumen</th><th>Jumlah</th><th>Status</th></tr></thead>
                <tbody>
                    @forelse ($legalisirTerbaru as $l)
                        <tr>
                            <td>{{ $l->dokumen?->nama_dokumen ?? '-' }}<small>{{ $l->created_at->format('d M Y') }}</small></td>
                            <td>{{ $l->jumlah }}</td>
                            <td>@include('admin.partials.badge', ['status' => $l->status])</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="empty">Belum ada pengajuan.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>

@endsection
