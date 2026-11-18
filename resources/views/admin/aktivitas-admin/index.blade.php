@extends('layouts.panel')

@section('title', 'Log Aktivitas Admin | MAAKA')
@section('heading', 'Log Aktivitas Admin')
@section('subheading', 'Catatan perubahan data yang dilakukan administrator.')

@section('content')

<div class="toolbar">
    <form method="GET" action="{{ route('admin.aktivitas-admin.index') }}" class="search">
        <input type="text" name="q" value="{{ $q }}" placeholder="Cari admin, objek, atau keterangan...">
        <select name="aksi" onchange="this.form.submit()">
            <option value="">Semua aksi</option>
            @foreach ($daftarAksi as $a)
                <option value="{{ $a }}" @selected($aksi === $a)>{{ $a }}</option>
            @endforeach
        </select>
        <button type="submit" class="btn btn-light">Cari</button>
        @if ($q !== '' || $aksi !== '')
            <a href="{{ route('admin.aktivitas-admin.index') }}" class="btn btn-light">Reset</a>
        @endif
    </form>
</div>

<section class="card">
    <div class="table-wrap">
        <table>
            <thead><tr><th>Waktu</th><th>Admin</th><th>Aksi</th><th>Objek</th><th>Keterangan</th></tr></thead>
            <tbody>
                @forelse ($aktivitas as $a)
                    <tr>
                        <td>{{ $a->created_at->format('d M Y') }}<small>{{ $a->created_at->format('H:i') }}</small></td>
                        <td>{{ $a->admin_nama }}</td>
                        <td><span class="badge info">{{ $a->aksi }}</span></td>
                        <td>{{ $a->objek }}</td>
                        <td>{{ $a->keterangan ?? '-' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="empty">Belum ada aktivitas yang tercatat.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @include('admin.partials.pager', ['paginator' => $aktivitas, 'noun' => 'aktivitas'])
</section>

@endsection
