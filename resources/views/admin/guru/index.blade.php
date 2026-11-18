@extends('layouts.panel')

@section('title', 'Data Guru | MAAKA')
@section('heading', 'Data Guru')
@section('subheading', 'Kelola data guru dan wali kelas.')

@section('content')

<div class="toolbar">
    <form method="GET" action="{{ route('admin.guru.index') }}" class="search">
        <input type="text" name="q" value="{{ $q }}" placeholder="Cari nama, NIP, atau email...">
        <button type="submit" class="btn btn-light">Cari</button>
        @if ($q !== '')
            <a href="{{ route('admin.guru.index') }}" class="btn btn-light">Reset</a>
        @endif
    </form>

    <div class="row-actions">
        <a href="{{ route('admin.impor.create', 'guru') }}" class="btn btn-light">Impor CSV</a>
        <a href="{{ route('admin.guru.aktivitas') }}" class="btn btn-light">Riwayat Semua Guru</a>
        <a href="{{ route('admin.guru.create') }}" class="btn btn-primary">+ Tambah Guru</a>
    </div>
</div>

<section class="card">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>NIP</th>
                    <th>Nama</th>
                    <th>Jabatan</th>
                    <th>Wali Kelas</th>
                    <th>Telepon</th>
                    <th>Akun Login</th>
                    <th>Aktivitas</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($gurus as $guru)
                    <tr>
                        <td>{{ $guru->nip ?? '-' }}</td>
                        <td>
                            {{ $guru->nama }}
                            <small>{{ $guru->email }}</small>
                        </td>
                        <td>{{ $guru->jabatan }}</td>
                        <td>
                            @if ($guru->waliAktif)
                                <span class="badge info">{{ $guru->waliAktif->kelas->nama }}</span>
                            @else
                                <span class="badge pending">Belum</span>
                            @endif
                        </td>
                        <td>{{ $guru->nomor_telepon ?? '-' }}</td>
                        <td>
                            @if ($guru->user)
                                <span class="badge success">Ada</span>
                                @if ($guru->nip)<small>Login: {{ $guru->nip }}</small>@endif
                            @else
                                <span class="badge pending">Belum</span>
                            @endif
                        </td>
                        <td>{{ $guru->aktivitas_count }}</td>
                        <td class="actions">
                            <div class="row-actions">
                                <a href="{{ route('admin.guru.wali', $guru) }}" class="btn btn-light btn-sm">Wali Kelas</a>
                                <a href="{{ route('admin.guru.riwayat', $guru) }}" class="btn btn-light btn-sm">Riwayat</a>
                                <a href="{{ route('admin.guru.edit', $guru) }}" class="btn btn-light btn-sm">Ubah</a>

                                <form method="POST" action="{{ route('admin.guru.destroy', $guru) }}"
                                      data-confirm="Hapus guru &quot;{{ $guru->nama }}&quot;?@if ($guru->user)&#10;&#10;Akun login guru ini juga akan ikut terhapus.@endif&#10;&#10;Tindakan ini tidak dapat dibatalkan.">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-danger btn-sm">Hapus</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="empty">
                            @if ($q !== '')
                                Tidak ada guru yang cocok dengan "{{ $q }}".
                            @else
                                Belum ada data guru. Klik "Tambah Guru" untuk mulai.
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @include('admin.partials.pager', ['paginator' => $gurus, 'noun' => 'guru'])
</section>

@endsection
