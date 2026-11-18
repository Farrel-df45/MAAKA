@extends('layouts.panel')

@section('title', 'Dashboard Admin | MAAKA')
@section('heading', 'Dashboard Admin')
@section('subheading', 'Ringkasan data akademik dan hal yang perlu ditindaklanjuti.')

@push('styles')
<style>
    .stats {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 18px;
        margin-bottom: 22px;
    }
    .stat { padding: 20px 22px; }
    .stat-top { display: flex; align-items: center; justify-content: space-between; }
    .stat-top span { font-size: 14px; color: var(--muted); }
    .stat-icon {
        width: 38px; height: 38px;
        display: grid; place-items: center;
        border-radius: 10px;
    }
    .stat-icon svg { width: 20px; height: 20px; stroke: currentColor; fill: none; stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round; }
    .stat-icon.blue  { background: var(--blue-bg);  color: var(--blue); }
    .stat-icon.green { background: var(--green-bg); color: var(--green); }
    .stat-icon.amber { background: var(--amber-bg); color: var(--amber); }
    .stat-icon.red   { background: var(--red-bg);   color: var(--red); }
    .stat strong { display: block; margin: 12px 0 2px; font-size: 32px; line-height: 1.1; }
    .stat small  { font-size: 13px; color: var(--muted); }
    .stat small.warn { color: var(--amber); font-weight: 600; }

    .grid-2 {
        display: grid;
        grid-template-columns: minmax(0, 2fr) minmax(0, 1fr);
        gap: 18px;
        align-items: start;
    }

    .todo { padding: 8px 22px 14px; }
    .todo-item {
        display: flex; align-items: center; justify-content: space-between; gap: 12px;
        padding: 14px 0;
        border-bottom: 1px solid var(--line);
        font-size: 14px;
    }
    .todo-item:last-child { border-bottom: 0; }
    .todo-count {
        min-width: 34px;
        text-align: center;
        padding: 4px 10px;
        border-radius: 999px;
        font-weight: 700;
        font-size: 13px;
    }
    .todo-count.on  { background: var(--amber-bg); color: var(--amber); }
    .todo-count.off { background: var(--green-bg); color: var(--green); }
    .todo-note { padding: 0 22px 18px; font-size: 13px; color: var(--muted); }

    @media (max-width: 1100px) { .stats { grid-template-columns: repeat(2, 1fr); } }
    @media (max-width: 900px)  { .grid-2 { grid-template-columns: minmax(0, 1fr); } }
    @media (max-width: 520px)  { .stats { grid-template-columns: minmax(0, 1fr); } }
</style>
@endpush

@section('content')

<div class="stats">
    <div class="card stat">
        <div class="stat-top">
            <span>Total Siswa</span>
            <div class="stat-icon blue">
                <svg viewBox="0 0 24 24"><circle cx="9" cy="8" r="3.2"/><path d="M3 20c0-3.3 2.7-6 6-6s6 2.7 6 6"/><path d="M16 5.5a3 3 0 010 5.5M18 14.5c1.8.8 3 2.6 3 5.5"/></svg>
            </div>
        </div>
        <strong>{{ $totalSiswa }}</strong>
        <small>Siswa terdaftar</small>
    </div>

    <div class="card stat">
        <div class="stat-top">
            <span>Total Guru</span>
            <div class="stat-icon green">
                <svg viewBox="0 0 24 24"><path d="M3 8l9-4 9 4-9 4-9-4z"/><path d="M7 10.5V15c0 1.4 2.2 3 5 3s5-1.6 5-3v-4.5"/></svg>
            </div>
        </div>
        <strong>{{ $totalGuru }}</strong>
        <small>Guru terdaftar</small>
    </div>

    <div class="card stat">
        <div class="stat-top">
            <span>Dokumen Akademik</span>
            <div class="stat-icon amber">
                <svg viewBox="0 0 24 24"><path d="M7 3h7l5 5v13H7z"/><path d="M14 3v5h5"/></svg>
            </div>
        </div>
        <strong>{{ $totalDokumen }}</strong>
        @if ($dokumenMenunggu > 0)
            <small class="warn">{{ $dokumenMenunggu }} menunggu verifikasi</small>
        @else
            <small>Semua sudah diverifikasi</small>
        @endif
    </div>

    <div class="card stat">
        <div class="stat-top">
            <span>Permohonan Legalisir</span>
            <div class="stat-icon red">
                <svg viewBox="0 0 24 24"><path d="M5 20h14"/><path d="M8 16h8l-1-5a3 3 0 10-6 0z"/></svg>
            </div>
        </div>
        <strong>{{ $totalLegalisir }}</strong>
        @if ($legalisirMenunggu > 0)
            <small class="warn">{{ $legalisirMenunggu }} menunggu diproses</small>
        @else
            <small>Tidak ada antrean</small>
        @endif
    </div>
</div>

