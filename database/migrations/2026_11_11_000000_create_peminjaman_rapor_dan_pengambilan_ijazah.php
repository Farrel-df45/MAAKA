<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Peminjaman rapor asli (wajib dikembalikan)
        Schema::create('peminjaman_rapors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('siswa_id')->constrained('siswas')->cascadeOnDelete();
            $table->foreignId('dokumen_id')->constrained('dokumens')->cascadeOnDelete();
            $table->date('tanggal_pinjam');
            $table->date('tanggal_kembali'); // rencana pengembalian
            $table->text('alasan');
            $table->string('status')->default('Menunggu');
            $table->text('catatan')->nullable();
            $table->timestamp('dipinjam_pada')->nullable();
            $table->timestamp('dikembalikan_pada')->nullable();
            $table->timestamps();
        });

        // Pengambilan ijazah asli oleh siswa lulus (sendiri / diwakilkan)
        Schema::create('pengambilan_ijazahs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('siswa_id')->constrained('siswas')->cascadeOnDelete();
            $table->date('tanggal_pengambilan');
            $table->string('pengambil')->default('Sendiri'); // Sendiri / Diwakilkan
            $table->string('nama_wakil')->nullable();
            $table->string('hubungan_wakil')->nullable();
            $table->text('keterangan')->nullable();
            $table->string('status')->default('Menunggu');
            $table->text('catatan')->nullable();
            $table->timestamp('diambil_pada')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pengambilan_ijazahs');
        Schema::dropIfExists('peminjaman_rapors');
    }
};
