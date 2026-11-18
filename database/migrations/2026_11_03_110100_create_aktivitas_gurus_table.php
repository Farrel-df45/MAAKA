<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aktivitas_gurus', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guru_id')->nullable()->constrained('gurus')->nullOnDelete();
            // Salinan nama/NIP agar riwayat tetap terbaca walau guru dihapus
            $table->string('guru_nama');
            $table->string('guru_nip')->nullable();
            $table->string('aksi', 20); // Unggah, Ubah, Hapus
            $table->foreignId('dokumen_id')->nullable()->constrained('dokumens')->nullOnDelete();
            // Salinan data dokumen agar tetap terbaca setelah dokumen dihapus
            $table->string('nama_dokumen');
            $table->string('nama_siswa')->nullable();
            $table->string('keterangan')->nullable();
            $table->timestamps();

            $table->index(['guru_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aktivitas_gurus');
    }
};
