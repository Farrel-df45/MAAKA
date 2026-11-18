@extends('layouts.panel')

@section('title', 'Dokumen Saya | MAAKA')
@section('heading', 'Dokumen Saya')
@section('subheading', 'Seluruh dokumen akademik Anda, termasuk dari kelas dan tahun ajaran sebelumnya.')

@section('content')

@unless ($siswa)
    <div class="alert error">Akun ini belum terhubung ke data siswa. Hubungi Admin.</div>
@endunless

<div class="toolbar">
    <form method="GET" action="{{ route('siswa.dokumen.index') }}" class="search">
        <select name="status" onchange="this.form.submit()">
            <option value="">Semua status</option>
            @foreach (\App\Models\Dokumen::STATUSES as $s)
                <option value="{{ $s }}" @selected($status === $s)>{{ $s }}</option>
            @endforeach
        </select>
        @if ($status)
            <a href="{{ route('siswa.dokumen.index') }}" class="btn btn-light">Reset</a>
        @endif
    </form>
    <div class="row-actions">
        <a href="{{ route('siswa.legalisir.create') }}" class="btn btn-light">Ajukan Legalisir</a>
        <a href="{{ route('siswa.dokumen.create') }}" class="btn btn-primary">+ Unggah Dokumen</a>
    </div>
</div>

<section class="card">
    <div class="table-wrap">
        <table>
            <thead><tr><th>Dokumen</th><th>Kategori</th><th>Kelas</th><th>Status</th><th></th></tr></thead>
            <tbody>
                @forelse ($dokumens as $d)
                    <tr>
                        <td>{{ $d->nama_dokumen }}<small>{{ $d->created_at->format('d M Y') }}</small></td>
                        <td>{{ $d->kategori }}</td>
                        <td>{{ $d->kelas?->nama ?? '-' }}<small>{{ $d->tahunAjaran?->nama }}</small></td>
                        <td>@include('admin.partials.badge', ['status' => $d->status])@if ($d->status === \App\Models\Dokumen::STATUS_DITOLAK && $d->catatan)<small>Alasan: {{ $d->catatan }}</small>@endif</td>
                        <td class="actions">
                            <div class="row-actions">
                                <a href="{{ route('siswa.dokumen.berkas', $d) }}" target="_blank" class="btn btn-light btn-sm">Lihat</a>
                                @if ($d->status !== \App\Models\Dokumen::STATUS_TERVERIFIKASI)
                                    <form method="POST" action="{{ route('siswa.dokumen.destroy', $d) }}" data-confirm="Hapus dokumen &quot;{{ $d->nama_dokumen }}&quot;?">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-danger btn-sm">Hapus</button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="empty">Belum ada dokumen.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @include('admin.partials.pager', ['paginator' => $dokumens, 'noun' => 'dokumen'])
</section>

@endsection
