@extends('layouts.panel')

@section('title', 'Tahun Ajaran | MAAKA')
@section('heading', 'Tahun Ajaran')
@section('subheading', 'Setiap tahun ajaran menyimpan kelas siswa dan wali kelas pada masanya.')

@section('content')

<div class="toolbar">
    <span></span>
    <a href="{{ route('admin.naik-kelas.create') }}" class="btn btn-primary">Proses Naik Kelas</a>
</div>

<section class="card">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Tahun Ajaran</th>
                    <th>Status</th>
                    <th>Penempatan Siswa</th>
                    <th>Wali Kelas</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($tahuns as $t)
                    <tr>
                        <td>{{ $t->nama }}</td>
                        <td>@if ($t->aktif)<span class="badge success">Aktif</span>@else<span class="badge info">Arsip</span>@endif</td>
                        <td>{{ $t->riwayat_kelas_count }}</td>
                        <td>{{ $t->wali_kelas_count }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="empty">Belum ada tahun ajaran.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>

@endsection
