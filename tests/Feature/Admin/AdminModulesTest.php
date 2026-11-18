<?php

namespace Tests\Feature\Admin;

use App\Models\Dokumen;
use App\Models\Guru;
use App\Models\Legalisir;
use App\Models\Siswa;
use App\Models\User;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminModulesTest extends TestCase
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
        return User::create([
            'name' => 'Admin Utama',
            'email' => 'admin@maaka.test',
            'password' => Hash::make('rahasia123'),
            'role' => 'admin',
        ]);
    }

    private function siswa(string $nis = '2026001', string $nama = 'Andi Pratama'): Siswa
    {
        return Siswa::create(['nis' => $nis, 'nama' => $nama, 'kelas' => 'XII RPL A', 'jurusan' => 'RPL']);
    }

    private function dokumen(Siswa $siswa, string $status = Dokumen::STATUS_MENUNGGU): Dokumen
    {
        return Dokumen::create([
            'siswa_id' => $siswa->id,
            'nama_dokumen' => 'Rapor Semester 6',
            'kategori' => 'Rapor',
            'file_path' => 'dokumen/rapor.pdf',
            'status' => $status,
        ]);
    }

    // ---------- Akses ----------

    public function test_semua_halaman_admin_butuh_login_dan_role_admin(): void
    {
        foreach (['guru', 'dokumen', 'legalisir', 'akun'] as $path) {
            $this->get("/admin/{$path}")->assertRedirect(route('login'));
        }

        $guru = User::create(['name' => 'G', 'email' => 'g@x.test', 'password' => Hash::make('x'), 'role' => 'guru']);
        $this->actingAs($guru)->get('/admin/akun')->assertForbidden();
    }

    public function test_halaman_daftar_dan_form_dapat_dibuka(): void
    {
        $admin = $this->admin();
        $siswa = $this->siswa();
        $guru = Guru::create(['nama' => 'Bu Sri', 'email' => 'sri@x.test', 'jabatan' => 'Guru']);
        $dokumen = $this->dokumen($siswa);
        $legalisir = Legalisir::create([
            'siswa_id' => $siswa->id, 'dokumen_id' => $dokumen->id,
            'jumlah' => 2, 'keperluan' => 'Daftar kuliah', 'status' => 'Menunggu',
        ]);

        $this->actingAs($admin);

        $this->get(route('admin.dashboard'))->assertOk();
        $this->get(route('admin.siswa.index'))->assertOk()->assertSee('Andi Pratama');
        $this->get(route('admin.siswa.create'))->assertOk();
        $this->get(route('admin.guru.index'))->assertOk()->assertSee('Bu Sri');
        $this->get(route('admin.guru.create'))->assertOk();
        $this->get(route('admin.guru.edit', $guru))->assertOk();
        $this->get(route('admin.dokumen.index'))->assertOk()->assertSee('Rapor Semester 6');
        $this->get(route('admin.dokumen.create'))->assertOk();
        $this->get(route('admin.dokumen.edit', $dokumen))->assertOk();
        $this->get(route('admin.legalisir.index'))->assertOk()->assertSee('Andi Pratama');
        $this->get(route('admin.legalisir.show', $legalisir))->assertOk()->assertSee('Daftar kuliah');
        $this->get(route('admin.akun.index'))->assertOk()->assertSee('admin@maaka.test');
        $this->get(route('admin.akun.create'))->assertOk();
        $this->get(route('admin.akun.edit', $admin))->assertOk();
    }

    // ---------- Guru ----------

    public function test_crud_guru(): void
    {
        $this->actingAs($this->admin());

        $this->post(route('admin.guru.store'), [
            'nip' => '1980', 'nama' => 'Pak Budi', 'email' => 'budi@x.test',
            'nomor_telepon' => '0812-3456', 'jabatan' => 'Wali Kelas',
        ])->assertRedirect(route('admin.guru.index'));

        $guru = Guru::firstWhere('email', 'budi@x.test');
        $this->assertNotNull($guru);

        // email & NIP duplikat ditolak
        $this->post(route('admin.guru.store'), [
            'nip' => '1980', 'nama' => 'Lain', 'email' => 'budi@x.test', 'jabatan' => 'Guru',
        ])->assertSessionHasErrors(['nip', 'email']);

        // update tidak bentrok dengan dirinya sendiri
        $this->put(route('admin.guru.update', $guru), [
            'nip' => '1980', 'nama' => 'Pak Budi S.', 'email' => 'budi@x.test', 'jabatan' => 'Kepala Jurusan',
        ])->assertRedirect(route('admin.guru.index'));
        $this->assertSame('Pak Budi S.', $guru->fresh()->nama);

        // pencarian
        $this->get(route('admin.guru.index', ['q' => 'Budi']))->assertSee('Pak Budi S.');
        $this->get(route('admin.guru.index', ['q' => 'zzz']))->assertDontSee('Pak Budi S.');

        // hapus guru ikut menghapus akun loginnya
        $akun = User::create([
            'name' => 'Pak Budi S.', 'email' => 'login-budi@x.test',
            'password' => Hash::make('x'), 'role' => 'guru', 'guru_id' => $guru->id,
        ]);
        $this->delete(route('admin.guru.destroy', $guru))->assertRedirect(route('admin.guru.index'));
        $this->assertModelMissing($guru);
        $this->assertModelMissing($akun);
    }

    // ---------- Dokumen ----------

    public function test_tambah_dokumen_dengan_berkas(): void
    {
        Storage::fake('local');
        $this->actingAs($this->admin());
        $siswa = $this->siswa();

        $this->post(route('admin.dokumen.store'), [
            'siswa_id' => $siswa->id,
            'nama_dokumen' => 'Ijazah',
            'kategori' => 'Ijazah',
            'status' => Dokumen::STATUS_TERVERIFIKASI,
            'berkas' => UploadedFile::fake()->create('ijazah.pdf', 100, 'application/pdf'),
        ])->assertRedirect(route('admin.dokumen.index'));

        $dokumen = Dokumen::firstWhere('nama_dokumen', 'Ijazah');
        $this->assertNotNull($dokumen);
        $this->disk()->assertExists($dokumen->file_path);

        // berkas bisa dilihat
        $this->get(route('admin.dokumen.berkas', $dokumen))->assertOk();
    }

    public function test_validasi_berkas_dokumen(): void
    {
        Storage::fake('local');
        $this->actingAs($this->admin());
        $siswa = $this->siswa();
        $base = ['siswa_id' => $siswa->id, 'nama_dokumen' => 'X', 'kategori' => 'Rapor', 'status' => Dokumen::STATUS_MENUNGGU];

        $this->post(route('admin.dokumen.store'), $base)->assertSessionHasErrors('berkas');
        $this->post(route('admin.dokumen.store'), $base + ['berkas' => UploadedFile::fake()->create('a.exe', 10)])
            ->assertSessionHasErrors('berkas');
        $this->post(route('admin.dokumen.store'), $base + ['berkas' => UploadedFile::fake()->create('a.pdf', 6000, 'application/pdf')])
            ->assertSessionHasErrors('berkas');
        $this->post(route('admin.dokumen.store'), array_merge($base, ['kategori' => 'Ngawur', 'berkas' => UploadedFile::fake()->create('a.pdf', 10, 'application/pdf')]))
            ->assertSessionHasErrors('kategori');
    }

    public function test_ganti_berkas_menghapus_berkas_lama_dan_hapus_dokumen_menghapus_berkas(): void
    {
        Storage::fake('local');
        $this->actingAs($this->admin());
        $siswa = $this->siswa();

        Storage::disk('local')->put('dokumen/lama.pdf', 'lama');
        $dokumen = $this->dokumen($siswa);
        $dokumen->update(['file_path' => 'dokumen/lama.pdf']);

        // update tanpa berkas: berkas lama tetap
        $this->put(route('admin.dokumen.update', $dokumen), [
            'siswa_id' => $siswa->id, 'nama_dokumen' => 'Rapor Revisi', 'kategori' => 'Rapor', 'status' => Dokumen::STATUS_TERVERIFIKASI,
        ])->assertRedirect(route('admin.dokumen.index'));
        $this->disk()->assertExists('dokumen/lama.pdf');
        $this->assertSame('Rapor Revisi', $dokumen->fresh()->nama_dokumen);

        // update dengan berkas baru: lama terhapus
        $this->put(route('admin.dokumen.update', $dokumen), [
            'siswa_id' => $siswa->id, 'nama_dokumen' => 'Rapor Revisi', 'kategori' => 'Rapor', 'status' => Dokumen::STATUS_TERVERIFIKASI,
            'berkas' => UploadedFile::fake()->create('baru.pdf', 10, 'application/pdf'),
        ]);
        $this->disk()->assertMissing('dokumen/lama.pdf');
        $baru = $dokumen->fresh()->file_path;
        $this->disk()->assertExists($baru);

        // hapus dokumen: berkas ikut terhapus
        $this->delete(route('admin.dokumen.destroy', $dokumen));
        $this->assertModelMissing($dokumen);
        $this->disk()->assertMissing($baru);
    }

    public function test_verifikasi_dan_tolak_dokumen(): void
    {
        $this->actingAs($this->admin());
        $dokumen = $this->dokumen($this->siswa());

        $this->patch(route('admin.dokumen.status', $dokumen), ['status' => Dokumen::STATUS_TERVERIFIKASI])
            ->assertSessionHas('success');
        $this->assertSame(Dokumen::STATUS_TERVERIFIKASI, $dokumen->fresh()->status);

        $this->patch(route('admin.dokumen.status', $dokumen), ['status' => Dokumen::STATUS_DITOLAK]);
        $this->assertSame(Dokumen::STATUS_DITOLAK, $dokumen->fresh()->status);

        $this->patch(route('admin.dokumen.status', $dokumen), ['status' => 'Sembarang'])
            ->assertSessionHasErrors('status');
    }

    public function test_berkas_tidak_ada_memberi_pesan_ramah_bukan_error(): void
    {
        Storage::fake('local');
        $this->actingAs($this->admin());
        $dokumen = $this->dokumen($this->siswa());

        $this->from(route('admin.dokumen.index'))
            ->get(route('admin.dokumen.berkas', $dokumen))
            ->assertRedirect(route('admin.dokumen.index'))
            ->assertSessionHas('error');
    }

    public function test_filter_dokumen_berdasarkan_status_dan_pencarian(): void
    {
        $this->actingAs($this->admin());
        $andi = $this->siswa('1', 'Andi');
        $budi = $this->siswa('2', 'Budi');
        Dokumen::create(['siswa_id' => $andi->id, 'nama_dokumen' => 'Dok Andi', 'kategori' => 'Rapor', 'file_path' => 'a', 'status' => Dokumen::STATUS_MENUNGGU]);
        Dokumen::create(['siswa_id' => $budi->id, 'nama_dokumen' => 'Dok Budi', 'kategori' => 'Ijazah', 'file_path' => 'b', 'status' => Dokumen::STATUS_TERVERIFIKASI]);

        $this->get(route('admin.dokumen.index', ['status' => Dokumen::STATUS_MENUNGGU]))
            ->assertSee('Dok Andi')->assertDontSee('Dok Budi');
        $this->get(route('admin.dokumen.index', ['kategori' => 'Ijazah']))
            ->assertSee('Dok Budi')->assertDontSee('Dok Andi');
        $this->get(route('admin.dokumen.index', ['q' => 'Budi']))
            ->assertSee('Dok Budi')->assertDontSee('Dok Andi');
    }

    // ---------- Legalisir ----------

    public function test_proses_legalisir(): void
    {
        $this->actingAs($this->admin());
        $siswa = $this->siswa();
        $legalisir = Legalisir::create([
            'siswa_id' => $siswa->id, 'dokumen_id' => $this->dokumen($siswa, Dokumen::STATUS_TERVERIFIKASI)->id,
            'jumlah' => 1, 'keperluan' => 'Melamar kerja', 'status' => Legalisir::STATUS_MENUNGGU,
        ]);

        $this->patch(route('admin.legalisir.status', $legalisir), ['status' => Legalisir::STATUS_DIPROSES])
            ->assertRedirect(route('admin.legalisir.show', $legalisir));
        $this->assertSame(Legalisir::STATUS_DIPROSES, $legalisir->fresh()->status);

        // ditolak wajib alasan
        $this->patch(route('admin.legalisir.status', $legalisir), ['status' => Legalisir::STATUS_DITOLAK])
            ->assertSessionHasErrors('catatan');
        $this->patch(route('admin.legalisir.status', $legalisir), ['status' => Legalisir::STATUS_DITOLAK, 'catatan' => 'Berkas buram'])
            ->assertSessionHasNoErrors();
        $this->assertSame('Berkas buram', $legalisir->fresh()->catatan);

        // selesai tanpa catatan boleh
        $this->patch(route('admin.legalisir.status', $legalisir), ['status' => Legalisir::STATUS_SELESAI]);
        $this->assertSame(Legalisir::STATUS_SELESAI, $legalisir->fresh()->status);
        $this->assertNull($legalisir->fresh()->catatan);

        $this->get(route('admin.legalisir.index', ['status' => Legalisir::STATUS_SELESAI]))->assertSee('Andi Pratama');
        $this->get(route('admin.legalisir.index', ['status' => Legalisir::STATUS_MENUNGGU]))->assertDontSee('Andi Pratama');
        $this->delete(route('admin.legalisir.destroy', $legalisir));
        $this->assertModelMissing($legalisir);
    }

    // ---------- Akun ----------

    public function test_buat_akun_siswa_dan_login_dengan_akun_itu(): void
    {
        $this->actingAs($this->admin());
        $siswa = $this->siswa();

        $this->post(route('admin.akun.store'), [
            'role' => 'siswa', 'siswa_id' => $siswa->id, 'email' => 'andi@x.test',
            'password' => 'password123', 'password_confirmation' => 'password123',
        ])->assertRedirect(route('admin.akun.index'));

        $akun = User::firstWhere('email', 'andi@x.test');
        $this->assertSame('Andi Pratama', $akun->name);       // nama diambil dari data siswa
        $this->assertSame('siswa', $akun->role);
        $this->assertNotSame('password123', $akun->password); // tersimpan sebagai hash

        // siswa yang sama tidak boleh punya dua akun
        $this->post(route('admin.akun.store'), [
            'role' => 'siswa', 'siswa_id' => $siswa->id, 'email' => 'andi2@x.test',
            'password' => 'password123', 'password_confirmation' => 'password123',
        ])->assertSessionHasErrors('siswa_id');

        // login nyata lewat halaman login siswa
        Auth::logout();
        $this->post('/siswa/login', ['login' => 'andi@x.test', 'password' => 'password123'])
            ->assertRedirect(route('siswa.dashboard'));
    }

    public function test_buat_akun_guru_dan_admin_serta_validasi(): void
    {
        $this->actingAs($this->admin());
        $guru = Guru::create(['nama' => 'Bu Sri', 'email' => 'sri@x.test', 'jabatan' => 'Guru']);

        $this->post(route('admin.akun.store'), [
            'role' => 'guru', 'guru_id' => $guru->id, 'email' => 'sri-login@x.test',
            'password' => 'password123', 'password_confirmation' => 'password123',
        ])->assertRedirect(route('admin.akun.index'));
        $this->assertSame($guru->id, User::firstWhere('email', 'sri-login@x.test')->guru_id);

        $this->post(route('admin.akun.store'), [
            'role' => 'admin', 'name' => 'Admin Dua', 'email' => 'admin2@x.test',
            'password' => 'password123', 'password_confirmation' => 'password123',
        ])->assertRedirect(route('admin.akun.index'));
        $this->assertSame('admin', User::firstWhere('email', 'admin2@x.test')->role);

        // admin wajib nama; siswa wajib memilih siswa; password min 8 & harus sama; email unik
        $this->post(route('admin.akun.store'), ['role' => 'admin', 'email' => 'a@x.test', 'password' => 'password123', 'password_confirmation' => 'password123'])
            ->assertSessionHasErrors('name');
        $this->post(route('admin.akun.store'), ['role' => 'siswa', 'email' => 'b@x.test', 'password' => 'password123', 'password_confirmation' => 'password123'])
            ->assertSessionHasErrors('siswa_id');
        $this->post(route('admin.akun.store'), ['role' => 'admin', 'name' => 'X', 'email' => 'c@x.test', 'password' => 'pendek', 'password_confirmation' => 'pendek'])
            ->assertSessionHasErrors('password');
        $this->post(route('admin.akun.store'), ['role' => 'admin', 'name' => 'X', 'email' => 'admin2@x.test', 'password' => 'password123', 'password_confirmation' => 'password123'])
            ->assertSessionHasErrors('email');
    }

    public function test_ubah_email_dan_reset_password(): void
    {
        $this->actingAs($this->admin());
        $akun = User::create(['name' => 'S', 'email' => 's@x.test', 'password' => Hash::make('lama12345'), 'role' => 'siswa']);

        // tanpa password baru: password lama tetap
        $this->put(route('admin.akun.update', $akun), ['email' => 's2@x.test'])->assertRedirect(route('admin.akun.index'));
        $this->assertTrue(Hash::check('lama12345', $akun->fresh()->password));
        $this->assertSame('s2@x.test', $akun->fresh()->email);

        // reset password
        $this->put(route('admin.akun.update', $akun), [
            'email' => 's2@x.test', 'password' => 'baru12345', 'password_confirmation' => 'baru12345',
        ]);
        $this->assertTrue(Hash::check('baru12345', $akun->fresh()->password));
    }

    public function test_hapus_akun_dengan_pengaman(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        // tidak bisa menghapus diri sendiri / admin terakhir
        $this->delete(route('admin.akun.destroy', $admin))->assertSessionHas('error');
        $this->assertModelExists($admin);

        // admin kedua: boleh menghapus admin lain selama masih ada admin tersisa
        $admin2 = User::create(['name' => 'A2', 'email' => 'a2@x.test', 'password' => Hash::make('x'), 'role' => 'admin']);
        $this->delete(route('admin.akun.destroy', $admin2))->assertRedirect(route('admin.akun.index'));
        $this->assertModelMissing($admin2);

        // menghapus akun siswa tidak menghapus data siswanya
        $siswa = $this->siswa();
        $akun = User::create(['name' => 'S', 'email' => 's@x.test', 'password' => Hash::make('x'), 'role' => 'siswa', 'siswa_id' => $siswa->id]);
        $this->delete(route('admin.akun.destroy', $akun));
        $this->assertModelMissing($akun);
        $this->assertModelExists($siswa);
    }

    public function test_siswa_yang_sudah_punya_akun_tidak_muncul_di_pilihan(): void
    {
        $this->actingAs($this->admin());
        $punya = $this->siswa('1', 'Sudah Punya Akun');
        $this->siswa('2', 'Belum Punya Akun');
        User::create(['name' => 'x', 'email' => 'x@x.test', 'password' => Hash::make('x'), 'role' => 'siswa', 'siswa_id' => $punya->id]);

        $this->get(route('admin.akun.create'))
            ->assertSee('Belum Punya Akun')
            ->assertDontSee('Sudah Punya Akun');
    }
}
