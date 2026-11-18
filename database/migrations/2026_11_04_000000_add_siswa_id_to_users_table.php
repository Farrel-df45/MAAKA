<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Menautkan akun siswa (login via NIS) ke data siswa.
        // Dilewati jika kolom sudah ada, supaya aman dijalankan di database lama maupun baru.
        if (Schema::hasColumn('users', 'siswa_id')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('siswa_id')
                ->nullable()
                ->after('role')
                ->constrained('siswas')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (!Schema::hasColumn('users', 'siswa_id')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('siswa_id');
        });
    }
};