<section class="card todo" style="margin-bottom:18px">
    <div class="card-head" style="padding-left:0;padding-right:0">
        <div>
            <h2>Perlu Ditindaklanjuti</h2>
            <p>Antrean yang menunggu tindakan admin.</p>
        </div>
    </div>
    @foreach ([
        ['Dokumen menunggu verifikasi', $dokumenMenunggu, route('admin.dokumen.index', ['status' => \App\Models\Dokumen::STATUS_MENUNGGU])],
        ['Permohonan legalisir menunggu', $legalisirMenunggu, route('admin.legalisir.index', ['status' => 'Menunggu'])],
        ['Pengajuan pinjam rapor menunggu', $rporTunggu, route('admin.peminjaman-rapor.index', ['status' => 'Menunggu'])],
        ['Rapor terlambat dikembalikan', $rporTerlambat, route('admin.peminjaman-rapor.index', ['status' => 'Terlambat'])],
        ['Pengajuan ambil ijazah menunggu', $ijazahTunggu, route('admin.pengambilan-ijazah.index', ['status' => 'Menunggu'])],
        ['Siswa dengan dokumen belum lengkap', $belumLengkap, route('admin.kelengkapan.index', ['filter' => 'belum'])],
    ] as [$teks, $jumlah, $url])
        <a href="{{ $url }}" class="todo-item" style="text-decoration:none;color:inherit">
            <span>{{ $teks }}</span>
            <span class="todo-count {{ $jumlah > 0 ? 'on' : 'off' }}">{{ $jumlah }}</span>
        </a>
    @endforeach
</section>

<section class="card" style="margin-bottom:18px">
    <div class="card-head">
        <div>
            <h2>Aktivitas Guru Terbaru</h2>
            <p>Pantau apa yang dilakukan guru
                @if ($tahunAktif) · tahun ajaran {{ $tahunAktif->nama }} @endif
                · {{ $aktivitasHariIni }} aktivitas hari ini.</p>
        </div>
        <a href="{{ route('admin.guru.aktivitas') }}" class="btn btn-light btn-sm">Lihat semua</a>
    </div>

    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>Waktu</th><th>Guru</th><th>Kelas</th><th>Aksi</th><th>Dokumen</th><th>Siswa</th></tr>
            </thead>
            <tbody>
                @forelse ($aktivitasGuru as $a)
                    <tr>
                        <td>{{ $a->created_at->format('d M Y') }}<small>{{ $a->created_at->format('H:i') }}</small></td>
                        <td>{{ $a->guru_nama }}<small>{{ $a->guru_nip ? 'NIP ' . $a->guru_nip : '' }}</small></td>
                        <td>{{ $a->kelas_nama ?? '-' }}</td>
                        <td>@include('admin.partials.badge', ['status' => $a->aksi])</td>
                        <td>{{ $a->nama_dokumen }}@if ($a->keterangan)<small>{{ $a->keterangan }}</small>@endif</td>
                        <td>{{ $a->nama_siswa ?? '-' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="empty">Belum ada aktivitas guru.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>

<section class="card" style="margin-bottom:18px">
    <div class="card-head">
        <div><h2>Aktivitas Admin Terbaru</h2><p>Perubahan data oleh administrator.</p></div>
        <a href="{{ route('admin.aktivitas-admin.index') }}" class="btn btn-light btn-sm">Lihat semua</a>
    </div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Waktu</th><th>Admin</th><th>Aksi</th><th>Objek</th><th>Keterangan</th></tr></thead>
            <tbody>
                @forelse ($aktivitasAdmin as $a)
                    <tr>
                        <td>{{ $a->created_at->format('d M Y') }}<small>{{ $a->created_at->format('H:i') }}</small></td>
                        <td>{{ $a->admin_nama }}</td>
                        <td><span class="badge info">{{ $a->aksi }}</span></td>
                        <td>{{ $a->objek }}</td>
                        <td>{{ $a->keterangan ?? '-' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="empty">Belum ada aktivitas admin.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>

<div class="grid-2">
    <section class="card">
        <div class="card-head">
            <div>
                <h2>Dokumen Terbaru</h2>
                <p>5 dokumen yang terakhir ditambahkan.</p>
            </div>
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Dokumen</th>
                        <th>Siswa</th>
                        <th>Kategori</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($dokumenTerbaru as $dokumen)
                        <tr>
                            <td>{{ $dokumen->nama_dokumen }}</td>
                            <td>
                                {{ $dokumen->siswa?->nama ?? '-' }}
                                <small>{{ $dokumen->siswa?->nis }}</small>
                            </td>
                            <td>{{ $dokumen->kategori }}</td>
                            <td>
                                @if ($dokumen->status === 'Terverifikasi')
                                    <span class="badge success">Terverifikasi</span>
                                @elseif ($dokumen->status === 'Ditolak')
                                    <span class="badge danger">Ditolak</span>
                                @else
                                    <span class="badge pending">Menunggu</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="empty">Belum ada dokumen.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="card">
        <div class="card-head">
            <div>
                <h2>Perlu Tindakan</h2>
                <p>Antrean yang menunggu Admin.</p>
            </div>
        </div>

        <div class="todo">
            <a href="{{ route('admin.dokumen.index', ['status' => \App\Models\Dokumen::STATUS_MENUNGGU]) }}" class="todo-item">
                <span>Dokumen menunggu verifikasi</span>
                <span class="todo-count {{ $dokumenMenunggu > 0 ? 'on' : 'off' }}">{{ $dokumenMenunggu }}</span>
            </a>
            <a href="{{ route('admin.legalisir.index', ['status' => \App\Models\Legalisir::STATUS_MENUNGGU]) }}" class="todo-item">
                <span>Legalisir menunggu diproses</span>
                <span class="todo-count {{ $legalisirMenunggu > 0 ? 'on' : 'off' }}">{{ $legalisirMenunggu }}</span>
            </a>
            <div class="todo-item">
                <span>Akun Admin aktif</span>
                <span class="todo-count off">{{ $totalAdmin }}</span>
            </div>
        </div>

        @if ($dokumenMenunggu === 0 && $legalisirMenunggu === 0)
            <p class="todo-note">Tidak ada pekerjaan tertunda saat ini.</p>
        @endif
    </section>
</div>

@endsection