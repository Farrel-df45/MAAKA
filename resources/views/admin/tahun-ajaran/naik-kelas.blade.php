@extends('layouts.panel')

@section('title', 'Naik Kelas | MAAKA')
@section('heading', 'Proses Naik Kelas')
@section('subheading', 'Pindahkan siswa dan rolling wali kelas ke tahun ajaran baru. Riwayat tahun lama tetap tersimpan.')

@push('styles')
<style>
    .nk-section { padding: 22px; }
    .nk-section h3 { font-size: 15px; margin-bottom: 4px; }
    .nk-section p.hint { font-size: 13px; color: var(--muted); margin-bottom: 14px; }
    .nk-form select, .nk-form input[type=text] {
        width: 100%; padding: 9px 12px; border: 1px solid #cbd5e1; border-radius: 9px; font: inherit; background: #fff;
    }
    .nk-year { max-width: 280px; }
    .nk-section .error { margin-top: 5px; font-size: 13px; color: var(--red); }
</style>
@endpush

@section('content')

@unless ($tahun)
    <div class="alert error">Belum ada tahun ajaran aktif, proses naik kelas belum bisa dilakukan.</div>
@else

@if ($errors->any())
    <div class="alert error">
        @foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach
    </div>
@endif

<form method="POST" class="nk-form" action="{{ route('admin.naik-kelas.store') }}"
      data-confirm="Proses naik kelas? Tahun ajaran {{ $tahun->nama }} akan diarsipkan dan tahun ajaran baru menjadi aktif.">
    @csrf

    <section class="card" style="margin-bottom:18px">
        <div class="nk-section">
            <h3>1. Tahun ajaran baru</h3>
            <p class="hint">Tahun ajaran aktif saat ini: <b>{{ $tahun->nama }}</b>.</p>
            <div class="nk-year">
                <input type="text" name="tahun_baru" value="{{ old('tahun_baru', $namaSaran) }}" placeholder="2027/2028" required>
            </div>
        </div>
    </section>

    <section class="card" style="margin-bottom:18px">
        <div class="nk-section" style="padding-bottom:0">
            <h3>2. Kelas tujuan siswa</h3>
            <p class="hint">Pilih ke kelas mana siswa tiap kelas dipindahkan. Pilih "Lulus" untuk siswa yang menamatkan sekolah.</p>
        </div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Kelas saat ini</th><th>Siswa</th><th style="width:40%">Pindah ke</th></tr></thead>
                <tbody>
                    @forelse ($sumber as $k)
                        @php $pilih = old("tujuan.{$k->id}", $saran[$k->id] ?? ''); @endphp
                        <tr>
                            <td>{{ $k->nama }}</td>
                            <td>{{ $k->siswa_aktif_count }}</td>
                            <td>
                                <select name="tujuan[{{ $k->id }}]">
                                    <option value="">— Pilih tujuan —</option>
                                    <option value="lulus" @selected((string) $pilih === 'lulus')>Lulus</option>
                                    @foreach ($semuaKelas as $tujuan)
                                        <option value="{{ $tujuan->id }}" @selected((string) $pilih === (string) $tujuan->id)>{{ $tujuan->nama }}</option>
                                    @endforeach
                                </select>
                                @error("tujuan.{$k->id}") <div class="error">{{ $message }}</div> @enderror
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="empty">Belum ada siswa yang ditempatkan di kelas.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="card" style="margin-bottom:18px">
        <div class="nk-section" style="padding-bottom:0">
            <h3>3. Wali kelas tahun ajaran baru</h3>
            <p class="hint">Atur rolling guru. Satu guru hanya boleh menjadi wali satu kelas. Dikosongkan berarti belum ada wali.</p>
            @error('wali') <div class="error" style="margin-bottom:10px">{{ $message }}</div> @enderror
        </div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Kelas</th><th style="width:50%">Wali kelas</th></tr></thead>
                <tbody>
                    @foreach ($semuaKelas as $k)
                        @php $guruPilih = old("wali.{$k->id}", $waliSaatIni[$k->id] ?? ''); @endphp
                        <tr>
                            <td>{{ $k->nama }}</td>
                            <td>
                                <select name="wali[{{ $k->id }}]">
                                    <option value="">— Belum ada —</option>
                                    @foreach ($gurus as $g)
                                        <option value="{{ $g->id }}" @selected((string) $guruPilih === (string) $g->id)>{{ $g->nama }}{{ $g->nip ? ' (' . $g->nip . ')' : '' }}</option>
                                    @endforeach
                                </select>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    <div class="row-actions">
        <a href="{{ route('admin.tahun-ajaran.index') }}" class="btn btn-light">Batal</a>
        <button type="submit" class="btn btn-primary">Proses Naik Kelas</button>
    </div>
</form>

@endunless

@endsection
