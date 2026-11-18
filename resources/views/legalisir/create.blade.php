@extends('layouts.app')

@section('title', 'Ajukan Legalisir - MAAKA')

@section('content')

<div class="page-header">
    <div>
        <span class="eyebrow">Layanan Akademik</span>
        <h1>Ajukan Legalisir</h1>
        <p>Ajukan permohonan legalisir dokumen akademik.</p>
    </div>
</div>

<div class="form-panel">

    <div class="form-title">
        <div class="form-title-icon">▤</div>
        <div>
            <h2>Form Pengajuan Legalisir</h2>
            <p>Lengkapi data pengajuan dengan benar.</p>
        </div>
    </div>

    <form action="{{ route('legalisir.store') }}" method="POST">
        @csrf

        <div class="form-grid">

            <div class="form-group">
                <label for="siswa_id">Siswa</label>

                <select name="siswa_id" id="siswa_id" required>
                    <option value="">Pilih siswa</option>

                    @foreach($siswas as $siswa)
                        <option value="{{ $siswa->id }}">
                            {{ $siswa->nama }} - {{ $siswa->nis }}
                        </option>
                    @endforeach
                </select>

                @error('siswa_id')
                    <span class="form-error">{{ $message }}</span>
                @enderror
            </div>

            <div class="form-group">
                <label for="dokumen_id">Dokumen</label>

                <select name="dokumen_id" id="dokumen_id" required disabled>
                    <option value="">Pilih siswa terlebih dahulu</option>
                </select>

                @error('dokumen_id')
                    <span class="form-error">{{ $message }}</span>
                @enderror
            </div>

            <div class="form-group">
                <label for="jumlah">Jumlah Salinan</label>

                <input
                    type="number"
                    name="jumlah"
                    id="jumlah"
                    value="{{ old('jumlah', 1) }}"
                    min="1"
                    required
                >

                @error('jumlah')
                    <span class="form-error">{{ $message }}</span>
                @enderror
            </div>

            <div class="form-group full">
                <label for="keperluan">Keperluan Legalisir</label>

                <textarea
                    name="keperluan"
                    id="keperluan"
                    rows="5"
                    placeholder="Contoh: Persyaratan administrasi pendaftaran perguruan tinggi..."
                    required
                >{{ old('keperluan') }}</textarea>

                @error('keperluan')
                    <span class="form-error">{{ $message }}</span>
                @enderror
            </div>

        </div>

        <div class="form-info">
            <strong>Informasi</strong>
            <span>
                Pastikan dokumen yang dipilih sudah berstatus Terverifikasi sebelum mengajukan legalisir.
            </span>
        </div>

        <div class="form-actions">
            <a href="{{ route('dashboard') }}" class="btn-secondary">
                Batal
            </a>

            <button type="submit" class="btn-primary">
                Ajukan Legalisir
            </button>
        </div>
    </form>
</div>

@endsection

@push('scripts')
<script>
const siswaSelect = document.getElementById('siswa_id');
const dokumenSelect = document.getElementById('dokumen_id');

siswaSelect.addEventListener('change', async function () {
    const siswaId = this.value;

    dokumenSelect.innerHTML = '<option value="">Memuat dokumen...</option>';
    dokumenSelect.disabled = true;

    if (!siswaId) {
        dokumenSelect.innerHTML = '<option value="">Pilih siswa terlebih dahulu</option>';
        return;
    }

    const response = await fetch(`/siswa/${siswaId}/dokumen`);
    const dokumens = await response.json();

    dokumenSelect.innerHTML = '<option value="">Pilih dokumen</option>';

    dokumens.forEach(dokumen => {
        const option = document.createElement('option');

        option.value = dokumen.id;
        option.textContent = `${dokumen.nama_dokumen} - ${dokumen.kategori}`;

        dokumenSelect.appendChild(option);
    });

    dokumenSelect.disabled = dokumens.length === 0;

    if (dokumens.length === 0) {
        dokumenSelect.innerHTML = '<option value="">Tidak ada dokumen terverifikasi</option>';
    }
});
</script>
@endpush