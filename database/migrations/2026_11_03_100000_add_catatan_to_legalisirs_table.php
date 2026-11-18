<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('legalisirs', function (Blueprint $table) {
            // Catatan admin, mis. alasan penolakan atau info pengambilan
            $table->text('catatan')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('legalisirs', function (Blueprint $table) {
            $table->dropColumn('catatan');
        });
    }
};
