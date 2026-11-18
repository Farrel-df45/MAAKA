@extends('layouts.panel')

@section('title', 'Permohonan Legalisir | MAAKA')
@section('heading', 'Permohonan Legalisir')
@section('subheading', 'Proses permohonan legalisir dokumen dari siswa.')

@section('content')

<div class="toolbar">
    <form method="GET" action="{{ route('admin.legalisir.index') }}" class="search">
        <input type="text" name="q" value="{{ $q }}" placeholder="Cari nama siswa, NIS, atau dokumen...">
        <select name="status" onchange="this.form.submit()">
            <option value="">Semua status</option>
            @foreach (\App\Models\Legalisir::STATUSES as $s)
                <option value="{{ $s }}" @selected($status === $s)>{{ $s }}</option>
            @endforeach
        </select>
        <button type="submit" class="btn btn-light">Cari</button>
        @if ($q !== '' || $status)
            <a href="{{ route('admin.legalisir.index') }}" class="btn btn-light">Reset</a>
        @endif
    </form>
</div>

<section class="card">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Tanggal</th>
                    <th>Siswa</th>
                    <th>Dokumen</th>
                    <th>Jumlah</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($legalisirs as $legalisir)
                    <tr>
                        <td>{{ $legalisir->created_at?->format('d M Y') }}</td>
                        <td>
                            {{ $legalisir->siswa?->nama ?? '-' }}
                            <small>{{ $legalisir->siswa?->nis }}</small>
                        </td>
                        <td>{{ $legalisir->dokumen?->nama_dokumen ?? '-' }}</td>
                        <td>{{ $legalisir->jumlah }} lembar</td>
                        <td>@include('admin.partials.badge', ['status' => $legalisir->status])</td>
                        <td class="actions">
                            <div class="row-actions">
                                <a href="{{ route('admin.legalisir.show', $legalisir) }}" class="btn btn-primary btn-sm">
                                    {{ $legalisir->status === \App\Models\Legalisir::STATUS_MENUNGGU ? 'Proses' : 'Detail' }}
                                </a>

                                <form method="POST" action="{{ route('admin.legalisir.destroy', $legalisir) }}"
                                      data-confirm="Hapus permohonan legalisir ini?&#10;&#10;Tindakan ini tidak dapat dibatalkan.">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-danger btn-sm">Hapus</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="empty">
                            @if ($q !== '' || $status)
                                Tidak ada permohonan yang cocok dengan pencarian/filter.
                            @else
                                Belum ada permohonan legalisir.
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @include('admin.partials.pager', ['paginator' => $legalisirs, 'noun' => 'permohonan'])
</section>

@endsection
