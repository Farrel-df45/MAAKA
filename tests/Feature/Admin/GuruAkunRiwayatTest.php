<?php

namespace Tests\Feature\Admin;

use App\Models\AktivitasGuru;
use App\Models\Dokumen;
use App\Models\Guru;
use App\Models\Kelas;
use App\Models\TahunAjaran;
use App\Models\WaliKelas;
use App\Models\Siswa;
use App\Models\User;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GuruAkunRiwayatTest extends TestCase
{
    use RefreshDatabase;

    /** Disk "local" (hasil Storage::fake) bertipe FilesystemAdapter agar assertExists/assertMissing dikenali editor */
    private function disk(): FilesystemAdapter
    {
        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk('local');

        return $disk;
    }

    private function admin(): User
    {
        return User::create(['name' => 'Admin', 'email' => 'admin@maaka.test', 'password' => Hash::make('rahasia123'), 'role' => 'admin']);
    }

    private function kelas(string $nama = 'XII RPL A'): Kelas
    {
        return Kelas::firstOrCreate(['nama' => $nama], ['tingkat' => 'XII']);
    }

    // Siswa ditempatkan di kelas pada tahun ajaran aktif
    private function siswa(string $nama = 'Andi Pratama', string $nis = '2026001', string $kelas = 'XII RPL A'): Siswa
    {
        $siswa = Siswa::create(['nis' => $nis, 'nama' => $nama, 'kelas' => $kelas, 'jurusan' => 'RPL']);
        $siswa->tempatkanDiKelas($this->kelas($kelas), TahunAjaran::aktif());

        return $siswa;
    }

    // Guru + akun login (email = email guru, login boleh pakai NIP); $kelas = kelas yang ia walikan (null = belum ditugaskan)
    private function guruDenganAkun(string $nip = '19800101', string $nama = 'Bu Sri', ?string $kelas = 'XII RPL A'): array
    {
        $guru = Guru::create(['nip' => $nip, 'nama' => $nama, 'email' => "{$nip}@sekolah.test", 'jabatan' => 'Guru']);
        if ($kelas) {
            WaliKelas::tetapkan($guru, $this->kelas($kelas), TahunAjaran::aktif());
        }
        $user = User::create([
            'name' => $nama, 'email' => $guru->email, 'password' => Hash::make('guru12345'),
            'role' => 'guru', 'guru_id' => $guru->id,
        ]);

        return [$guru, $user];
    }

    private function pdf(string $nama = 'dok.pdf'): UploadedFile
    {
        return UploadedFile::fake()->create($nama, 50, 'application/pdf');
    }

    // ---------- Admin membuat akun guru dengan NIP ----------

    public function test_admin_membuat_guru_beserta_akun_dan_guru_login_dengan_nip(): void
    {
        $this->actingAs($this->admin());

        $this->post(route('admin.guru.store'), [
            'nip' => '19800101', 'nama' => 'Bu Sri', 'email' => 'sri@sekolah.test', 'jabatan' => 'Wali Kelas',
            'password' => 'guru12345', 'password_confirmation' => 'guru12345',
        ])->assertRedirect(route('admin.guru.index'));

        $guru = Guru::firstWhere('nip', '19800101');
        $akun = User::firstWhere('guru_id', $guru->id);
        $this->assertSame('guru', $akun->role);
        $this->assertSame('sri@sekolah.test', $akun->email);
        $this->assertTrue(Hash::check('guru12345', $akun->password));

        Auth::logout();

        // login dengan NIP
        $this->post('/guru/login', ['login' => '19800101', 'password' => 'guru12345'])
            ->assertRedirect(route('guru.dashboard'));
        $this->assertAuthenticatedAs($akun);

        Auth::logout();
        // login dengan email tetap bisa; kata sandi salah ditolak
        $this->post('/guru/login', ['login' => 'sri@sekolah.test', 'password' => 'guru12345'])->assertRedirect(route('guru.dashboard'));
        Auth::logout();
        $this->post('/guru/login', ['login' => '19800101', 'password' => 'salah'])->assertSessionHasErrors('login');
        $this->assertGuest();
    }

    public function test_validasi_akun_guru(): void
    {
        $this->actingAs($this->admin());
        $dasar = ['nama' => 'Pak Budi', 'email' => 'budi@sekolah.test', 'jabatan' => 'Guru'];

        // password diisi tapi NIP kosong
        $this->post(route('admin.guru.store'), $dasar + ['password' => 'guru12345', 'password_confirmation' => 'guru12345'])
            ->assertSessionHasErrors('nip');
        // password terlalu pendek / konfirmasi beda
        $this->post(route('admin.guru.store'), $dasar + ['nip' => '1', 'password' => 'pendek', 'password_confirmation' => 'pendek'])
            ->assertSessionHasErrors('password');
        $this->post(route('admin.guru.store'), $dasar + ['nip' => '1', 'password' => 'guru12345', 'password_confirmation' => 'beda12345'])
            ->assertSessionHasErrors('password');
        // email sudah dipakai akun lain
        $this->post(route('admin.guru.store'), array_merge($dasar, ['nip' => '1', 'email' => 'admin@maaka.test', 'password' => 'guru12345', 'password_confirmation' => 'guru12345']))
            ->assertSessionHasErrors('email');
        $this->assertSame(0, Guru::count());
        $this->assertSame(1, User::count());

        // tanpa password: hanya data guru, tanpa akun
        $this->post(route('admin.guru.store'), $dasar)->assertSessionHasNoErrors();
        $this->assertSame(1, Guru::count());
        $this->assertSame(1, User::count());
    }

    public function test_tambah_akun_untuk_guru_lama_dan_sinkron_email_serta_reset_password(): void
    {
        $this->actingAs($this->admin());
        $guru = Guru::create(['nip' => '777', 'nama' => 'Pak Lama', 'email' => 'lama@sekolah.test', 'jabatan' => 'Guru']);

        // edit + isi password => akun dibuat
        $this->put(route('admin.guru.update', $guru), [
            'nip' => '777', 'nama' => 'Pak Lama', 'email' => 'lama@sekolah.test', 'jabatan' => 'Guru',
            'password' => 'guru12345', 'password_confirmation' => 'guru12345',
        ])->assertRedirect(route('admin.guru.index'));
        $akun = User::firstWhere('guru_id', $guru->id);
        $this->assertNotNull($akun);

        // ubah email & nama => akun ikut berubah; password lama tetap
        $this->put(route('admin.guru.update', $guru), [
            'nip' => '777', 'nama' => 'Pak Baru', 'email' => 'baru@sekolah.test', 'jabatan' => 'Guru',
        ]);
        $akun->refresh();
        $this->assertSame('baru@sekolah.test', $akun->email);
        $this->assertSame('Pak Baru', $akun->name);
        $this->assertTrue(Hash::check('guru12345', $akun->password));

        // reset password
        $this->put(route('admin.guru.update', $guru), [
            'nip' => '777', 'nama' => 'Pak Baru', 'email' => 'baru@sekolah.test', 'jabatan' => 'Guru',
            'password' => 'passbaru123', 'password_confirmation' => 'passbaru123',
        ]);
        $this->assertTrue(Hash::check('passbaru123', $akun->fresh()->password));
        $this->assertSame(1, User::where('guru_id', $guru->id)->count());
    }

    // ---------- Guru: unggah / ubah / hapus + riwayat ----------

    public function test_halaman_guru_dapat_dibuka(): void
    {
        [, $user] = $this->guruDenganAkun();
        $dokumen = Dokumen::create(['siswa_id' => $this->siswa()->id, 'diunggah_oleh' => $user->id, 'nama_dokumen' => 'X', 'kategori' => 'Rapor', 'file_path' => 'a', 'status' => Dokumen::STATUS_MENUNGGU]);

        $this->actingAs($user);
        $this->get(route('guru.dashboard'))->assertOk()->assertSee('Dashboard Guru');
        $this->get(route('guru.dokumen.index'))->assertOk();
        $this->get(route('guru.dokumen.create'))->assertOk();
        $this->get(route('guru.dokumen.edit', $dokumen))->assertOk();
    }

    public function test_guru_unggah_dokumen_tercatat_di_riwayat(): void
    {
        Storage::fake('local');
        [$guru, $user] = $this->guruDenganAkun();
        $siswa = $this->siswa();

        $this->actingAs($user)->post(route('guru.dokumen.store'), [
            'siswa_id' => $siswa->id, 'nama_dokumen' => 'Rapor Semester 6', 'kategori' => 'Rapor',
            'status' => Dokumen::STATUS_TERVERIFIKASI, // harus diabaikan
            'berkas' => $this->pdf(),
        ])->assertRedirect(route('guru.dokumen.index'));

        $dokumen = Dokumen::firstWhere('nama_dokumen', 'Rapor Semester 6');
        $this->assertSame($user->id, $dokumen->diunggah_oleh);
        $this->assertSame(Dokumen::STATUS_MENUNGGU, $dokumen->status); // guru tidak bisa mengesahkan sendiri
        $this->disk()->assertExists($dokumen->file_path);

        $log = AktivitasGuru::first();
        $this->assertSame('Unggah', $log->aksi);
        $this->assertSame($guru->id, $log->guru_id);
        $this->assertSame('Bu Sri', $log->guru_nama);
        $this->assertSame('Rapor Semester 6', $log->nama_dokumen);
        $this->assertSame('Andi Pratama', $log->nama_siswa);
    }

    public function test_guru_ubah_dokumen_tercatat_dan_status_diverifikasi_ulang(): void
    {
        Storage::fake('local');
        [, $user] = $this->guruDenganAkun();
        $siswa = $this->siswa();
        $this->actingAs($user)->post(route('guru.dokumen.store'), [
            'siswa_id' => $siswa->id, 'nama_dokumen' => 'Rapor', 'kategori' => 'Rapor', 'berkas' => $this->pdf(),
        ]);
        $dokumen = Dokumen::first();
        $dokumen->update(['status' => Dokumen::STATUS_TERVERIFIKASI]); // sudah disetujui admin
        $lama = $dokumen->file_path;

        // ubah nama + ganti berkas
        $this->put(route('guru.dokumen.update', $dokumen), [
            'siswa_id' => $siswa->id, 'nama_dokumen' => 'Rapor Revisi', 'kategori' => 'Rapor', 'berkas' => $this->pdf('baru.pdf'),
        ])->assertRedirect(route('guru.dokumen.index'));

        $dokumen->refresh();
        $this->assertSame('Rapor Revisi', $dokumen->nama_dokumen);
        $this->assertSame(Dokumen::STATUS_MENUNGGU, $dokumen->status);
        $this->disk()->assertMissing($lama);
        $this->disk()->assertExists($dokumen->file_path);

        $log = AktivitasGuru::where('aksi', 'Ubah')->first();
        $this->assertNotNull($log);
        $this->assertSame('Rapor Revisi', $log->nama_dokumen);
        $this->assertStringContainsString('nama dokumen', $log->keterangan);
        $this->assertStringContainsString('berkas', $log->keterangan);

        // simpan tanpa perubahan: tidak mencatat aktivitas baru dan status tidak direset
        $dokumen->update(['status' => Dokumen::STATUS_TERVERIFIKASI]);
        $jumlah = AktivitasGuru::count();
        $this->put(route('guru.dokumen.update', $dokumen), [
            'siswa_id' => $siswa->id, 'nama_dokumen' => 'Rapor Revisi', 'kategori' => 'Rapor',
        ])->assertRedirect(route('guru.dokumen.index'));
        $this->assertSame($jumlah, AktivitasGuru::count());
        $this->assertSame(Dokumen::STATUS_TERVERIFIKASI, $dokumen->fresh()->status);
    }

    public function test_guru_hapus_dokumen_tercatat_dengan_salinan_data(): void
    {
        Storage::fake('local');
        [, $user] = $this->guruDenganAkun();
        $this->actingAs($user)->post(route('guru.dokumen.store'), [
            'siswa_id' => $this->siswa()->id, 'nama_dokumen' => 'Sertifikat', 'kategori' => 'Sertifikat', 'berkas' => $this->pdf(),
        ]);
        $dokumen = Dokumen::first();
        $path = $dokumen->file_path;

        $this->delete(route('guru.dokumen.destroy', $dokumen))->assertRedirect(route('guru.dokumen.index'));

        $this->assertModelMissing($dokumen);
        $this->disk()->assertMissing($path);
        $log = AktivitasGuru::where('aksi', 'Hapus')->first();
        $this->assertNotNull($log);
        $this->assertSame('Sertifikat', $log->nama_dokumen);
        $this->assertSame('Andi Pratama', $log->nama_siswa);
        $this->assertNull($log->dokumen_id);
    }

    public function test_guru_hanya_bisa_mengelola_dokumen_miliknya(): void
    {
        [, $userA] = $this->guruDenganAkun('111', 'Guru A');
        [, $userB] = $this->guruDenganAkun('222', 'Guru B', 'XII RPL B');
        $siswa = $this->siswa();
        $milikA = Dokumen::create(['siswa_id' => $siswa->id, 'diunggah_oleh' => $userA->id, 'nama_dokumen' => 'Milik A', 'kategori' => 'Rapor', 'file_path' => 'a', 'status' => Dokumen::STATUS_MENUNGGU]);
        $dariAdmin = Dokumen::create(['siswa_id' => $siswa->id, 'nama_dokumen' => 'Data Lama', 'kategori' => 'Rapor', 'file_path' => 'b', 'status' => Dokumen::STATUS_TERVERIFIKASI]);

        $this->actingAs($userB);
        $this->get(route('guru.dokumen.edit', $milikA))->assertForbidden();
        $this->put(route('guru.dokumen.update', $milikA), ['siswa_id' => $siswa->id, 'nama_dokumen' => 'Dibajak', 'kategori' => 'Rapor'])->assertForbidden();
        $this->delete(route('guru.dokumen.destroy', $milikA))->assertForbidden();
        $this->get(route('guru.dokumen.berkas', $milikA))->assertForbidden();
        $this->delete(route('guru.dokumen.destroy', $dariAdmin))->assertForbidden();
        $this->assertSame('Milik A', $milikA->fresh()->nama_dokumen);
        $this->assertSame(0, AktivitasGuru::count());

        // daftar milik B tidak memuat dokumen guru lain
        $this->get(route('guru.dokumen.index'))->assertDontSee('Milik A')->assertDontSee('Data Lama');
    }

    public function test_pembatasan_role_guru_dan_siswa(): void
    {
        [, $guru] = $this->guruDenganAkun();
        $siswaUser = User::create(['name' => 'S', 'email' => 's@x.test', 'password' => Hash::make('x'), 'role' => 'siswa']);

        $this->get(route('guru.dokumen.index'))->assertRedirect(route('login'));
        $this->actingAs($guru)->get(route('admin.guru.aktivitas'))->assertForbidden();
        $this->actingAs($guru)->get(route('admin.dokumen.index'))->assertForbidden();
        $this->actingAs($siswaUser)->get(route('guru.dashboard'))->assertForbidden();
        $this->actingAs($this->admin())->get(route('guru.dashboard'))->assertForbidden();
    }

    public function test_akun_guru_tanpa_data_guru_tidak_bisa_unggah(): void
    {
        $yatim = User::create(['name' => 'Guru Yatim', 'email' => 'y@x.test', 'password' => Hash::make('x'), 'role' => 'guru']);

        $this->actingAs($yatim)->get(route('guru.dashboard'))->assertOk()->assertSee('belum terhubung');
        $this->post(route('guru.dokumen.store'), [
            'siswa_id' => $this->siswa()->id, 'nama_dokumen' => 'X', 'kategori' => 'Rapor', 'berkas' => $this->pdf(),
        ])->assertForbidden();
        $this->assertSame(0, Dokumen::count());
    }

    // ---------- Admin melihat riwayat ----------

    public function test_admin_melihat_riwayat_guru_dengan_filter(): void
    {
        Storage::fake('local');
        [$guruA, $userA] = $this->guruDenganAkun('111', 'Guru Alpha');
        [$guruB, $userB] = $this->guruDenganAkun('222', 'Guru Beta', 'XII RPL B');
        $siswa = $this->siswa('Siswa Satu', '1');
        $siswaB = $this->siswa('Siswa Dua', '2', 'XII RPL B');

        $this->actingAs($userA)->post(route('guru.dokumen.store'), ['siswa_id' => $siswa->id, 'nama_dokumen' => 'Dok Alpha', 'kategori' => 'Rapor', 'berkas' => $this->pdf()]);
        $this->actingAs($userB)->post(route('guru.dokumen.store'), ['siswa_id' => $siswaB->id, 'nama_dokumen' => 'Dok Beta', 'kategori' => 'Ijazah', 'berkas' => $this->pdf()]);
        $this->actingAs($userB)->delete(route('guru.dokumen.destroy', Dokumen::firstWhere('nama_dokumen', 'Dok Beta')));

        $this->actingAs($this->admin());

        // semua guru
        $this->get(route('admin.guru.aktivitas'))->assertOk()
            ->assertSee('Guru Alpha')->assertSee('Guru Beta')->assertSee('Dok Alpha')->assertSee('Dok Beta')->assertSee('Sudah dihapus');

        // per guru
        $this->get(route('admin.guru.riwayat', $guruA))->assertOk()->assertSee('Dok Alpha')->assertDontSee('Dok Beta');
        $this->get(route('admin.guru.riwayat', $guruB))->assertSee('Dok Beta')->assertDontSee('Dok Alpha');

        // filter guru_id, aksi, dan pencarian
        $this->get(route('admin.guru.aktivitas', ['guru_id' => $guruA->id]))->assertSee('Dok Alpha')->assertDontSee('Dok Beta');
        $this->get(route('admin.guru.aktivitas', ['aksi' => 'Hapus']))->assertSee('Dok Beta')->assertDontSee('Dok Alpha');
        $this->get(route('admin.guru.aktivitas', ['q' => 'Alpha']))->assertSee('Dok Alpha')->assertDontSee('Dok Beta');

        // jumlah aktivitas tampil di Data Guru
        $this->get(route('admin.guru.index'))->assertOk()->assertSee('Guru Alpha');

        // dokumen unggahan guru menampilkan pengunggah di daftar dokumen admin
        $this->get(route('admin.dokumen.index'))->assertSee('oleh Guru Alpha (Guru)');
    }

    public function test_riwayat_tetap_ada_setelah_guru_dihapus(): void
    {
        Storage::fake('local');
        [$guru, $user] = $this->guruDenganAkun('333', 'Guru Pindah');
        $this->actingAs($user)->post(route('guru.dokumen.store'), ['siswa_id' => $this->siswa()->id, 'nama_dokumen' => 'Dok Penting', 'kategori' => 'Rapor', 'berkas' => $this->pdf()]);

        $this->actingAs($this->admin())->delete(route('admin.guru.destroy', $guru))->assertRedirect(route('admin.guru.index'));

        $this->assertModelMissing($guru);
        $this->assertModelMissing($user);
        $log = AktivitasGuru::first();
        $this->assertNull($log->guru_id);
        $this->get(route('admin.guru.aktivitas'))->assertOk()->assertSee('Guru Pindah')->assertSee('Dok Penting');
    }

    public function test_admin_unggah_dokumen_mencatat_pengunggah(): void
    {
        Storage::fake('local');
        $admin = $this->admin();
        $this->actingAs($admin)->post(route('admin.dokumen.store'), [
            'siswa_id' => $this->siswa()->id, 'nama_dokumen' => 'Ijazah', 'kategori' => 'Ijazah',
            'status' => Dokumen::STATUS_TERVERIFIKASI, 'berkas' => $this->pdf(),
        ]);

        $this->assertSame($admin->id, Dokumen::first()->diunggah_oleh);
        $this->assertSame(0, AktivitasGuru::count()); // riwayat hanya untuk aksi guru
    }
}
