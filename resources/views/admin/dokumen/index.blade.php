@extends('layouts.panel')

@section('title', 'Dokumen Akademik | MAAKA')
@section('heading', 'Dokumen Akademik')
@section('subheading', 'Verifikasi dan kelola dokumen akademik siswa.')

@section('content')

<div class="toolbar">
    <form method="GET" action="{{ route('admin.dokumen.index') }}" class="search">
        <input type="text" name="q" value="{{ $q }}" placeholder="Cari dokumen, nama siswa, atau NIS...">
        <select name="status" onchange="this.form.submit()">
            <option value="">Semua status</option>
            @foreach (\App\Models\Dokumen::STATUSES as $s)
                <option value="{{ $s }}" @selected($status === $s)>{{ $s }}</option>
            @endforeach
        </select>
        <select name="kategori" onchange="this.form.submit()">
            <option value="">Semua kategori</option>
            @foreach (\App\Models\Dokumen::KATEGORI as $k)
                <option value="{{ $k }}" @selected($kategori === $k)>{{ $k }}</option>
            @endforeach
        </select>
        <button type="submit" class="btn btn-light">Cari</button>
        @if ($q !== '' || $status || $kategori)
            <a href="{{ route('admin.dokumen.index') }}" class="btn btn-light">Reset</a>
        @endif
    </form>

    <div class="row-actions">
        <a href="{{ route('admin.ekspor', 'dokumen') }}" class="btn btn-light">Ekspor CSV</a>
        <a href="{{ route('admin.dokumen.create') }}" class="btn btn-primary">+ Tambah Dokumen</a>
    </div>
</div>

@error('ids') <div class="alert error">{{ $message }}</div> @enderror
@error('catatan') <div class="alert error">{{ $message }}</div> @enderror

<form id="form-massal" method="POST" action="{{ route('admin.dokumen.massal') }}">
    @csrf
    <div class="toolbar" id="bar-massal" style="margin-bottom:10px">
        <span class="hint" style="margin:0"><strong id="jml-dipilih">0</strong> dokumen dipilih</span>
        <div class="row-actions">
            <input type="text" name="catatan" maxlength="500" placeholder="Alasan penolakan (wajib bila menolak)" style="flex:1 1 220px;min-width:0;padding:9px 12px;border:1px solid var(--line);border-radius:9px;background:var(--card);font:inherit">
            <button type="submit" name="aksi" value="verifikasi" class="btn btn-success btn-sm" data-massal>Verifikasi terpilih</button>
            <button type="submit" name="aksi" value="tolak" class="btn btn-danger btn-sm" data-massal>Tolak terpilih</button>
        </div>
    </div>
</form>

<section class="card">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th style="width:32px"><input type="checkbox" id="pilih-semua" aria-label="Pilih semua"></th>
                    <th>Dokumen</th>
                    <th>Siswa</th>
                    <th>Kategori</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($dokumens as $dokumen)
                    <tr>
                        <td><input type="checkbox" class="pilih" name="ids[]" value="{{ $dokumen->id }}" form="form-massal" aria-label="Pilih {{ $dokumen->nama_dokumen }}"></td>
                        <td>
                            {{ $dokumen->nama_dokumen }}
                            <small>{{ $dokumen->created_at?->format('d M Y') }}@if ($dokumen->pengunggah) · oleh {{ $dokumen->pengunggah->name }} ({{ ['guru' => 'Guru', 'siswa' => 'Siswa'][$dokumen->pengunggah->role] ?? 'Admin' }})@endif</small>
                        </td>
                        <td>
                            {{ $dokumen->siswa?->nama ?? '-' }}
                            <small>{{ $dokumen->siswa?->nis }}</small>
                        </td>
                        <td>{{ $dokumen->kategori }}</td>
                        <td>@include('admin.partials.badge', ['status' => $dokumen->status, 'label' => $dokumen->status === \App\Models\Dokumen::STATUS_MENUNGGU ? 'Menunggu' : $dokumen->status])@if ($dokumen->status === \App\Models\Dokumen::STATUS_DITOLAK && $dokumen->catatan)<small>{{ $dokumen->catatan }}</small>@endif</td>
                        <td class="actions">
                            <div class="row-actions">
                                <a href="{{ route('admin.dokumen.berkas', $dokumen) }}" target="_blank" class="btn btn-light btn-sm">Lihat</a>

                                @if ($dokumen->status !== \App\Models\Dokumen::STATUS_TERVERIFIKASI)
                                    <form method="POST" action="{{ route('admin.dokumen.status', $dokumen) }}">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="status" value="{{ \App\Models\Dokumen::STATUS_TERVERIFIKASI }}">
                                        <button type="submit" class="btn btn-success btn-sm">Verifikasi</button>
                                    </form>
                                @endif

                                @if ($dokumen->status === \App\Models\Dokumen::STATUS_MENUNGGU)
                                    <form method="POST" action="{{ route('admin.dokumen.status', $dokumen) }}"
                                          data-confirm="Tolak dokumen &quot;{{ $dokumen->nama_dokumen }}&quot;?">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="status" value="{{ \App\Models\Dokumen::STATUS_DITOLAK }}">
                                        <button type="submit" class="btn btn-danger btn-sm">Tolak</button>
                                    </form>
                                @endif

                                <a href="{{ route('admin.dokumen.edit', $dokumen) }}" class="btn btn-light btn-sm">Ubah</a>

                                <form method="POST" action="{{ route('admin.dokumen.destroy', $dokumen) }}"
                                      data-confirm="Hapus dokumen &quot;{{ $dokumen->nama_dokumen }}&quot;?&#10;&#10;Berkas dan pengajuan legalisir terkait juga akan ikut terhapus.&#10;&#10;Tindakan ini tidak dapat dibatalkan.">
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
                            @if ($q !== '' || $status || $kategori)
                                Tidak ada dokumen yang cocok dengan pencarian/filter.
                            @else
                                Belum ada dokumen. Klik "Tambah Dokumen" untuk mulai.
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @include('admin.partials.pager', ['paginator' => $dokumens, 'noun' => 'dokumen'])
</section>

@push('scripts')
<script>
    (function () {
        var semua = document.getElementById('pilih-semua');
        var kotak = function () { return Array.prototype.slice.call(document.querySelectorAll('.pilih')); };
        var hitung = function () {
            document.getElementById('jml-dipilih').textContent = kotak().filter(function (c) { return c.checked; }).length;
        };
        if (semua) semua.addEventListener('change', function () { kotak().forEach(function (c) { c.checked = semua.checked; }); hitung(); });
        kotak().forEach(function (c) { c.addEventListener('change', hitung); });
        document.getElementById('form-massal').addEventListener('submit', function (e) {
            var n = kotak().filter(function (c) { return c.checked; }).length;
            var aksi = e.submitter ? e.submitter.value : '';
            if (n === 0) { e.preventDefault(); alert('Pilih minimal satu dokumen.'); return; }
            if (aksi === 'tolak' && !this.catatan.value.trim()) { e.preventDefault(); alert('Isi alasan penolakan.'); return; }
            if (!confirm((aksi === 'tolak' ? 'Tolak ' : 'Verifikasi ') + n + ' dokumen?')) e.preventDefault();
        });
    })();
</script>
@endpush

@endsection
