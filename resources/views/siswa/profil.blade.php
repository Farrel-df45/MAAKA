@extends('layouts.panel')

@section('title', 'Profil Saya | MAAKA')
@section('heading', 'Profil Saya')
@section('subheading', 'Data diri dan riwayat kelas Anda. Untuk perubahan data, hubungi Admin.')

@section('content')

@unless ($siswa)
    <div class="alert error">Akun ini belum terhubung ke data siswa. Hubungi Admin.</div>
@else
    <section class="card" style="margin-bottom:18px">
        <div class="card-head">
            <div><h2>{{ $siswa->nama }}</h2><p>NIS {{ $siswa->nis }}</p></div>
            @if ($lulus) <span class="badge success">Lulus</span> @endif
        </div>
        <dl class="detail-list">
            <div class="detail-row"><dt>Kelas saat ini</dt><dd>{{ $lulus ? 'Sudah lulus' : $siswa->kelas }}</dd></div>
            <div class="detail-row"><dt>Jurusan</dt><dd>{{ $siswa->jurusan ?? '-' }}</dd></div>
            <div class="detail-row"><dt>Alamat</dt><dd>{{ $siswa->alamat ?? '-' }}</dd></div>
            <div class="detail-row"><dt>Email akun</dt><dd>{{ auth()->user()->email }}</dd></div>
        </dl>
    </section>

    <section class="card">
        <div class="card-head">
            <div><h2>Riwayat Kelas</h2><p>Kelas dan wali kelas pada setiap tahun ajaran.</p></div>
        </div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Tahun ajaran</th><th>Kelas</th><th>Wali kelas</th><th>Dokumen</th></tr></thead>
                <tbody>
                    @forelse ($periode as $p)
                        <tr>
                            <td>{{ $p['riwayat']->tahunAjaran->nama }}
                                @if ($p['riwayat']->tahunAjaran->aktif)<span class="badge success">Aktif</span>@endif
                            </td>
                            <td>{{ $p['riwayat']->kelas->nama }}</td>
                            <td>{{ $p['wali']?->guru_nama ?? '-' }}</td>
                            <td>{{ $p['dokumen'] }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="empty">Belum ada riwayat kelas.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endunless

@endsection
