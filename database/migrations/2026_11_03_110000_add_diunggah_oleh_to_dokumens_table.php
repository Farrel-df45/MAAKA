<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dokumens', function (Blueprint $table) {
            // Akun (admin/guru) yang mengunggah dokumen; null untuk data lama
            $table->foreignId('diunggah_oleh')->nullable()->after('siswa_id')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('dokumens', function (Blueprint $table) {
            $table->dropConstrainedForeignId('diunggah_oleh');
        });
    }
};
