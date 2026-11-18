@extends('layouts.panel')

@section('title', 'Peminjaman Rapor | MAAKA')
@section('heading', 'Peminjaman Rapor')
@section('subheading', 'Proses pengajuan pinjam rapor asli dan pantau pengembaliannya.')

@section('content')

<div class="toolbar">
    <form method="GET" action="{{ route('admin.peminjaman-rapor.index') }}" class="search">
        <input type="text" name="q" value="{{ $q }}" placeholder="Cari nama atau NIS siswa...">
        <select name="status" onchange="this.form.submit()">
            <option value="">Semua status</option>
            @foreach (\App\Models\PeminjamanRapor::STATUSES as $s)
                <option value="{{ $s }}" @selected($status === $s)>{{ $s }}</option>
            @endforeach
            <option value="Terlambat" @selected($status === 'Terlambat')>Terlambat dikembalikan</option>
        </select>
        <button type="submit" class="btn btn-light">Cari</button>
        @if ($q !== '' || $status)
            <a href="{{ route('admin.peminjaman-rapor.index') }}" class="btn btn-light">Reset</a>
        @endif
    </form>
    <a href="{{ route('admin.ekspor', 'peminjaman-rapor') }}" class="btn btn-light">Ekspor CSV</a>
</div>

<section class="card">
    <div class="table-wrap">
        <table>
            <thead><tr><th>Siswa</th><th>Rapor</th><th>Pinjam</th><th>Kembali</th><th>Status</th><th></th></tr></thead>
            <tbody>
                @forelse ($peminjamans as $p)
                    <tr>
                        <td>{{ $p->siswa?->nama ?? '-' }}<small>{{ $p->siswa?->nis }} · {{ $p->siswa?->kelas }}</small></td>
                        <td>{{ $p->dokumen?->nama_dokumen ?? '-' }}</td>
                        <td>{{ $p->tanggal_pinjam->format('d M Y') }}</td>
                        <td>{{ $p->tanggal_kembali->format('d M Y') }}</td>
                        <td>
                            @include('admin.partials.badge', ['status' => $p->status])
                            @if ($p->terlambat()) @include('admin.partials.badge', ['status' => 'Terlambat']) @endif
                        </td>
                        <td class="actions">
                            <div class="row-actions">
                                <a href="{{ route('admin.peminjaman-rapor.show', $p) }}" class="btn btn-light btn-sm">Proses</a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="empty">Belum ada pengajuan peminjaman rapor.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @include('admin.partials.pager', ['paginator' => $peminjamans, 'noun' => 'peminjaman'])
</section>

@endsection
