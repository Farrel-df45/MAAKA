<?php

namespace Tests\Feature\Admin;

use App\Models\AktivitasAdmin;
use App\Models\Dokumen;
use App\Models\Guru;
use App\Models\Kelas;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminFiturTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::firstWhere('email', 'admin@maaka.test')
            ?? User::create(['name' => 'Admin Utama', 'email' => 'admin@maaka.test', 'password' => Hash::make('x'), 'role' => 'admin']);
    }

    private function siswa(string $nis, string $nama, string $tingkat = 'XII'): Siswa
    {
        $kelas = Kelas::firstOrCreate(['nama' => "{$tingkat} RPL A"], ['tingkat' => $tingkat]);
        $s = Siswa::create(['nis' => $nis, 'nama' => $nama, 'kelas' => $kelas->nama, 'jurusan' => 'RPL']);
        $s->tempatkanDiKelas($kelas, TahunAjaran::aktif());

        return $s;
    }

    private function dok(Siswa $s, string $kategori, string $status = Dokumen::STATUS_TERVERIFIKASI, string $nama = null): Dokumen
    {
        return Dokumen::create(['siswa_id' => $s->id, 'nama_dokumen' => $nama ?? "{$kategori} {$s->nama}", 'kategori' => $kategori, 'file_path' => 'dokumen/x.pdf', 'status' => $status]);
    }

    private function csv(string $isi): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('data.csv', $isi);
    }

    // ---------- Kelengkapan ----------

    public function test_kelengkapan_dihitung_per_tingkat_dan_hanya_terverifikasi(): void
    {
        $lengkap = $this->siswa('1', 'Lengkap');
        $this->dok($lengkap, 'Rapor');
        $this->dok($lengkap, 'Transkrip Nilai');

        $sebagian = $this->siswa('2', 'Sebagian');
        $this->dok($sebagian, 'Rapor');
        $this->dok($sebagian, 'Transkrip Nilai', Dokumen::STATUS_MENUNGGU);

        $kosong = $this->siswa('3', 'Kosong');
        $this->dok($kosong, 'Rapor', Dokumen::STATUS_DITOLAK);

        $kelasX = $this->siswa('4', 'Anak Kelas X', 'X');
        $this->dok($kelasX, 'Rapor'); // kelas X hanya wajib rapor

        $this->actingAs($this->admin());

        $this->get(route('admin.kelengkapan.index'))->assertOk()
            ->assertViewHas('ringkasan', ['total' => 4, 'lengkap' => 2, 'belum' => 2])
            ->assertSee('Transkrip Nilai: menunggu verifikasi')
            ->assertSee('Rapor: ditolak')
            ->assertSee('Transkrip Nilai: belum ada');

        $this->get(route('admin.kelengkapan.index', ['filter' => 'lengkap']))->assertOk()
            ->assertSee('Lengkap')->assertSee('Anak Kelas X')->assertDontSee('Kosong');
        $this->get(route('admin.kelengkapan.index', ['filter' => 'belum']))->assertOk()
            ->assertSee('Sebagian')->assertDontSee('Anak Kelas X');
        $this->get(route('admin.kelengkapan.index', ['q' => 'kosong']))->assertOk()->assertSee('Kosong')->assertDontSee('Sebagian');
    }

    public function test_alumni_tidak_masuk_kelengkapan_dan_hanya_admin_yang_boleh(): void
    {
        $alumni = Siswa::create(['nis' => '9', 'nama' => 'Alumni Lama', 'kelas' => 'Lulus', 'jurusan' => 'RPL']);
        $this->actingAs($this->admin())->get(route('admin.kelengkapan.index'))->assertOk()->assertDontSee('Alumni Lama');

        $guru = User::create(['name' => 'G', 'email' => 'g@t.test', 'password' => Hash::make('x'), 'role' => 'guru']);
        $this->actingAs($guru)->get(route('admin.kelengkapan.index'))->assertForbidden();
    }

    // ---------- Verifikasi massal ----------

    public function test_verifikasi_dan_tolak_massal(): void
    {
        $s = $this->siswa('1', 'Andi');
        $a = $this->dok($s, 'Rapor', Dokumen::STATUS_MENUNGGU, 'A');
        $b = $this->dok($s, 'Ijazah', Dokumen::STATUS_MENUNGGU, 'B');
        $c = $this->dok($s, 'Sertifikat', Dokumen::STATUS_MENUNGGU, 'C');
        $this->actingAs($this->admin());

        $this->post(route('admin.dokumen.massal'), ['aksi' => 'verifikasi'])->assertSessionHasErrors('ids');
        $this->post(route('admin.dokumen.massal'), ['ids' => [$a->id, 999], 'aksi' => 'verifikasi'])->assertSessionHasErrors('ids.1');
        $this->post(route('admin.dokumen.massal'), ['ids' => [$a->id], 'aksi' => 'hapus'])->assertSessionHasErrors('aksi');
        $this->assertSame(Dokumen::STATUS_MENUNGGU, $a->fresh()->status);

        $this->post(route('admin.dokumen.massal'), ['ids' => [$a->id, $b->id], 'aksi' => 'verifikasi'])->assertSessionHas('success');
        $this->assertSame(Dokumen::STATUS_TERVERIFIKASI, $a->fresh()->status);
        $this->assertSame(Dokumen::STATUS_TERVERIFIKASI, $b->fresh()->status);
        $this->assertSame(Dokumen::STATUS_MENUNGGU, $c->fresh()->status);

        // tolak wajib alasan
        $this->post(route('admin.dokumen.massal'), ['ids' => [$c->id], 'aksi' => 'tolak'])->assertSessionHasErrors('catatan');
        $this->post(route('admin.dokumen.massal'), ['ids' => [$c->id, $b->id], 'aksi' => 'tolak', 'catatan' => 'Foto buram'])->assertSessionHas('success');
        $this->assertSame('Foto buram', $c->fresh()->catatan);
        $this->assertSame(Dokumen::STATUS_DITOLAK, $b->fresh()->status);

        // verifikasi ulang menghapus alasan penolakan
        $this->post(route('admin.dokumen.massal'), ['ids' => [$c->id], 'aksi' => 'verifikasi']);
        $this->assertNull($c->fresh()->catatan);
    }

    public function test_tolak_satuan_menyimpan_alasan_dan_siswa_melihatnya(): void
    {
        $s = $this->siswa('1', 'Andi');
        $d = $this->dok($s, 'Rapor', Dokumen::STATUS_MENUNGGU, 'Rapor Smt 1');
        $this->actingAs($this->admin())->patch(route('admin.dokumen.status', $d), ['status' => 'Ditolak', 'catatan' => 'Tidak terbaca'])->assertSessionHasNoErrors();
        $this->assertSame('Tidak terbaca', $d->fresh()->catatan);

        $user = User::create(['name' => 'Andi', 'email' => 'a@t.test', 'password' => Hash::make('x'), 'role' => 'siswa', 'siswa_id' => $s->id]);
        $this->actingAs($user)->get(route('siswa.dokumen.index'))->assertOk()->assertSee('Alasan: Tidak terbaca');
    }

    // ---------- Ekspor ----------

    public function test_ekspor_csv_berisi_data_dan_aman_dari_formula(): void
    {
        $s = $this->siswa('123', '=HYPERLINK("x")');
        $this->dok($s, 'Rapor');
        $this->actingAs($this->admin());

        foreach (['siswa', 'dokumen', 'peminjaman-rapor', 'kelengkapan'] as $jenis) {
            $r = $this->get(route('admin.ekspor', $jenis));
            $r->assertOk();
            $this->assertStringContainsString('text/csv', $r->headers->get('Content-Type'));
            $this->assertStringContainsString('attachment', $r->headers->get('Content-Disposition'));
        }

        $isi = $this->get(route('admin.ekspor', 'siswa'))->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $isi);
        $this->assertStringContainsString('NIS;Nama;Kelas', $isi);
        $this->assertStringContainsString("'=HYPERLINK", $isi);
        $this->assertStringNotContainsString(';=HYPERLINK', $isi);

        $this->get(route('admin.ekspor', 'rahasia'))->assertNotFound();
    }

    // ---------- Impor ----------

    public function test_impor_siswa_melewati_baris_bermasalah(): void
    {
        $kelas = Kelas::create(['nama' => 'X RPL A', 'tingkat' => 'X']);
        $this->siswa('777', 'Sudah Ada');
        User::create(['name' => 'Pakai', 'email' => 'dipakai@t.test', 'password' => Hash::make('x'), 'role' => 'admin']);
        $this->actingAs($this->admin());

        $this->get(route('admin.impor.create', 'siswa'))->assertOk();
        $this->get(route('admin.impor.contoh', 'siswa'))->assertOk();

        $isi = "nis;nama;kelas;jurusan;alamat;email;password\n"
            . "1001;Budi;x rpl a;RPL;Jl A;budi@t.test;rahasia123\n"        // ok + akun
            . "1002;Citra;X RPL A;RPL;;;\n"                                // ok tanpa akun
            . "777;Dobel Lama;X RPL A;RPL;;;\n"                            // NIS sudah ada
            . "1003;Dina;Kelas Hantu;RPL;;;\n"                            // kelas tak ada
            . "1002;Citra Dua;X RPL A;RPL;;;\n"                            // NIS dobel di berkas
            . "1004;Eka;X RPL A;RPL;;dipakai@t.test;rahasia123\n"         // email sudah dipakai
            . "1005;Fani;X RPL A;RPL;;fani@t.test;pendek\n"               // password pendek
            . "1006;;X RPL A;RPL;;;\n";                                   // nama kosong

        $r = $this->post(route('admin.impor.store', 'siswa'), ['berkas' => $this->csv($isi)]);
        $r->assertRedirect(route('admin.impor.create', 'siswa'))->assertSessionHas('error');
        $this->assertCount(6, session('gagal') === null ? [] : session('gagal'));

        $this->assertSame(2, Siswa::whereIn('nis', ['1001', '1002'])->count());
        $budi = Siswa::where('nis', '1001')->firstOrFail();
        $this->assertSame($kelas->id, $budi->riwayatAktif->kelas_id);
        $this->assertSame('X RPL A', $budi->kelas);
        $this->assertNotNull(User::firstWhere('siswa_id', $budi->id));
        $this->assertNull(User::firstWhere('siswa_id', Siswa::where('nis', '1002')->firstOrFail()->id));
        $this->assertSame(0, Siswa::where('nis', '1003')->count());
        $this->assertSame('Sudah Ada', Siswa::where('nis', '777')->firstOrFail()->nama);
    }

    public function test_impor_guru_dan_validasi_berkas(): void
    {
        $this->actingAs($this->admin());

        $isi = "nip,nama,email,nomor_telepon,jabatan,password\n"
            . "1980,Ani,ani@t.test,0812,,rahasia123\n"
            . "1981,Bima,bima@t.test,,Guru Produktif,\n"
            . "1980,Dobel,lain@t.test,,,\n"
            . "1982,Tanpa Email,,,,\n";

        $this->post(route('admin.impor.store', 'guru'), ['berkas' => $this->csv($isi)])->assertSessionHas('error');
        $this->assertSame(2, Guru::count());
        $this->assertSame('Guru/Wali Kelas', Guru::where('nip', '1980')->firstOrFail()->jabatan);
        $this->assertSame('guru', User::where('email', 'ani@t.test')->firstOrFail()->role);
        $this->assertNull(User::firstWhere('email', 'bima@t.test'));

        // header salah / tanpa berkas / jenis tak dikenal
        $this->post(route('admin.impor.store', 'guru'), ['berkas' => $this->csv("a;b\n1;2\n")])->assertSessionHas('error');
        $this->post(route('admin.impor.store', 'guru'), [])->assertSessionHasErrors('berkas');
        $this->get(route('admin.impor.create', 'dokumen'))->assertNotFound();
    }

    // ---------- Log admin & dashboard ----------

    public function test_perubahan_admin_tercatat_dan_aksi_gagal_atau_baca_tidak(): void
    {
        $s = $this->siswa('1', 'Andi');
        $d = $this->dok($s, 'Rapor', Dokumen::STATUS_MENUNGGU, 'Rapor Andi');
        $this->actingAs($this->admin());

        $this->get(route('admin.dokumen.index'));
        $this->post(route('admin.dokumen.massal'), ['aksi' => 'verifikasi']); // gagal validasi
        $this->assertSame(0, AktivitasAdmin::count());

        $this->patch(route('admin.dokumen.status', $d), ['status' => 'Terverifikasi']);
        $this->post(route('admin.dokumen.massal'), ['ids' => [$d->id], 'aksi' => 'tolak', 'catatan' => 'x']);

        $this->assertSame(2, AktivitasAdmin::count());
        $log = AktivitasAdmin::oldest('id')->firstOrFail();
        $this->assertSame('Admin Utama', $log->admin_nama);
        $this->assertSame('Ubah Status', $log->aksi);
        $this->assertSame('Dokumen', $log->objek);
        $this->assertStringContainsString('Rapor Andi', $log->keterangan);
        $this->assertSame('Verifikasi Massal', AktivitasAdmin::latest('id')->firstOrFail()->aksi);

        $this->get(route('admin.aktivitas-admin.index'))->assertOk()->assertSee('Admin Utama')->assertSee('Ubah Status');
        $this->get(route('admin.aktivitas-admin.index', ['aksi' => 'Verifikasi Massal']))->assertOk()->assertDontSee('Ubah Status</span>', false);
        $this->get(route('admin.aktivitas-admin.index', ['q' => 'tidak-ada']))->assertOk()->assertSee('Belum ada aktivitas');
    }

    public function test_dashboard_menampilkan_antrean(): void
    {
        $s = $this->siswa('1', 'Andi');
        $this->dok($s, 'Rapor', Dokumen::STATUS_MENUNGGU);
        $this->actingAs($this->admin())->get(route('admin.dashboard'))->assertOk()
            ->assertSee('Perlu Ditindaklanjuti')->assertSee('Siswa dengan dokumen belum lengkap')->assertSee('Aktivitas Admin Terbaru');

        $this->get(route('admin.aktivitas-admin.index'))->assertOk();
        $siswaUser = User::create(['name' => 'S', 'email' => 's@t.test', 'password' => Hash::make('x'), 'role' => 'siswa']);
        $this->actingAs($siswaUser)->get(route('admin.aktivitas-admin.index'))->assertForbidden();
    }
}
