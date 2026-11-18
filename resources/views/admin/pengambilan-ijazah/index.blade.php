
@extends('layouts.panel')

@section('title', 'Pengambilan Ijazah | MAAKA')
@section('heading', 'Pengambilan Ijazah')
@section('subheading', 'Kelola dan pantau pengajuan pengambilan ijazah siswa.')

@push('styles')
<style>
    .filter-card {
        padding: 18px 20px;
        margin-bottom: 20px;
    }

    .filter-form {
        display: flex;
        align-items: end;
        gap: 12px;
        flex-wrap: wrap;
    }

    .filter-form .field {
        flex: 1;
        min-width: 180px;
    }

    .filter-actions {
        display: flex;
        gap: 8px;
    }

    .student-info strong {
        display: block;
    }

    .student-info small {
        display: block;
        color: var(--muted);
        margin-top: 3px;
    }

    .table-wrap {
        overflow-x: auto;
    }

    @media (max-width: 600px) {
        .filter-form {
            display: grid;
            grid-template-columns: 1fr;
        }

        .filter-form .field {
            min-width: 0;
        }
    }
</style>
@endpush

@section('content')

{{-- Filter dan pencarian --}}
<section class="card filter-card">
    <form method="GET"
          action="{{ route('admin.pengambilan-ijazah.index') }}"
          class="filter-form">

        <div class="field">
            <label for="q">Cari siswa</label>
            <input
                type="text"
                id="q"
                name="q"
                value="{{ $q }}"
                placeholder="Nama siswa atau NIS..."
            >
        </div>

        <div class="field">
            <label for="status">Filter status</label>
            <select name="status" id="status">
                <option value="">Semua status</option>

                @foreach (\App\Models\PengambilanIjazah::STATUSES as $s)
                    <option value="{{ $s }}" @selected($status === $s)>
                        {{ $s }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="filter-actions">
            <button type="submit" class="btn btn-primary">
                Cari
            </button>

            <a href="{{ route('admin.pengambilan-ijazah.index') }}"
               class="btn btn-light">
                Reset
            </a>
        </div>
    </form>
</section>

{{-- Daftar pengajuan --}}
<section class="card">
    <div class="card-head">
        <div>
            <h2>Daftar Pengajuan</h2>
            <p>
                Total {{ $pengambilans->total() }} pengajuan pengambilan ijazah.
            </p>
        </div>
    </div>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>No.</th>
                    <th>Siswa</th>
                    <th>Tanggal Pengambilan</th>
                    <th>Diambil Oleh</th>
                    <th>Status</th>
                    <th>Aksi</th>
                </tr>
            </thead>

            <tbody>
                @forelse ($pengambilans as $i => $p)
                    <tr>
                        <td>
                            {{ $pengambilans->firstItem() + $i }}
                        </td>

                        <td>
                            <div class="student-info">
                                <strong>{{ $p->siswa?->nama ?? '-' }}</strong>
                                <small>NIS {{ $p->siswa?->nis ?? '-' }}</small>
                            </div>
                        </td>

                        <td>
                            {{ $p->tanggal_pengambilan
                                ? \Carbon\Carbon::parse($p->tanggal_pengambilan)->format('d M Y')
                                : '-' }}
                        </td>

                        <td>
                            {{ $p->pengambil ?? '-' }}
                        </td>

                        <td>
                            @include('admin.partials.badge', [
                                'status' => $p->status
                            ])
                        </td>

                        <td class="actions">
                            <a href="{{ route('admin.pengambilan-ijazah.show', $p) }}"
                               class="btn btn-light btn-sm">
                                Detail
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="empty">
                            Belum ada pengajuan pengambilan ijazah.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($pengambilans->hasPages())
        <div style="padding: 18px 20px;">
            {{ $pengambilans->links() }}
        </div>
    @endif
</section>

@endsection
