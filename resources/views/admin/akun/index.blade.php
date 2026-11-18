@extends('layouts.panel')

@section('title', 'Manajemen Akun | MAAKA')
@section('heading', 'Manajemen Akun')
@section('subheading', 'Kelola akun login Administrator, Guru, dan Siswa.')

@section('content')

<div class="toolbar">
    <form method="GET" action="{{ route('admin.akun.index') }}" class="search">
        <input type="text" name="q" value="{{ $q }}" placeholder="Cari nama atau email...">
        <select name="role" onchange="this.form.submit()">
            <option value="">Semua role</option>
            <option value="admin" @selected($role === 'admin')>Administrator</option>
            <option value="guru" @selected($role === 'guru')>Guru</option>
            <option value="siswa" @selected($role === 'siswa')>Siswa</option>
        </select>
        <button type="submit" class="btn btn-light">Cari</button>
        @if ($q !== '' || $role)
            <a href="{{ route('admin.akun.index') }}" class="btn btn-light">Reset</a>
        @endif
    </form>

    <a href="{{ route('admin.akun.create') }}" class="btn btn-primary">+ Tambah Akun</a>
</div>

<section class="card">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Nama</th>
                    <th>Email (untuk login)</th>
                    <th>Role</th>
                    <th>Terhubung ke</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($akuns as $akun)
                    <tr>
                        <td>
                            {{ $akun->name }}
                            @if ($akun->is(auth()->user()))
                                <small>Akun Anda</small>
                            @endif
                        </td>
                        <td>{{ $akun->email }}</td>
                        <td>
                            @if ($akun->role === 'admin')
                                <span class="badge danger">Administrator</span>
                            @elseif ($akun->role === 'guru')
                                <span class="badge info">Guru</span>
                            @else
                                <span class="badge success">Siswa</span>
                            @endif
                        </td>
                        <td>
                            @if ($akun->siswa)
                                NIS {{ $akun->siswa->nis }}
                            @elseif ($akun->guru)
                                {{ $akun->guru->nip ? 'NIP ' . $akun->guru->nip : $akun->guru->nama }}
                            @else
                                -
                            @endif
                        </td>
                        <td class="actions">
                            <div class="row-actions">
                                <a href="{{ route('admin.akun.edit', $akun) }}" class="btn btn-light btn-sm">Ubah</a>

                                @unless ($akun->is(auth()->user()))
                                    <form method="POST" action="{{ route('admin.akun.destroy', $akun) }}"
                                          data-confirm="Hapus akun &quot;{{ $akun->email }}&quot;?&#10;&#10;Pemilik akun tidak akan bisa login lagi. Data siswa/guru tidak ikut terhapus.">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-danger btn-sm">Hapus</button>
                                    </form>
                                @endunless
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="empty">
                            @if ($q !== '' || $role)
                                Tidak ada akun yang cocok dengan pencarian/filter.
                            @else
                                Belum ada akun.
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @include('admin.partials.pager', ['paginator' => $akuns, 'noun' => 'akun'])
</section>

@endsection
