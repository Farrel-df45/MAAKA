<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tahun_ajarans', function (Blueprint $table) {
            $table->id();
            $table->string('nama', 20)->unique(); // contoh: 2026/2027
            $table->boolean('aktif')->default(false);
            $table->timestamps();
        });

        Schema::create('kelas', function (Blueprint $table) {
            $table->id();
            $table->string('nama', 50)->unique(); // contoh: X RPL A
            $table->string('tingkat', 10)->nullable(); // X / XI / XII
            $table->string('jurusan')->nullable();
            $table->timestamps();
        });

        // Kelas yang ditempati siswa per tahun ajaran (riwayat naik kelas)
        Schema::create('riwayat_kelas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('siswa_id')->constrained('siswas')->cascadeOnDelete();
            $table->foreignId('kelas_id')->constrained('kelas')->restrictOnDelete();
            $table->foreignId('tahun_ajaran_id')->constrained('tahun_ajarans')->restrictOnDelete();
            $table->timestamps();

            // Satu siswa hanya berada di satu kelas pada satu tahun ajaran
            $table->unique(['siswa_id', 'tahun_ajaran_id']);
        });

        // Wali kelas per tahun ajaran: 1 wali per kelas, 1 kelas per guru
        Schema::create('wali_kelas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guru_id')->nullable()->constrained('gurus')->nullOnDelete();
            // Salinan identitas agar riwayat tetap terbaca walau guru dihapus
            $table->string('guru_nama');
            $table->string('guru_nip')->nullable();
            $table->foreignId('kelas_id')->constrained('kelas')->restrictOnDelete();
            $table->foreignId('tahun_ajaran_id')->constrained('tahun_ajarans')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['kelas_id', 'tahun_ajaran_id']);
            $table->unique(['guru_id', 'tahun_ajaran_id']);
        });

        // Dokumen dicap dengan kelas & tahun ajaran saat dokumen dibuat
        Schema::table('dokumens', function (Blueprint $table) {
            $table->foreignId('kelas_id')->nullable()->after('siswa_id')
                ->constrained('kelas')->nullOnDelete();
            $table->foreignId('tahun_ajaran_id')->nullable()->after('kelas_id')
                ->constrained('tahun_ajarans')->nullOnDelete();
        });

        // Riwayat aktivitas guru menyimpan salinan kelas & tahun ajaran saat aksi terjadi
        Schema::table('aktivitas_gurus', function (Blueprint $table) {
            $table->string('kelas_nama', 50)->nullable()->after('guru_nip');
            $table->string('tahun_ajaran_nama', 20)->nullable()->after('kelas_nama');
        });

        $this->pindahkanDataLama();
    }

    public function down(): void
    {
        Schema::table('aktivitas_gurus', function (Blueprint $table) {
            $table->dropColumn(['kelas_nama', 'tahun_ajaran_nama']);
        });

        Schema::table('dokumens', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tahun_ajaran_id');
            $table->dropConstrainedForeignId('kelas_id');
        });

        Schema::dropIfExists('wali_kelas');
        Schema::dropIfExists('riwayat_kelas');
        Schema::dropIfExists('kelas');
        Schema::dropIfExists('tahun_ajarans');
    }

    // Tahun ajaran aktif dibuat otomatis, lalu teks "kelas" siswa lama dijadikan data kelas
    private function pindahkanDataLama(): void
    {
        $now = now();
        $tahun = (int) $now->format('Y');
        $mulai = (int) $now->format('n') >= 7 ? $tahun : $tahun - 1;
        $nama = $mulai . '/' . ($mulai + 1);

        $tahunId = DB::table('tahun_ajarans')->insertGetId([
            'nama' => $nama, 'aktif' => true, 'created_at' => $now, 'updated_at' => $now,
        ]);

        $kelasIds = [];

        foreach (DB::table('siswas')->orderBy('id')->get() as $siswa) {
            $namaKelas = trim((string) $siswa->kelas);
            if ($namaKelas === '') {
                continue;
            }

            $key = mb_strtolower($namaKelas);
            if (!isset($kelasIds[$key])) {
                $tingkat = strtoupper(strtok($namaKelas, ' '));
                $kelasIds[$key] = DB::table('kelas')->insertGetId([
                    'nama' => $namaKelas,
                    'tingkat' => in_array($tingkat, ['X', 'XI', 'XII'], true) ? $tingkat : null,
                    'jurusan' => $siswa->jurusan,
                    'created_at' => $now, 'updated_at' => $now,
                ]);
            }

            DB::table('riwayat_kelas')->insert([
                'siswa_id' => $siswa->id,
                'kelas_id' => $kelasIds[$key],
                'tahun_ajaran_id' => $tahunId,
                'created_at' => $now, 'updated_at' => $now,
            ]);

            DB::table('dokumens')->where('siswa_id', $siswa->id)->update([
                'kelas_id' => $kelasIds[$key],
                'tahun_ajaran_id' => $tahunId,
            ]);
        }
    }
};
