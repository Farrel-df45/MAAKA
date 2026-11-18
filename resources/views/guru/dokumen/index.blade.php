@extends('layouts.panel')

@section('title', 'Dokumen Saya | MAAKA')
@section('heading', 'Dokumen Kelas')
@section('subheading', $wali
    ? 'Dokumen siswa kelas ' . $wali->kelas->nama . ' (' . $wali->tahunAjaran->nama . '). Perubahan dan penghapusan tercatat untuk Admin.'
    : 'Anda belum ditugaskan sebagai wali kelas.')

@section('content')

@unless ($wali)
    <div class="alert error">Anda belum ditugaskan sebagai wali kelas pada tahun ajaran aktif, jadi belum ada siswa atau dokumen yang dapat diakses. Hubungi Admin.</div>
@endunless

<div class="toolbar">
    <form method="GET" action="{{ route('guru.dokumen.index') }}" class="search">
        <input type="text" name="q" value="{{ $q }}" placeholder="Cari dokumen, nama siswa, atau NIS...">
        <select name="status" onchange="this.form.submit()">
            <option value="">Semua status</option>
            @foreach (\App\Models\Dokumen::STATUSES as $s)
                <option value="{{ $s }}" @selected($status === $s)>{{ $s }}</option>
            @endforeach
        </select>
        <button type="submit" class="btn btn-light">Cari</button>
        @if ($q !== '' || $status)
            <a href="{{ route('guru.dokumen.index') }}" class="btn btn-light">Reset</a>
        @endif
    </form>

    <a href="{{ route('guru.dokumen.create') }}" class="btn btn-primary">+ Unggah Dokumen</a>
</div>

<section class="card">
    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>Dokumen</th><th>Siswa</th><th>Kategori</th><th>Status</th><th></th></tr>
            </thead>
            <tbody>
                @forelse ($dokumens as $dokumen)
                    <tr>
                        <td>{{ $dokumen->nama_dokumen }}<small>{{ $dokumen->created_at?->format('d M Y') }}</small></td>
                        <td>{{ $dokumen->siswa?->nama ?? '-' }}<small>{{ $dokumen->siswa?->nis }}</small></td>
                        <td>{{ $dokumen->kategori }}</td>
                        <td>@include('admin.partials.badge', ['status' => $dokumen->status, 'label' => $dokumen->status === \App\Models\Dokumen::STATUS_MENUNGGU ? 'Menunggu' : $dokumen->status])</td>
                        <td class="actions">
                            <div class="row-actions">
                                <a href="{{ route('guru.dokumen.berkas', $dokumen) }}" target="_blank" class="btn btn-light btn-sm">Lihat</a>
                                @if ($dokumen->diunggah_oleh === auth()->id())
                                    <a href="{{ route('guru.dokumen.edit', $dokumen) }}" class="btn btn-light btn-sm">Ubah</a>
                                    <form method="POST" action="{{ route('guru.dokumen.destroy', $dokumen) }}"
                                          data-confirm="Hapus dokumen &quot;{{ $dokumen->nama_dokumen }}&quot;?&#10;&#10;Berkas dan pengajuan legalisir terkait juga akan ikut terhapus. Aksi ini tercatat di riwayat Admin.">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-danger btn-sm">Hapus</button>
                                    </form>
                                @else
                                    <small>Diunggah {{ $dokumen->pengunggah?->name ?? 'Admin' }}</small>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="empty">
                            @if ($q !== '' || $status)
                                Tidak ada dokumen yang cocok dengan pencarian/filter.
                            @else
                                Belum ada dokumen untuk kelas ini. Klik "Unggah Dokumen" untuk mulai.
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @include('admin.partials.pager', ['paginator' => $dokumens, 'noun' => 'dokumen'])
</section>

@endsection
