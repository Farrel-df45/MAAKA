@extends('layouts.panel')

@section('title', 'Riwayat ' . $siswa->nama . ' | MAAKA')
@section('heading', 'Riwayat ' . $siswa->nama)
@section('subheading', 'NIS ' . $siswa->nis . ' · kelas, wali kelas, dan dokumen pada tiap tahun ajaran.')

@section('content')

<div class="toolbar">
    <a href="{{ route('admin.siswa.index') }}" class="btn btn-light">‹ Kembali ke Data Siswa</a>
    <a href="{{ route('admin.siswa.edit', $siswa) }}" class="btn btn-light">Ubah Data Siswa</a>
</div>

@forelse ($periode as $p)
    <section class="card" style="margin-bottom:18px">
        <div class="card-head">
            <div>
                <h2>{{ $p['riwayat']->tahunAjaran->nama }} · {{ $p['riwayat']->kelas->nama }}
                    @if ($p['riwayat']->tahunAjaran->aktif)<span class="badge success">Aktif</span>@endif
                </h2>
                <p>Wali kelas: {{ $p['wali']?->guru_nama ?? 'belum tercatat' }}{{ $p['wali']?->guru_nip ? ' (NIP ' . $p['wali']->guru_nip . ')' : '' }}</p>
            </div>
        </div>

        <div class="table-wrap">
            <table>
                <thead><tr><th>Dokumen</th><th>Kategori</th><th>Diunggah oleh</th><th>Status</th><th></th></tr></thead>
                <tbody>
                    @forelse ($p['dokumens'] as $d)
                        <tr>
                            <td>{{ $d->nama_dokumen }}</td>
                            <td>{{ $d->kategori }}</td>
                            <td>{{ $d->pengunggah?->name ?? '-' }}</td>
                            <td>@include('admin.partials.badge', ['status' => $d->status])</td>
                            <td class="actions">
                                <div class="row-actions">
                                    <a href="{{ route('admin.dokumen.berkas', $d) }}" target="_blank" class="btn btn-light btn-sm">Lihat</a>
                                    <a href="{{ route('admin.dokumen.edit', $d) }}" class="btn btn-light btn-sm">Ubah</a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="empty">Tidak ada dokumen pada periode ini.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@empty
    <section class="card"><div class="empty">Siswa ini belum punya riwayat kelas.</div></section>
@endforelse

@if ($tanpaPeriode->isNotEmpty())
    <section class="card">
        <div class="card-head">
            <div><h2>Dokumen tanpa periode</h2><p>Dokumen lama yang belum terhubung ke kelas dan tahun ajaran.</p></div>
        </div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Dokumen</th><th>Kategori</th><th>Status</th><th></th></tr></thead>
                <tbody>
                    @foreach ($tanpaPeriode as $d)
                        <tr>
                            <td>{{ $d->nama_dokumen }}</td>
                            <td>{{ $d->kategori }}</td>
                            <td>@include('admin.partials.badge', ['status' => $d->status])</td>
                            <td class="actions"><div class="row-actions">
                                <a href="{{ route('admin.dokumen.edit', $d) }}" class="btn btn-light btn-sm">Ubah</a>
                            </div></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
@endif

@endsection
