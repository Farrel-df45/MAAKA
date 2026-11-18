@extends('layouts.panel')

@section('title', 'Kelas | MAAKA')
@section('heading', 'Kelas')
@section('subheading', $tahun ? 'Wali kelas dan siswa untuk tahun ajaran ' . $tahun->nama . '.' : 'Belum ada tahun ajaran aktif.')

@section('content')

<div class="toolbar">
    <form method="GET" action="{{ route('admin.kelas.index') }}" class="search">
        <input type="text" name="q" value="{{ $q }}" placeholder="Cari nama kelas...">
        <button type="submit" class="btn btn-light">Cari</button>
        @if ($q !== '')
            <a href="{{ route('admin.kelas.index') }}" class="btn btn-light">Reset</a>
        @endif
    </form>

    <div class="row-actions">
        <a href="{{ route('admin.naik-kelas.create') }}" class="btn btn-light">Naik Kelas</a>
        <a href="{{ route('admin.kelas.create') }}" class="btn btn-primary">+ Tambah Kelas</a>
    </div>
</div>

<section class="card">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Kelas</th>
                    <th>Jurusan</th>
                    <th>Wali Kelas {{ $tahun?->nama }}</th>
                    <th>Siswa</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($kelas as $k)
                    <tr>
                        <td>{{ $k->nama }}@if ($k->tingkat)<small>Tingkat {{ $k->tingkat }}</small>@endif</td>
                        <td>{{ $k->jurusan ?? '-' }}</td>
                        <td>
                            @if ($k->waliAktif)
                                {{ $k->waliAktif->guru_nama }}
                                @if ($k->waliAktif->guru_nip)<small>NIP {{ $k->waliAktif->guru_nip }}</small>@endif
                            @else
                                <span class="badge pending">Belum ada</span>
                            @endif
                        </td>
                        <td>
                            <a href="{{ route('admin.siswa.index', ['kelas_id' => $k->id]) }}">{{ $k->siswa_aktif_count }} siswa</a>
                        </td>
                        <td class="actions">
                            <div class="row-actions">
                                <a href="{{ route('admin.kelas.edit', $k) }}" class="btn btn-light btn-sm">Ubah</a>
                                <form method="POST" action="{{ route('admin.kelas.destroy', $k) }}"
                                      data-confirm="Hapus kelas {{ $k->nama }}? Kelas yang sudah punya riwayat tidak dapat dihapus.">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-danger btn-sm">Hapus</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="empty">Belum ada kelas. Klik "Tambah Kelas" untuk mulai.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @include('admin.partials.pager', ['paginator' => $kelas, 'noun' => 'kelas'])
</section>

@endsection
