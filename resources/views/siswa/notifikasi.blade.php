@extends('layouts.panel')

@section('title', 'Notifikasi | MAAKA')
@section('heading', 'Notifikasi')
@section('subheading', 'Kabar terbaru tentang dokumen dan pengajuan Anda.')

@section('content')

<div class="toolbar">
    <span class="hint" style="margin:0">{{ $notifikasis->total() }} notifikasi</span>
    <form method="POST" action="{{ route('siswa.notifikasi.baca-semua') }}">
        @csrf
        <button type="submit" class="btn btn-light">Tandai semua dibaca</button>
    </form>
</div>

<section class="card">
    <div class="table-wrap">
        <table>
            <thead><tr><th>Notifikasi</th><th>Waktu</th><th></th></tr></thead>
            <tbody>
                @forelse ($notifikasis as $n)
                    <tr>
                        <td>
                            @unless ($n->dibaca_pada) <span class="badge info">Baru</span> @endunless
                            <strong>{{ $n->judul }}</strong>
                            @if ($n->pesan)<small>{{ $n->pesan }}</small>@endif
                        </td>
                        <td>{{ $n->created_at->format('d M Y') }}<small>{{ $n->created_at->format('H:i') }}</small></td>
                        <td class="actions">
                            <form method="POST" action="{{ route('siswa.notifikasi.baca', $n) }}">
                                @csrf
                                <button type="submit" class="btn btn-light btn-sm">Buka</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="empty">Belum ada notifikasi.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @include('admin.partials.pager', ['paginator' => $notifikasis, 'noun' => 'notifikasi'])
</section>

@endsection
