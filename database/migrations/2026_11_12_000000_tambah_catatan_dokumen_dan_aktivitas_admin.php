<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('dokumens', 'catatan')) {
            Schema::table('dokumens', function (Blueprint $table) {
                $table->string('catatan', 500)->nullable(); // alasan penolakan
            });
        }

        if (!Schema::hasTable('aktivitas_admins')) {
            Schema::create('aktivitas_admins', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('admin_nama');
                $table->string('aksi', 40);
                $table->string('objek')->nullable();
                $table->string('keterangan', 500)->nullable();
                $table->timestamps();
                $table->index('created_at');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('aktivitas_admins');
        if (Schema::hasColumn('dokumens', 'catatan')) {
            Schema::table('dokumens', fn (Blueprint $t) => $t->dropColumn('catatan'));
        }
    }
};
