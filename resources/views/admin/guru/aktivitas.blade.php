@extends('layouts.panel')

@php
    $judul = $guru ? 'Riwayat ' . $guru->nama : 'Pantau Guru';
    $aksiRoute = $guru ? route('admin.guru.riwayat', $guru) : route('admin.guru.aktivitas');
    $adaFilter = $q !== '' || $aksi || $kelas !== '' || $tahun !== '' || ($guruId && ! $guru);
@endphp

@section('title', $judul . ' | MAAKA')
@section('heading', $judul)
@section('subheading', $guru
    ? 'Dokumen yang diunggah, diubah, dan dihapus guru ini.'
    : 'Pantau dokumen yang diunggah, diubah, dan dihapus oleh semua guru.')

@push('styles')
<style>
    .mini-stats { display: grid; grid-template-columns: repeat(4, 1fr); gap: 14px; margin-bottom: 18px; }
    .mini-stat { padding: 16px 18px; }
    .mini-stat span { font-size: 13px; color: var(--muted); }
    .mini-stat strong { display: block; margin-top: 6px; font-size: 26px; line-height: 1.1; }
    .mini-stat.warn strong { color: var(--red); }
    @media (max-width: 900px) { .mini-stats { grid-template-columns: repeat(2, 1fr); } }
</style>
@endpush

@section('content')

<div class="mini-stats">
    <div class="card mini-stat"><span>Total aktivitas</span><strong>{{ $ringkasan['total'] }}</strong></div>
    <div class="card mini-stat"><span>Hari ini</span><strong>{{ $ringkasan['hariIni'] }}</strong></div>
    <div class="card mini-stat"><span>7 hari terakhir</span><strong>{{ $ringkasan['mingguIni'] }}</strong></div>
    <div class="card mini-stat {{ $ringkasan['hapus'] > 0 ? 'warn' : '' }}"><span>Penghapusan dokumen</span><strong>{{ $ringkasan['hapus'] }}</strong></div>
</div>

@unless ($guru)
    <section class="card" style="margin-bottom:18px">
        <div class="card-head">
            <div>
                <h2>Ringkasan per Guru</h2>
                <p>10 guru dengan aktivitas terbaru.</p>
            </div>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr><th>Guru</th><th>Unggah</th><th>Ubah</th><th>Hapus</th><th>Terakhir aktif</th></tr>
                </thead>
                <tbody>
                    @forelse ($perGuru as $g)
                        <tr>
                            <td>
                                @if ($g->guru_id)
                                    <a href="{{ route('admin.guru.riwayat', $g->guru_id) }}">{{ $g->guru_nama }}</a>
                                @else
                                    {{ $g->guru_nama }} <small>Sudah dihapus</small>
                                @endif
                                <small>{{ $g->guru_nip ? 'NIP ' . $g->guru_nip : '' }}</small>
                            </td>
                            <td>{{ $g->unggah }}</td>
                            <td>{{ $g->ubah }}</td>
                            <td>{{ $g->hapus }}</td>
                            <td>{{ \Illuminate\Support\Carbon::parse($g->terakhir)->format('d M Y, H:i') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="empty">Belum ada aktivitas yang tercatat.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endunless

<div class="toolbar">
    <form method="GET" action="{{ $aksiRoute }}" class="search" style="max-width:none">
        <input type="text" name="q" value="{{ $q }}" placeholder="Cari dokumen atau nama siswa...">
        @unless ($guru)
            <select name="guru_id" onchange="this.form.submit()">
                <option value="">Semua guru</option>
                @foreach ($gurus as $g)
                    <option value="{{ $g->id }}" @selected((int) $guruId === $g->id)>{{ $g->nama }}{{ $g->nip ? ' (' . $g->nip . ')' : '' }}</option>
                @endforeach
            </select>
        @endunless
        <select name="kelas" onchange="this.form.submit()">
            <option value="">Semua kelas</option>
            @foreach ($daftarKelas as $k)
                <option value="{{ $k }}" @selected($kelas === $k)>{{ $k }}</option>
            @endforeach
        </select>
        <select name="tahun" onchange="this.form.submit()">
            <option value="">Semua tahun ajaran</option>
            @foreach ($daftarTahun as $t)
                <option value="{{ $t }}" @selected($tahun === $t)>{{ $t }}</option>
            @endforeach
        </select>
        <select name="aksi" onchange="this.form.submit()">
            <option value="">Semua aksi</option>
            @foreach (\App\Models\AktivitasGuru::AKSI as $a)
                <option value="{{ $a }}" @selected($aksi === $a)>{{ $a }}</option>
            @endforeach
        </select>
        <button type="submit" class="btn btn-light">Cari</button>
        @if ($adaFilter)
            <a href="{{ $aksiRoute }}" class="btn btn-light">Reset</a>
        @endif
    </form>

    @if ($guru)
        <a href="{{ route('admin.guru.index') }}" class="btn btn-light">‹ Kembali ke Data Guru</a>
    @endif
</div>

<section class="card">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Waktu</th>
                    @unless ($guru) <th>Guru</th> @endunless
                    <th>Kelas</th>
                    <th>Aksi</th>
                    <th>Dokumen</th>
                    <th>Siswa</th>
                    <th>Keterangan</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($aktivitas as $a)
                    <tr>
                        <td>
                            {{ $a->created_at->format('d M Y') }}
                            <small>{{ $a->created_at->format('H:i') }}</small>
                        </td>
                        @unless ($guru)
                            <td>
                                {{ $a->guru_nama }}
                                <small>{{ $a->guru_nip ? 'NIP ' . $a->guru_nip : '' }}</small>
                            </td>
                        @endunless
                        <td>
                            {{ $a->kelas_nama ?? '-' }}
                            <small>{{ $a->tahun_ajaran_nama }}</small>
                        </td>
                        <td>@include('admin.partials.badge', ['status' => $a->aksi])</td>
                        <td>
                            {{ $a->nama_dokumen }}
                            @if ($a->aksi === 'Hapus')
                                <small>Sudah dihapus</small>
                            @endif
                        </td>
                        <td>{{ $a->nama_siswa ?? '-' }}</td>
                        <td>{{ $a->keterangan ?? '-' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $guru ? 6 : 7 }}" class="empty">Belum ada aktivitas yang tercatat.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @include('admin.partials.pager', ['paginator' => $aktivitas, 'noun' => 'aktivitas'])
</section>

@endsection
