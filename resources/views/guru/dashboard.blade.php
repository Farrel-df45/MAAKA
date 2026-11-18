@extends('layouts.panel')

@section('title', 'Dashboard Guru | MAAKA')
@section('heading', 'Dashboard Guru')
@section('subheading', $wali
    ? 'Wali kelas ' . $wali->kelas->nama . ' · tahun ajaran ' . $wali->tahunAjaran->nama . ' · ' . $jumlahSiswa . ' siswa.'
    : 'Ringkasan dokumen akademik kelas Anda.')

@push('styles')
<style>
    .stats { display: grid; grid-template-columns: repeat(4, 1fr); gap: 18px; margin-bottom: 22px; }
    .stat { padding: 20px 22px; }
    .stat span { font-size: 14px; color: var(--muted); }
    a.stat:hover { border-color: var(--accent); }
    .stat strong { display: block; margin: 10px 0 2px; font-size: 32px; line-height: 1.1; }
    .stat small { font-size: 13px; color: var(--muted); }
    @media (max-width: 1100px) { .stats { grid-template-columns: repeat(2, 1fr); } }
    @media (max-width: 520px)  { .stats { grid-template-columns: minmax(0, 1fr); } }
</style>
@endpush

@section('content')

@unless ($guru)
    <div class="alert error">Akun ini belum terhubung ke data guru, jadi unggahan belum bisa dicatat. Hubungi Admin.</div>
@endunless

@if ($guru && ! $wali)
    <div class="alert error">Anda belum ditugaskan sebagai wali kelas pada tahun ajaran aktif. Hubungi Admin untuk penugasan kelas.</div>
@endif

<div class="stats">
    <a href="{{ route('guru.dokumen.index') }}" class="card stat">
        <span>Dokumen Kelas</span>
        <strong>{{ $totalDokumen }}</strong>
        <small>Dokumen siswa di kelas Anda</small>
    </a>
    <a href="{{ route('guru.dokumen.index', ['status' => \App\Models\Dokumen::STATUS_MENUNGGU]) }}" class="card stat">
        <span>Menunggu Verifikasi</span>
        <strong>{{ $menunggu }}</strong>
        <small>Belum diperiksa Admin</small>
    </a>
    <a href="{{ route('guru.dokumen.index', ['status' => \App\Models\Dokumen::STATUS_TERVERIFIKASI]) }}" class="card stat">
        <span>Terverifikasi</span>
        <strong>{{ $terverifikasi }}</strong>
        <small>Sudah disetujui Admin</small>
    </a>
    <a href="{{ route('guru.dokumen.index', ['status' => \App\Models\Dokumen::STATUS_DITOLAK]) }}" class="card stat">
        <span>Ditolak</span>
        <strong>{{ $ditolak }}</strong>
        <small>Perlu diperbaiki</small>
    </a>
</div>

<section class="card">
    <div class="card-head">
        <div>
            <h2>Aktivitas Terbaru</h2>
            <p>5 aksi terakhir Anda (juga terlihat oleh Admin).</p>
        </div>
        <a href="{{ route('guru.dokumen.create') }}" class="btn btn-primary btn-sm">+ Unggah Dokumen</a>
    </div>

    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>Waktu</th><th>Aksi</th><th>Dokumen</th><th>Siswa</th></tr>
            </thead>
            <tbody>
                @forelse ($aktivitasTerbaru as $a)
                    <tr>
                        <td>{{ $a->created_at->format('d M Y, H:i') }}</td>
                        <td>@include('admin.partials.badge', ['status' => $a->aksi])</td>
                        <td>{{ $a->nama_dokumen }}@if ($a->keterangan)<small>{{ $a->keterangan }}</small>@endif</td>
                        <td>{{ $a->nama_siswa ?? '-' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="empty">Belum ada aktivitas. Mulai dengan mengunggah dokumen.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>

@endsection
