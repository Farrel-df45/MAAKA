@extends('layouts.panel')

@section('title', 'Ambil Ijazah Asli | MAAKA')
@section('heading', 'Ambil Ijazah Asli')
@section('subheading', 'Jadwalkan pengambilan ijazah asli di sekolah. Khusus siswa yang sudah lulus.')

@push('styles')
<style>
    .wakil-fields { display: none; }
    .wakil-fields.show { display: contents; }
</style>
@endpush

@section('content')

@unless ($siswa)
    <div class="alert error">Akun ini belum terhubung ke data siswa. Hubungi Admin.</div>
@endunless

@if ($siswa && ! $lulus)
    <div class="alert error">Pengambilan ijazah asli hanya dapat diajukan oleh siswa yang sudah lulus.</div>
@elseif ($sudahAda)
    <div class="alert error">Anda sudah punya pengajuan pengambilan ijazah yang berjalan atau sudah selesai.</div>
@endif

<section class="card form-card" style="margin-bottom:18px">
    <form method="POST" action="{{ route('siswa.ijazah.store') }}">
        @csrf
        @php $pengambil = old('pengambil', 'Sendiri'); @endphp

        <div class="form-body">
            <div class="field">
                <label for="tanggal_pengambilan">Tanggal pengambilan</label>
                <input id="tanggal_pengambilan" type="date" name="tanggal_pengambilan" min="{{ now()->toDateString() }}"
                       value="{{ old('tanggal_pengambilan') }}" class="{{ $errors->has('tanggal_pengambilan') ? 'invalid' : '' }}" required>
                @error('tanggal_pengambilan') <div class="error">{{ $message }}</div> @enderror
            </div>

            <div class="field">
                <label for="pengambil">Diambil oleh</label>
                <select id="pengambil" name="pengambil" class="{{ $errors->has('pengambil') ? 'invalid' : '' }}" required>
                    @foreach (\App\Models\PengambilanIjazah::PENGAMBIL as $p)
                        <option value="{{ $p }}" @selected($pengambil === $p)>{{ $p === 'Sendiri' ? 'Saya sendiri' : 'Diwakilkan' }}</option>
                    @endforeach
                </select>
                @error('pengambil') <div class="error">{{ $message }}</div> @enderror
            </div>

            <div class="wakil-fields {{ $pengambil === 'Diwakilkan' ? 'show' : '' }}" id="wakil-fields">
                <div class="field">
                    <label for="nama_wakil">Nama perwakilan</label>
                    <input id="nama_wakil" type="text" name="nama_wakil" value="{{ old('nama_wakil') }}" class="{{ $errors->has('nama_wakil') ? 'invalid' : '' }}">
                    @error('nama_wakil') <div class="error">{{ $message }}</div> @enderror
                </div>
                <div class="field">
                    <label for="hubungan_wakil">Hubungan dengan Anda</label>
                    <input id="hubungan_wakil" type="text" name="hubungan_wakil" value="{{ old('hubungan_wakil') }}" placeholder="Contoh: Orang tua"
                           class="{{ $errors->has('hubungan_wakil') ? 'invalid' : '' }}">
                    @error('hubungan_wakil') <div class="error">{{ $message }}</div> @enderror
                </div>
            </div>

            <div class="field full">
                <label for="keterangan">Keterangan <span style="font-weight:400;color:var(--muted)">(opsional)</span></label>
                <input id="keterangan" type="text" name="keterangan" value="{{ old('keterangan') }}" maxlength="500">
                @error('keterangan') <div class="error">{{ $message }}</div> @enderror
                <div class="hint">Bawa kartu identitas saat mengambil. Jika diwakilkan, perwakilan membawa surat kuasa dan identitasnya.</div>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary" @disabled(! $siswa || ! $lulus || $sudahAda)>Kirim Pengajuan</button>
        </div>
    </form>
</section>

<section class="card">
    <div class="card-head">
        <div><h2>Pengajuan Saya</h2><p>Pantau status pengambilan ijazah.</p></div>
    </div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Tanggal</th><th>Pengambil</th><th>Keterangan</th><th>Status</th><th></th></tr></thead>
            <tbody>
                @forelse ($pengambilans as $p)
                    <tr>
                        <td>{{ $p->tanggal_pengambilan->format('d M Y') }}
                            @if ($p->diambil_pada)<small>Diambil {{ $p->diambil_pada->format('d M Y') }}</small>@endif
                        </td>
                        <td>{{ $p->pengambil }}@if ($p->nama_wakil)<small>{{ $p->nama_wakil }} ({{ $p->hubungan_wakil }})</small>@endif</td>
                        <td>{{ $p->keterangan ?? '-' }}@if ($p->catatan)<small>Catatan: {{ $p->catatan }}</small>@endif</td>
                        <td>@include('admin.partials.badge', ['status' => $p->status])</td>
                        <td class="actions">
                            @if ($p->status === \App\Models\PengambilanIjazah::STATUS_MENUNGGU)
                                <form method="POST" action="{{ route('siswa.ijazah.batal', $p) }}" data-confirm="Batalkan pengajuan pengambilan ijazah ini?">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="btn btn-light btn-sm">Batalkan</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="empty">Belum ada pengajuan.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @include('admin.partials.pager', ['paginator' => $pengambilans, 'noun' => 'pengajuan'])
</section>

<script>
    // Tampilkan isian perwakilan hanya bila "Diwakilkan"
    document.getElementById('pengambil').addEventListener('change', function () {
        document.getElementById('wakil-fields').classList.toggle('show', this.value === 'Diwakilkan');
    });
</script>

@endsection
