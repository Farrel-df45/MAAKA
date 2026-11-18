
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'guru_id')) {
                $table->foreignId('guru_id')
                    ->nullable()
                    ->unique()
                    ->constrained('gurus')
                    ->nullOnDelete();
            }
        });

        if (!Schema::hasTable('activity_logs')) {
            Schema::create('activity_logs', function (Blueprint $table) {
                $table->id();

                $table->foreignId('actor_user_id')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                $table->foreignId('guru_id')
                    ->nullable()
                    ->constrained('gurus')
                    ->nullOnDelete();

                // Salinan identitas agar histori tetap terbaca
                $table->string('guru_nama')->nullable();
                $table->string('guru_nip')->nullable();

                $table->string('action');
                $table->string('subject_type')->nullable();
                $table->unsignedBigInteger('subject_id')->nullable();
                $table->text('description');
                $table->json('properties')->nullable();

                $table->timestamp('created_at')->useCurrent();

                $table->index(['guru_id', 'created_at']);
                $table->index(['subject_type', 'subject_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');

        if (Schema::hasColumn('users', 'guru_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropForeign(['guru_id']);
                $table->dropUnique(['guru_id']);
                $table->dropColumn('guru_id');
            });
        }
    }
};
