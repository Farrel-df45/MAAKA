<?php

namespace Tests\Feature\Admin;

use App\Models\AktivitasGuru;
use App\Models\Dokumen;
use App\Models\Guru;
use App\Models\Kelas;
use App\Models\RiwayatKelas;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Models\WaliKelas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class KelasRollingTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        // Satu akun admin per tes: dipakai ulang bila dipanggil lagi
        return User::firstWhere('email', 'admin@maaka.test') ?? User::create(['name' => 'Admin', 'email' => 'admin@maaka.test', 'password' => Hash::make('rahasia123'), 'role' => 'admin']);
    }

    private function kelas(string $nama, string $tingkat): Kelas
    {
        return Kelas::firstOrCreate(['nama' => $nama], ['tingkat' => $tingkat]);
    }

    private function siswa(string $nama, string $nis, Kelas $kelas): Siswa
    {
        $siswa = Siswa::create(['nis' => $nis, 'nama' => $nama, 'kelas' => $kelas->nama, 'jurusan' => 'RPL']);
        $siswa->tempatkanDiKelas($kelas, TahunAjaran::aktif());

        return $siswa;
    }

    // Guru + akun; $kelas = kelas yang ia walikan pada tahun ajaran aktif (null = belum ditugaskan)
    private function guru(string $nip, string $nama, ?Kelas $kelas = null): array
    {
        $guru = Guru::create(['nip' => $nip, 'nama' => $nama, 'email' => "{$nip}@sekolah.test", 'jabatan' => 'Guru']);
        $user = User::create(['name' => $nama, 'email' => $guru->email, 'password' => Hash::make('guru12345'), 'role' => 'guru', 'guru_id' => $guru->id]);
        if ($kelas) {
            WaliKelas::tetapkan($guru, $kelas, TahunAjaran::aktif());
        }

        return [$guru, $user];
    }

    private function pdf(): UploadedFile
    {
        return UploadedFile::fake()->create('dok.pdf', 50, 'application/pdf');
    }

    // ---------- Akses guru dibatasi ke kelasnya ----------

    public function test_guru_tanpa_kelas_tidak_bisa_mengakses_siswa_dan_dokumen(): void
    {
        $siswa = $this->siswa('Andi', '1', $this->kelas('X RPL A', 'X'));
        Dokumen::create(['siswa_id' => $siswa->id, 'nama_dokumen' => 'Rapor Andi', 'kategori' => 'Rapor', 'file_path' => 'a', 'status' => Dokumen::STATUS_TERVERIFIKASI]);
        [, $user] = $this->guru('900', 'Guru Baru');

        $this->actingAs($user)->get(route('guru.dashboard'))->assertOk()->assertSee('belum ditugaskan');
        $this->get(route('guru.dokumen.index'))->assertOk()->assertDontSee('Rapor Andi');
        $this->get(route('guru.dokumen.create'))->assertOk()->assertDontSee('Andi');
        $this->post(route('guru.dokumen.store'), ['siswa_id' => $siswa->id, 'nama_dokumen' => 'X', 'kategori' => 'Rapor', 'berkas' => $this->pdf()])
            ->assertForbidden();
        $this->assertSame(1, Dokumen::count());
    }

    public function test_guru_hanya_melihat_dan_mengunggah_untuk_siswa_kelasnya(): void
    {
        Storage::fake('local');
        $kelasA = $this->kelas('X RPL A', 'X');
        $kelasB = $this->kelas('X RPL B', 'X');
        $andi = $this->siswa('Andi Kelas A', '1', $kelasA);
        $budi = $this->siswa('Budi Kelas B', '2', $kelasB);
        Dokumen::create(['siswa_id' => $andi->id, 'nama_dokumen' => 'Dok Andi', 'kategori' => 'Rapor', 'file_path' => 'a', 'status' => Dokumen::STATUS_TERVERIFIKASI]);
        $dokBudi = Dokumen::create(['siswa_id' => $budi->id, 'nama_dokumen' => 'Dok Budi', 'kategori' => 'Rapor', 'file_path' => 'b', 'status' => Dokumen::STATUS_TERVERIFIKASI]);
        [, $user] = $this->guru('901', 'Wali A', $kelasA);

        $this->actingAs($user);

        // daftar dokumen & pilihan siswa hanya kelas A
        $this->get(route('guru.dokumen.index'))->assertOk()->assertSee('Dok Andi')->assertDontSee('Dok Budi');
        $this->get(route('guru.dokumen.create'))->assertOk()->assertSee('Andi Kelas A')->assertDontSee('Budi Kelas B');

        // dokumen kelas lain tidak bisa dilihat berkasnya
        $this->get(route('guru.dokumen.berkas', $dokBudi))->assertForbidden();

        // unggah untuk siswa kelas lain ditolak
        $this->post(route('guru.dokumen.store'), ['siswa_id' => $budi->id, 'nama_dokumen' => 'Selundupan', 'kategori' => 'Rapor', 'berkas' => $this->pdf()])
            ->assertSessionHasErrors('siswa_id');
        $this->assertSame(0, Dokumen::where('nama_dokumen', 'Selundupan')->count());

        // unggah untuk siswa kelas sendiri berhasil dan dicap kelas + tahun ajaran, aktivitas mencatat kelas
        $this->post(route('guru.dokumen.store'), ['siswa_id' => $andi->id, 'nama_dokumen' => 'Dok Baru', 'kategori' => 'Rapor', 'berkas' => $this->pdf()])
            ->assertRedirect(route('guru.dokumen.index'));
        $baru = Dokumen::firstWhere('nama_dokumen', 'Dok Baru');
        $this->assertSame($kelasA->id, $baru->kelas_id);
        $this->assertSame(TahunAjaran::aktif()->id, $baru->tahun_ajaran_id);
        $this->assertSame('X RPL A', AktivitasGuru::first()->kelas_nama);
    }

    public function test_dokumen_kelas_diunggah_pihak_lain_hanya_bisa_dilihat_guru(): void
    {
        $kelas = $this->kelas('X RPL A', 'X');
        $siswa = $this->siswa('Andi', '1', $kelas);
        $dariAdmin = Dokumen::create(['siswa_id' => $siswa->id, 'nama_dokumen' => 'Dari Admin', 'kategori' => 'Rapor', 'file_path' => 'a', 'status' => Dokumen::STATUS_TERVERIFIKASI]);
        [, $user] = $this->guru('902', 'Wali A', $kelas);

        $this->actingAs($user)->get(route('guru.dokumen.index'))->assertSee('Dari Admin');
        $this->get(route('guru.dokumen.edit', $dariAdmin))->assertForbidden();
        $this->delete(route('guru.dokumen.destroy', $dariAdmin))->assertForbidden();
        $this->assertModelExists($dariAdmin);
    }

    // ---------- Admin: kelas, siswa, wali ----------

    public function test_admin_kelola_kelas_dan_wali_kelas(): void
    {
        [$guru] = $this->guru('910', 'Bu Wali');
        [$guru2] = $this->guru('911', 'Pak Lain');
        $this->actingAs($this->admin());

        $this->get(route('admin.kelas.index'))->assertOk();
        $this->get(route('admin.kelas.create'))->assertOk();
        $this->post(route('admin.kelas.store'), ['nama' => 'X RPL A', 'tingkat' => 'X', 'guru_id' => $guru->id])
            ->assertRedirect(route('admin.kelas.index'));
        $kelas = Kelas::firstWhere('nama', 'X RPL A');
        $this->assertSame($guru->id, $kelas->waliAktif->guru_id);
        $this->get(route('admin.kelas.index'))->assertSee('Bu Wali');
        $this->get(route('admin.kelas.edit', $kelas))->assertOk();

        // satu guru tidak boleh memegang dua kelas, nama kelas unik
        $this->post(route('admin.kelas.store'), ['nama' => 'X RPL B', 'tingkat' => 'X', 'guru_id' => $guru->id])->assertSessionHasErrors('guru_id');
        $this->post(route('admin.kelas.store'), ['nama' => 'X RPL A'])->assertSessionHasErrors('nama');

        // ganti wali kelas
        $this->put(route('admin.kelas.update', $kelas), ['nama' => 'X RPL A', 'tingkat' => 'X', 'guru_id' => $guru2->id])->assertRedirect();
        $this->assertSame($guru2->id, $kelas->fresh()->waliAktif->guru_id);

        // kelas dengan riwayat tidak boleh dihapus; kelas kosong boleh
        $this->siswa('Andi', '1', $kelas);
        $this->delete(route('admin.kelas.destroy', $kelas))->assertSessionHas('error');
        $this->assertModelExists($kelas);
        $kosong = $this->kelas('XI RPL Z', 'XI');
        $this->delete(route('admin.kelas.destroy', $kosong))->assertRedirect(route('admin.kelas.index'));
        $this->assertModelMissing($kosong);
    }

    public function test_admin_tetapkan_wali_dari_form_guru_dan_siswa_pilih_kelas(): void
    {
        $kelas = $this->kelas('XII RPL A', 'XII');
        $this->actingAs($this->admin());

        // guru dibuat langsung sebagai wali kelas
        $this->post(route('admin.guru.store'), [
            'nip' => '920', 'nama' => 'Bu Rini', 'email' => 'rini@sekolah.test', 'jabatan' => 'Guru', 'kelas_id' => $kelas->id,
        ])->assertRedirect(route('admin.guru.index'));
        $guru = Guru::firstWhere('nip', '920');
        $this->assertSame($kelas->id, $guru->waliAktif->kelas_id);

        // kelas yang sudah punya wali tidak bisa dipakai guru lain
        $this->post(route('admin.guru.store'), [
            'nip' => '921', 'nama' => 'Pak Dedi', 'email' => 'dedi@sekolah.test', 'jabatan' => 'Guru', 'kelas_id' => $kelas->id,
        ])->assertSessionHasErrors('kelas_id');

        // siswa dibuat dengan kelas -> ada penempatan di tahun ajaran aktif
        $this->post(route('admin.siswa.store'), ['nis' => '1', 'nama' => 'Andi', 'kelas_id' => $kelas->id, 'jurusan' => 'RPL'])
            ->assertRedirect(route('admin.siswa.index'));
        $siswa = Siswa::firstWhere('nis', '1');
        $this->assertSame('XII RPL A', $siswa->kelas);
        $this->assertSame($kelas->id, $siswa->riwayatAktif->kelas_id);

        // pindah kelas pada tahun yang sama memperbarui penempatan, bukan menambah baris
        $kelasB = $this->kelas('XII RPL B', 'XII');
        $this->put(route('admin.siswa.update', $siswa), ['nis' => '1', 'nama' => 'Andi', 'kelas_id' => $kelasB->id, 'jurusan' => 'RPL'])
            ->assertRedirect(route('admin.siswa.index'));
        $this->assertSame(1, RiwayatKelas::where('siswa_id', $siswa->id)->count());
        $this->assertSame($kelasB->id, $siswa->fresh()->riwayatAktif->kelas_id);

        // kelas wajib dipilih saat membuat siswa; filter kelas di daftar
        $this->post(route('admin.siswa.store'), ['nis' => '2', 'nama' => 'Tanpa Kelas', 'jurusan' => 'RPL'])->assertSessionHasErrors('kelas_id');
        $this->get(route('admin.siswa.index', ['kelas_id' => $kelasB->id]))->assertOk()->assertSee('Andi');
        $this->get(route('admin.siswa.index', ['kelas_id' => $kelas->id]))->assertOk()->assertDontSee('Andi');
    }

    // ---------- Naik kelas + histori ----------

    public function test_naik_kelas_menyimpan_riwayat_siswa_wali_dan_dokumen(): void
    {
        Storage::fake('local');
        $tahunLama = TahunAjaran::aktif();
        $x = $this->kelas('X RPL A', 'X');
        $xi = $this->kelas('XI RPL A', 'XI');
        $xii = $this->kelas('XII RPL A', 'XII');

        $andi = $this->siswa('Andi Naik', '1', $x);
        $sari = $this->siswa('Sari Lulus', '2', $xii);
        [$guru, $userGuru] = $this->guru('930', 'Bu Wali', $x);
        [$guruXii] = $this->guru('931', 'Pak Senior', $xii);

        // guru mengunggah dokumen untuk Andi di kelas X
        $this->actingAs($userGuru)->post(route('guru.dokumen.store'), [
            'siswa_id' => $andi->id, 'nama_dokumen' => 'Rapor Kelas X', 'kategori' => 'Rapor', 'berkas' => $this->pdf(),
        ]);
        $dokumen = Dokumen::firstWhere('nama_dokumen', 'Rapor Kelas X');

        $admin = $this->actingAs($this->admin());
        $admin->get(route('admin.naik-kelas.create'))->assertOk()->assertSee('X RPL A');

        // tujuan wajib diisi untuk kelas yang punya siswa; tidak ada perubahan bila gagal
        $this->post(route('admin.naik-kelas.store'), ['tahun_baru' => '2099/2100', 'tujuan' => [$x->id => $xi->id]])
            ->assertSessionHasErrors("tujuan.{$xii->id}");
        $this->assertSame($tahunLama->id, TahunAjaran::aktif()->id);

        // guru ganda sebagai wali ditolak
        $this->post(route('admin.naik-kelas.store'), [
            'tahun_baru' => '2099/2100', 'tujuan' => [$x->id => $xi->id, $xii->id => 'lulus'],
            'wali' => [$xi->id => $guru->id, $x->id => $guru->id],
        ])->assertSessionHasErrors('wali');
        $this->assertSame($tahunLama->id, TahunAjaran::aktif()->id);

        // proses naik kelas: Andi X -> XI, Sari lulus, Bu Wali pindah jadi wali XI
        $this->post(route('admin.naik-kelas.store'), [
            'tahun_baru' => '2099/2100',
            'tujuan' => [$x->id => $xi->id, $xii->id => 'lulus'],
            'wali' => [$xi->id => $guru->id, $xii->id => '', $x->id => ''],
        ])->assertRedirect(route('admin.tahun-ajaran.index'));

        $tahunBaru = TahunAjaran::aktif();
        $this->assertSame('2099/2100', $tahunBaru->nama);
        $this->assertFalse($tahunLama->fresh()->aktif);

        // siswa: penempatan lama tetap ada, penempatan baru ditambah
        $this->assertSame(2, RiwayatKelas::where('siswa_id', $andi->id)->count());
        $this->assertSame($x->id, RiwayatKelas::where('siswa_id', $andi->id)->where('tahun_ajaran_id', $tahunLama->id)->value('kelas_id'));
        $this->assertSame($xi->id, $andi->fresh()->riwayatAktif->kelas_id);
        $this->assertSame('XI RPL A', $andi->fresh()->kelas);
        $this->assertSame('Lulus', $sari->fresh()->kelas);
        $this->assertNull($sari->fresh()->riwayatAktif);
        $this->assertSame(1, RiwayatKelas::where('siswa_id', $sari->id)->count());

        // wali: riwayat lama tetap, wali baru dibuat; Pak Senior tidak lagi wali aktif
        $this->assertSame(2, WaliKelas::where('guru_id', $guru->id)->count());
        $this->assertSame($xi->id, $guru->fresh()->waliAktif->kelas_id);
        $this->assertNull($guruXii->fresh()->waliAktif);

        // dokumen lama tetap tercatat di kelas X tahun lama
        $dokumen->refresh();
        $this->assertSame($x->id, $dokumen->kelas_id);
        $this->assertSame($tahunLama->id, $dokumen->tahun_ajaran_id);

        // guru di kelas baru tidak lagi bisa mengakses dokumen kelas lamanya
        // (user dimuat ulang seperti request baru, agar relasi wali kelas lama tidak ter-cache)
        $this->actingAs($userGuru->fresh())->get(route('guru.dokumen.index'))->assertOk()->assertDontSee('Rapor Kelas X');
        $this->get(route('guru.dokumen.berkas', $dokumen))->assertForbidden();
        $this->get(route('guru.dokumen.edit', $dokumen))->assertForbidden();
        $this->get(route('guru.dokumen.create'))->assertOk()->assertSee('Andi Naik');

        // aktivitas guru lama tetap mencatat kelas saat itu
        $this->assertSame('X RPL A', AktivitasGuru::first()->kelas_nama);

        // admin: riwayat siswa menampilkan kedua periode + dokumen, dan bisa mengubah dokumen lama
        $this->actingAs($this->admin());
        $this->get(route('admin.siswa.riwayat', $andi))->assertOk()
            ->assertSee('X RPL A')->assertSee('XI RPL A')->assertSee('Rapor Kelas X')->assertSee('Bu Wali');
        $this->put(route('admin.dokumen.update', $dokumen), [
            'siswa_id' => $andi->id, 'nama_dokumen' => 'Rapor Kelas X (Koreksi)', 'kategori' => 'Rapor', 'status' => Dokumen::STATUS_TERVERIFIKASI,
        ])->assertRedirect(route('admin.dokumen.index'));
        $this->assertSame('Rapor Kelas X (Koreksi)', $dokumen->fresh()->nama_dokumen);
        $this->assertSame($x->id, $dokumen->fresh()->kelas_id);

        // admin: riwayat wali kelas guru menampilkan kelas lama dan baru beserta siswanya
        $this->get(route('admin.guru.wali', $guru))->assertOk()
            ->assertSee('X RPL A')->assertSee('XI RPL A')->assertSee('Andi Naik');

        // halaman tahun ajaran, kelas, dan daftar guru dapat dibuka
        $this->get(route('admin.tahun-ajaran.index'))->assertOk()->assertSee('2099/2100');
        $this->get(route('admin.kelas.index'))->assertOk();
        $this->get(route('admin.guru.index'))->assertOk()->assertSee('XI RPL A');
    }

    public function test_siswa_lulus_tetap_bisa_diubah_datanya_tanpa_kelas(): void
    {
        $xii = $this->kelas('XII RPL A', 'XII');
        $sari = $this->siswa('Sari', '2', $xii);
        $this->actingAs($this->admin());

        $this->post(route('admin.naik-kelas.store'), ['tahun_baru' => '2099/2100', 'tujuan' => [$xii->id => 'lulus']])->assertRedirect();

        $this->get(route('admin.siswa.edit', $sari))->assertOk();
        $this->put(route('admin.siswa.update', $sari), ['nis' => '2', 'nama' => 'Sari Alumni', 'jurusan' => 'RPL'])
            ->assertRedirect(route('admin.siswa.index'));
        $this->assertSame('Sari Alumni', $sari->fresh()->nama);
        $this->assertNull($sari->fresh()->riwayatAktif);
        $this->get(route('admin.siswa.riwayat', $sari))->assertOk()->assertSee('XII RPL A');
    }

    // ---------- Pantau guru ----------

    public function test_halaman_pantau_guru_dan_dashboard_admin_menampilkan_aktivitas(): void
    {
        Storage::fake('local');
        $kelas = $this->kelas('X RPL A', 'X');
        $siswa = $this->siswa('Andi', '1', $kelas);
        [$guru, $user] = $this->guru('940', 'Bu Pantau', $kelas);

        $this->actingAs($user)->post(route('guru.dokumen.store'), ['siswa_id' => $siswa->id, 'nama_dokumen' => 'Dok Pantau', 'kategori' => 'Rapor', 'berkas' => $this->pdf()]);
        $this->delete(route('guru.dokumen.destroy', Dokumen::firstWhere('nama_dokumen', 'Dok Pantau')));

        $this->actingAs($this->admin());

        $this->get(route('admin.dashboard'))->assertOk()
            ->assertSee('Aktivitas Guru Terbaru')->assertSee('Bu Pantau')->assertSee('Dok Pantau');

        $this->get(route('admin.guru.aktivitas'))->assertOk()
            ->assertSee('Pantau Guru')->assertSee('Ringkasan per Guru')->assertSee('Bu Pantau')->assertSee('X RPL A');

        // filter kelas dan tahun ajaran
        $this->get(route('admin.guru.aktivitas', ['kelas' => 'X RPL A']))->assertSee('Dok Pantau');
        $this->get(route('admin.guru.aktivitas', ['kelas' => 'Kelas Lain']))->assertDontSee('Dok Pantau');
        $this->get(route('admin.guru.aktivitas', ['tahun' => TahunAjaran::aktif()->nama]))->assertSee('Dok Pantau');
        $this->get(route('admin.guru.riwayat', $guru))->assertOk()->assertSee('Dok Pantau');
    }

    public function test_hak_akses_halaman_kelas_hanya_admin(): void
    {
        [, $user] = $this->guru('950', 'Guru X');

        $this->get(route('admin.kelas.index'))->assertRedirect(route('login'));
        $this->actingAs($user);
        foreach (['admin.kelas.index', 'admin.tahun-ajaran.index', 'admin.naik-kelas.create'] as $nama) {
            $this->get(route($nama))->assertForbidden();
        }
        $this->post(route('admin.naik-kelas.store'), ['tahun_baru' => '2099/2100'])->assertForbidden();
    }

    // ---------- Migrasi data lama ----------

    public function test_tahun_ajaran_aktif_selalu_tersedia(): void
    {
        $this->assertNotNull(TahunAjaran::aktif());
        $this->assertSame(1, TahunAjaran::where('aktif', true)->count());
    }
}
