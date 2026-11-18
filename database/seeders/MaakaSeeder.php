<?php

namespace Database\Seeders;

use App\Models\Dokumen;
use App\Models\Kelas;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use Illuminate\Database\Seeder;

class MaakaSeeder extends Seeder
{
    public function run(): void
    {
        // Tahun ajaran aktif dibuat oleh migrasi; buat bila belum ada
        $tahun = TahunAjaran::aktif() ?? TahunAjaran::create(['nama' => now()->year . '/' . (now()->year + 1), 'aktif' => true]);
        $kelasA = Kelas::firstOrCreate(['nama' => 'XII RPL A'], ['tingkat' => 'XII', 'jurusan' => 'Rekayasa Perangkat Lunak']);
        $kelasB = Kelas::firstOrCreate(['nama' => 'XII RPL B'], ['tingkat' => 'XII', 'jurusan' => 'Rekayasa Perangkat Lunak']);

        // Aman dijalankan berulang: data yang sudah ada dilewati, tidak dibuat ganda
        $andi = Siswa::firstOrCreate(['nis' => '2026001'], [
            'nama' => 'Andi Pratama',
            'kelas' => 'XII RPL A',
            'jurusan' => 'Rekayasa Perangkat Lunak',
            'alamat' => 'Surakarta',
        ]);

        $budi = Siswa::firstOrCreate(['nis' => '2026002'], [
            'nama' => 'Budi Santoso',
            'kelas' => 'XII RPL B',
            'jurusan' => 'Rekayasa Perangkat Lunak',
            'alamat' => 'Boyolali',
        ]);

        $andi->tempatkanDiKelas($kelasA, $tahun);
        $budi->tempatkanDiKelas($kelasB, $tahun);

        Dokumen::firstOrCreate([
            'siswa_id' => $andi->id,
            'nama_dokumen' => 'Rapor Semester 6',
        ], [
            'kategori' => 'Rapor',
            'file_path' => 'dokumen/rapor-andi.pdf',
            'status' => 'Terverifikasi',
        ]);

        Dokumen::firstOrCreate([
            'siswa_id' => $andi->id,
            'nama_dokumen' => 'Sertifikat Kompetensi',
        ], [
            'kategori' => 'Sertifikat',
            'file_path' => 'dokumen/sertifikat-andi.pdf',
            'status' => 'Menunggu Verifikasi',
        ]);

        Dokumen::firstOrCreate([
            'siswa_id' => $budi->id,
            'nama_dokumen' => 'Ijazah',
        ], [
            'kategori' => 'Ijazah',
            'file_path' => 'dokumen/ijazah-budi.pdf',
            'status' => 'Terverifikasi',
        ]);
    }
}