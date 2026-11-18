@extends('layouts.panel')

@section('title', 'Riwayat Wali Kelas ' . $guru->nama . ' | MAAKA')
@section('heading', 'Riwayat Wali Kelas')
@section('subheading', $guru->nama . ($guru->nip ? ' · NIP ' . $guru->nip : '') . ' · kelas yang pernah dipegang beserta siswanya.')

@section('content')

<div class="toolbar">
    <a href="{{ route('admin.guru.index') }}" class="btn btn-light">‹ Kembali ke Data Guru</a>
    <a href="{{ route('admin.guru.riwayat', $guru) }}" class="btn btn-light">Lihat Aktivitas Guru</a>
</div>

@forelse ($penugasan as $p)
    <section class="card" style="margin-bottom:18px">
        <div class="card-head">
            <div>
                <h2>{{ $p['wali']->tahunAjaran->nama }} · {{ $p['wali']->kelas->nama }}
                    @if ($p['wali']->tahunAjaran->aktif)<span class="badge success">Aktif</span>@endif
                </h2>
                <p>{{ $p['siswas']->count() }} siswa · {{ $p['diunggahGuru'] }} dokumen diunggah oleh guru ini</p>
            </div>
        </div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>NIS</th><th>Nama siswa</th><th>Dokumen di kelas ini</th><th></th></tr></thead>
                <tbody>
                    @forelse ($p['siswas'] as $s)
                        <tr>
                            <td>{{ $s->nis }}</td>
                            <td>{{ $s->nama }}</td>
                            <td>{{ $s->dokumen_periode_count }}</td>
                            <td class="actions"><div class="row-actions">
                                <a href="{{ route('admin.siswa.riwayat', $s) }}" class="btn btn-light btn-sm">Riwayat siswa</a>
                            </div></td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="empty">Tidak ada siswa tercatat pada kelas ini.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@empty
    <section class="card"><div class="empty">Guru ini belum pernah menjadi wali kelas.</div></section>
@endforelse

@endsection
