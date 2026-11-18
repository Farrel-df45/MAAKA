@extends('layouts.panel')

@section('title', 'Data Siswa | MAAKA')
@section('heading', 'Data Siswa')
@section('subheading', 'Kelola data siswa yang terdaftar di sistem.')

@push('styles')
<style>
    .btn {
        display: inline-flex; align-items: center; justify-content: center; gap: 6px;
        padding: 9px 16px;
        border: 1px solid transparent;
        border-radius: 9px;
        font-size: 14px; font-weight: 600; font-family: inherit;
        cursor: pointer;
        white-space: nowrap;
    }
    .btn-primary { background: var(--blue); color: #fff; }
    .btn-primary:hover { background: #1e40af; }
    .btn-light { background: #fff; color: var(--text); border-color: var(--line); }
    .btn-light:hover { background: #f1f5fb; }
    .btn-danger { background: #fff; color: var(--red); border-color: #fecaca; }
    .btn-danger:hover { background: var(--red-bg); }
    .btn-sm { padding: 6px 12px; font-size: 13px; }

    .toolbar {
        display: flex; align-items: center; justify-content: space-between; gap: 12px;
        flex-wrap: wrap;
        margin-bottom: 18px;
    }
    .search { display: flex; gap: 8px; flex: 1; max-width: 420px; }
    .search input {
        flex: 1; min-width: 0;
        padding: 9px 14px;
        border: 1px solid var(--line);
        border-radius: 9px;
        font-size: 14px; font-family: inherit;
        background: #fff;
    }
    .search input:focus { outline: none; border-color: var(--accent); box-shadow: 0 0 0 3px rgba(59,130,246,.15); }

    .row-actions { display: flex; gap: 8px; justify-content: flex-end; }
    .row-actions form { display: inline; }
    td.actions { text-align: right; }

    .pager {
        display: flex; align-items: center; justify-content: space-between; gap: 12px;
        padding: 14px 22px;
        border-top: 1px solid var(--line);
        font-size: 13px; color: var(--muted);
    }
    .pager-buttons { display: flex; gap: 8px; }
    .btn.is-disabled { opacity: .45; pointer-events: none; }
</style>
@endpush

@section('content')

<div class="toolbar">
    <form method="GET" action="{{ route('admin.siswa.index') }}" class="search">
        <input type="text" name="q" value="{{ $q }}" placeholder="Cari nama atau NIS...">
        <select name="kelas_id" onchange="this.form.submit()">
            <option value="">Semua kelas</option>
            @foreach ($daftarKelas as $k)
                <option value="{{ $k->id }}" @selected((int) $kelasId === $k->id)>{{ $k->nama }}</option>
            @endforeach
        </select>
        <button type="submit" class="btn btn-light">Cari</button>
        @if ($q !== '' || $kelasId)
            <a href="{{ route('admin.siswa.index') }}" class="btn btn-light">Reset</a>
        @endif
    </form>

    <div class="row-actions">
        <a href="{{ route('admin.ekspor', 'siswa') }}" class="btn btn-light">Ekspor CSV</a>
        <a href="{{ route('admin.impor.create', 'siswa') }}" class="btn btn-light">Impor CSV</a>
        <a href="{{ route('admin.siswa.create') }}" class="btn btn-primary">+ Tambah Siswa</a>
    </div>
</div>

<section class="card">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>NIS</th>
                    <th>Nama</th>
                    <th>Kelas</th>
                    <th>Jurusan</th>
                    <th>Dokumen</th>
                    <th>Akun Login</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($siswas as $siswa)
                    <tr>
                        <td>{{ $siswa->nis }}</td>
                        <td>
                            {{ $siswa->nama }}
                            @if ($siswa->alamat)
                                <small>{{ $siswa->alamat }}</small>
                            @endif
                        </td>
                        <td>{{ $siswa->kelas }}</td>
                        <td>{{ $siswa->jurusan }}</td>
                        <td>{{ $siswa->dokumens_count }}</td>
                        <td>@if ($siswa->user)<span class="badge success">Ada</span><small>Login: {{ $siswa->nis }}</small>@else<span class="badge pending">Belum</span>@endif</td>
                        <td class="actions">
                            <div class="row-actions">
                                <a href="{{ route('admin.siswa.riwayat', $siswa) }}" class="btn btn-light btn-sm">Riwayat</a>
                                <a href="{{ route('admin.siswa.edit', $siswa) }}" class="btn btn-light btn-sm">Ubah</a>

                                <form method="POST" action="{{ route('admin.siswa.destroy', $siswa) }}"
                                      data-nama="{{ $siswa->nama }}"
                                      data-dokumen="{{ $siswa->dokumens_count }}"
                                      class="form-hapus">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-danger btn-sm">Hapus</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="empty">
                            @if ($q !== '')
                                Tidak ada siswa yang cocok dengan "{{ $q }}".
                            @else
                                Belum ada data siswa. Klik "Tambah Siswa" untuk mulai.
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($siswas->total() > 0)
        <div class="pager">
            <span>
                Menampilkan {{ $siswas->firstItem() }}–{{ $siswas->lastItem() }} dari {{ $siswas->total() }} siswa
            </span>

            @if ($siswas->hasPages())
                <div class="pager-buttons">
                    <a href="{{ $siswas->previousPageUrl() ?? '#' }}"
                       class="btn btn-light btn-sm {{ $siswas->onFirstPage() ? 'is-disabled' : '' }}">‹ Sebelumnya</a>
                    <a href="{{ $siswas->nextPageUrl() ?? '#' }}"
                       class="btn btn-light btn-sm {{ $siswas->hasMorePages() ? '' : 'is-disabled' }}">Berikutnya ›</a>
                </div>
            @endif
        </div>
    @endif
</section>

@endsection

@push('scripts')
<script>
    // Konfirmasi sebelum menghapus siswa
    document.querySelectorAll('.form-hapus').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            var nama = form.dataset.nama;
            var dokumen = parseInt(form.dataset.dokumen, 10);
            var pesan = 'Hapus siswa "' + nama + '"?';

            if (dokumen > 0) {
                pesan += '\n\n' + dokumen + ' dokumen milik siswa ini beserta pengajuan legalisirnya juga akan ikut terhapus.';
            }
            pesan += '\n\nTindakan ini tidak dapat dibatalkan.';

            if (!confirm(pesan)) {
                e.preventDefault();
            }
        });
    });
</script>
@endpush