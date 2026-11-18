@extends('layouts.panel')

@section('title', 'Ajukan Legalisir | MAAKA')
@section('heading', 'Ajukan Legalisir')
@section('subheading', 'Ajukan legalisir untuk dokumen yang sudah terverifikasi.')

@section('content')

@unless ($siswa)
    <div class="alert error">Akun ini belum terhubung ke data siswa. Hubungi Admin.</div>
@endunless

<section class="card form-card" style="margin-bottom:18px">
    <form method="POST" action="{{ route('siswa.legalisir.store') }}">
        @csrf

        <div class="form-body">
            <div class="field full">
                <label for="dokumen_id">Dokumen</label>
                <select id="dokumen_id" name="dokumen_id" class="{{ $errors->has('dokumen_id') ? 'invalid' : '' }}" required>
                    <option value="">— Pilih dokumen —</option>
                    @foreach ($dokumens as $d)
                        <option value="{{ $d->id }}" @selected((string) old('dokumen_id') === (string) $d->id)>{{ $d->nama_dokumen }} ({{ $d->kategori }})</option>
                    @endforeach
                </select>
                @error('dokumen_id') <div class="error">{{ $message }}</div> @enderror
                @if ($siswa && $dokumens->isEmpty())
                    <div class="hint">Belum ada dokumen yang bisa diajukan. Dokumen harus berstatus Terverifikasi dan belum punya pengajuan yang berjalan.</div>
                @endif
            </div>

            <div class="field">
                <label for="jumlah">Jumlah salinan</label>
                <input id="jumlah" type="number" name="jumlah" min="1" max="10" value="{{ old('jumlah', 1) }}"
                       class="{{ $errors->has('jumlah') ? 'invalid' : '' }}" required>
                @error('jumlah') <div class="error">{{ $message }}</div> @enderror
            </div>

            <div class="field full">
                <label for="keperluan">Keperluan</label>
                <input id="keperluan" type="text" name="keperluan" value="{{ old('keperluan') }}" maxlength="500"
                       placeholder="Contoh: Melamar kerja / mendaftar kuliah"
                       class="{{ $errors->has('keperluan') ? 'invalid' : '' }}" required>
                @error('keperluan') <div class="error">{{ $message }}</div> @enderror
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary" @disabled(! $siswa || $dokumens->isEmpty())>Kirim Pengajuan</button>
        </div>
    </form>
</section>

<section class="card">
    <div class="card-head">
        <div><h2>Pengajuan Saya</h2><p>Pantau status permohonan legalisir Anda.</p></div>
    </div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Tanggal</th><th>Dokumen</th><th>Jumlah</th><th>Keperluan</th><th>Status</th><th>Catatan Admin</th><th></th></tr></thead>
            <tbody>
                @forelse ($legalisirs as $l)
                    <tr>
                        <td>{{ $l->created_at->format('d M Y') }}</td>
                        <td>{{ $l->dokumen?->nama_dokumen ?? '-' }}</td>
                        <td>{{ $l->jumlah }}</td>
                        <td>{{ $l->keperluan }}</td>
                        <td>@include('admin.partials.badge', ['status' => $l->status])</td>
                        <td>{{ $l->catatan ?? '-' }}</td>
                        <td class="actions">
                            @if ($l->status === \App\Models\Legalisir::STATUS_MENUNGGU)
                                <form method="POST" action="{{ route('siswa.legalisir.batal', $l) }}" data-confirm="Batalkan pengajuan legalisir ini?">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="btn btn-light btn-sm">Batalkan</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="empty">Belum ada pengajuan.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @include('admin.partials.pager', ['paginator' => $legalisirs, 'noun' => 'pengajuan'])
</section>

@endsection
