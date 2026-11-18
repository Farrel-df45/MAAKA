@extends('layouts.panel')

@section('title', 'Status Pengajuan | MAAKA')
@section('heading', 'Status Pengajuan')
@section('subheading', 'Pantau legalisir, peminjaman rapor, dan pengambilan ijazah dalam satu halaman.')

@push('styles')
<style>
    .langkah { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 8px; }
    .langkah span { font-size: 12px; padding: 3px 10px; border-radius: 999px; background: var(--line); color: var(--muted); }
    .langkah span.selesai { background: var(--green-bg); color: var(--green); }
    .langkah span.sekarang { background: var(--blue-bg); color: var(--blue); font-weight: 700; }
    .langkah.berhenti span { opacity: .5; }
</style>
@endpush

@section('content')

<div class="toolbar">
    <div class="row-actions">
        <a href="{{ route('siswa.pengajuan') }}" class="btn {{ $semua ? 'btn-light' : 'btn-primary' }}">Berjalan ({{ $jumlahBerjalan }})</a>
        <a href="{{ route('siswa.pengajuan', ['tampil' => 'semua']) }}" class="btn {{ $semua ? 'btn-primary' : 'btn-light' }}">Semua ({{ $jumlahSemua }})</a>
    </div>
</div>

<section class="card">
    <div class="table-wrap">
        <table>
            <thead><tr><th>Pengajuan</th><th>Tahapan</th><th>Status</th><th></th></tr></thead>
            <tbody>
                @forelse ($daftar as $p)
                    <tr>
                        <td>
                            <strong>{{ $p['jenis'] }}</strong> · {{ $p['judul'] }}
                            <small>{{ $p['info'] }} · diajukan {{ $p['dibuat']->format('d M Y') }}</small>
                            @if ($p['catatan'])<small>Catatan: {{ $p['catatan'] }}</small>@endif
                        </td>
                        <td>
                            <div class="langkah {{ $p['berhenti'] ? 'berhenti' : '' }}">
                                @foreach ($p['langkah'] as $i => $l)
                                    <span class="{{ $i < $p['indeks'] ? 'selesai' : ($i === $p['indeks'] ? 'sekarang' : '') }}">{{ $l }}</span>
                                @endforeach
                            </div>
                            <small>Terakhir diperbarui {{ $p['diubah']->format('d M Y, H:i') }}</small>
                        </td>
                        <td>@include('admin.partials.badge', ['status' => $p['status']])</td>
                        <td class="actions"><a href="{{ $p['url'] }}" class="btn btn-light btn-sm">Detail</a></td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="empty">{{ $semua ? 'Belum ada pengajuan.' : 'Tidak ada pengajuan yang sedang berjalan.' }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>

@endsection
