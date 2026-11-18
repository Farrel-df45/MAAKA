@extends('layouts.panel')

@section('title', 'Kelengkapan Dokumen | MAAKA')
@section('heading', 'Kelengkapan Dokumen')
@section('subheading', 'Pantau siswa yang dokumen wajibnya sudah atau belum terverifikasi.')

@push('styles')
<style>
    .mini-stats { display: grid; grid-template-columns: repeat(3, 1fr); gap: 14px; margin-bottom: 18px; }
    .mini-stat { padding: 16px 18px; }
    .mini-stat span { font-size: 13px; color: var(--muted); }
    .mini-stat strong { display: block; margin-top: 6px; font-size: 26px; line-height: 1.1; }
    .mini-stat.warn strong { color: var(--red); }
    progress.bar { display: block; width: 100%; min-width: 90px; max-width: 160px; height: 8px; border: 0; border-radius: 999px; background: var(--line); overflow: hidden; appearance: none; -webkit-appearance: none; }
    progress.bar::-webkit-progress-bar { background: var(--line); border-radius: 999px; }
    progress.bar::-webkit-progress-value { background: var(--green); border-radius: 999px; }
    progress.bar::-moz-progress-bar { background: var(--green); border-radius: 999px; }
    progress.bar.low::-webkit-progress-value { background: var(--amber); }
    progress.bar.low::-moz-progress-bar { background: var(--amber); }
    .chip { display: inline-block; margin: 2px 4px 2px 0; padding: 2px 8px; border-radius: 999px; font-size: 12px; }
    .chip.kurang { background: var(--red-bg); color: var(--red); }
    .chip.menunggu { background: var(--amber-bg); color: var(--amber); }
    .chip.ditolak { background: var(--red-bg); color: var(--red); }
    @media (max-width: 700px) { .mini-stats { grid-template-columns: minmax(0, 1fr); } }
</style>
@endpush

@section('content')

<div class="mini-stats">
    <div class="card mini-stat"><span>Siswa aktif</span><strong>{{ $ringkasan['total'] }}</strong></div>
    <div class="card mini-stat"><span>Dokumen lengkap</span><strong>{{ $ringkasan['lengkap'] }}</strong></div>
    <div class="card mini-stat {{ $ringkasan['belum'] > 0 ? 'warn' : '' }}"><span>Belum lengkap</span><strong>{{ $ringkasan['belum'] }}</strong></div>
</div>

<div class="toolbar">
    <form method="GET" action="{{ route('admin.kelengkapan.index') }}" class="search">
        <input type="text" name="q" value="{{ $q }}" placeholder="Cari nama atau NIS...">
        <select name="kelas_id" onchange="this.form.submit()">
            <option value="">Semua kelas</option>
            @foreach ($daftarKelas as $k)
                <option value="{{ $k->id }}" @selected($kelasId === $k->id)>{{ $k->nama }}</option>
            @endforeach
        </select>
        <select name="filter" onchange="this.form.submit()">
            <option value="">Semua</option>
            <option value="belum" @selected($filter === 'belum')>Belum lengkap</option>
            <option value="lengkap" @selected($filter === 'lengkap')>Lengkap</option>
        </select>
        <button type="submit" class="btn btn-light">Cari</button>
        @if ($q !== '' || $kelasId || $filter)
            <a href="{{ route('admin.kelengkapan.index') }}" class="btn btn-light">Reset</a>
        @endif
    </form>
    <a href="{{ route('admin.ekspor', array_merge(['jenis' => 'kelengkapan'], request()->query())) }}" class="btn btn-light">Ekspor CSV</a>
</div>

<p class="hint" style="margin:0 0 12px">
    Dokumen wajib:
    @foreach ($wajibPerTingkat as $tingkat => $daftar)
        <strong>{{ $tingkat }}</strong>: {{ implode(', ', $daftar) }}@unless ($loop->last) · @endunless
    @endforeach
    . Hanya dokumen berstatus Terverifikasi yang dihitung lengkap.
</p>

<section class="card">
    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>Siswa</th><th>Kelas</th><th>Kelengkapan</th><th>Yang masih kurang</th><th></th></tr>
            </thead>
            <tbody>
                @forelse ($baris as $r)
                    @php $h = $r['hasil']; @endphp
                    <tr>
                        <td>{{ $r['siswa']->nama }}<small>{{ $r['siswa']->nis }}</small></td>
                        <td>{{ $r['kelas']?->nama ?? '-' }}</td>
                        <td>
                            <progress class="bar {{ $h['persen'] < 100 ? 'low' : '' }}" max="100" value="{{ $h['persen'] }}">{{ $h['persen'] }}%</progress>
                            <small>{{ count($h['terpenuhi']) }} dari {{ count($h['wajib']) }} · {{ $h['persen'] }}%</small>
                        </td>
                        <td>
                            @forelse ($h['kurang'] as $k) <span class="chip kurang">{{ $k }}: belum ada</span> @empty @endforelse
                            @foreach ($h['menunggu'] as $k) <span class="chip menunggu">{{ $k }}: menunggu verifikasi</span> @endforeach
                            @foreach ($h['ditolak'] as $k) <span class="chip ditolak">{{ $k }}: ditolak</span> @endforeach
                            @if ($h['lengkap']) <span class="badge success">Lengkap</span> @endif
                        </td>
                        <td class="actions">
                            <a href="{{ route('admin.siswa.riwayat', $r['siswa']) }}" class="btn btn-light btn-sm">Riwayat</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="empty">Tidak ada siswa yang sesuai.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @include('admin.partials.pager', ['paginator' => $baris, 'noun' => 'siswa'])
</section>

@endsection