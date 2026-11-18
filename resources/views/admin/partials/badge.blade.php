@php
    $class = match ($status) {
        'Terverifikasi', 'Selesai', 'Unggah', 'Dikembalikan', 'Sudah Diambil' => 'success',
        'Ditolak', 'Hapus', 'Terlambat'      => 'danger',
        'Diproses', 'Ubah', 'Disetujui', 'Dipinjam' => 'info',
        'Dibatalkan'                         => 'muted',
        default                    => 'pending',
    };
@endphp
<span class="badge {{ $class }}">{{ $label ?? $status }}</span>
