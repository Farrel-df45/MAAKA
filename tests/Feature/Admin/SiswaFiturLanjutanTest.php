<?php

namespace Tests\Feature\Admin;

use App\Models\Dokumen;
use App\Models\Kelas;
use App\Models\Legalisir;
use App\Models\Notifikasi;
use App\Models\PeminjamanRapor;
use App\Models\PengambilanIjazah;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SiswaFiturLanjutanTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::firstWhere('email', 'admin@maaka.test')
            ?? User::create(['name' => 'Admin', 'email' => 'admin@maaka.test', 'password' => Hash::make('x'), 'role' => 'admin']);
    }

    private function siswa(string $nis = '1', string $nama = 'Andi', string $tingkat = 'XII'): array
    {
        $kelas = Kelas::firstOrCreate(['nama' => "{$tingkat} RPL A"], ['tingkat' => $tingkat]);
        $s = Siswa::create(['nis' => $nis, 'nama' => $nama, 'kelas' => $kelas->nama, 'jurusan' => 'RPL']);
        $s->tempatkanDiKelas($kelas, TahunAjaran::aktif());
        $u = User::create(['name' => $nama, 'email' => "{$nis}@s.test", 'password' => Hash::make('x'), 'role' => 'siswa', 'siswa_id' => $s->id]);

        return [$s, $u];
    }

    private function dok(Siswa $s, string $kategori = 'Rapor', string $status = Dokumen::STATUS_TERVERIFIKASI, string $nama = 'Rapor X'): Dokumen
    {
        return Dokumen::create(['siswa_id' => $s->id, 'nama_dokumen' => $nama, 'kategori' => $kategori, 'file_path' => 'dokumen/x.pdf', 'status' => $status]);
    }

    // ---------- Unggah & hapus dokumen ----------

    public function test_siswa_mengunggah_dokumen_sendiri_berstatus_menunggu(): void
    {
        Storage::fake('local');
        [$s, $u] = $this->siswa();
        $this->actingAs($u)->get(route('siswa.dokumen.create'))->assertOk();

        $data = ['nama_dokumen' => 'Rapor Smt 1', 'kategori' => 'Rapor', 'berkas' => UploadedFile::fake()->create('r.pdf', 100, 'application/pdf')];
        $this->post(route('siswa.dokumen.store'), $data)->assertRedirect(route('siswa.dokumen.index'));

        $d = Dokumen::firstOrFail();
        $this->assertSame($s->id, $d->siswa_id);
        $this->assertSame(Dokumen::STATUS_MENUNGGU, $d->status);
        $this->assertSame($u->id, $d->diunggah_oleh);
        $this->assertNotNull($d->kelas_id); // dicap kelas saat ini
        /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
        $disk = Storage::disk('local');
        $disk->assertExists($d->file_path);

        $this->actingAs($this->admin())->get(route('admin.dokumen.index'))->assertSee('(Siswa)');
    }

    public function test_unggah_siswa_divalidasi(): void
    {
        Storage::fake('local');
        [$s, $u] = $this->siswa();
        $this->actingAs($u);
        $ok = fn (array $o = []) => array_merge(['nama_dokumen' => 'D', 'kategori' => 'Rapor', 'berkas' => UploadedFile::fake()->create('r.pdf', 10, 'application/pdf')], $o);

        $this->post(route('siswa.dokumen.store'), $ok(['berkas' => UploadedFile::fake()->create('virus.exe', 10)]))->assertSessionHasErrors('berkas');
        $this->post(route('siswa.dokumen.store'), $ok(['berkas' => UploadedFile::fake()->create('besar.pdf', 6000, 'application/pdf')]))->assertSessionHasErrors('berkas');
        $this->post(route('siswa.dokumen.store'), $ok(['kategori' => 'Rahasia']))->assertSessionHasErrors('kategori');
        $this->post(route('siswa.dokumen.store'), $ok(['nama_dokumen' => '']))->assertSessionHasErrors('nama_dokumen');
        $this->assertSame(0, Dokumen::count());

        // batas dokumen yang menunggu verifikasi
        for ($i = 0; $i < 10; $i++) {
            $this->dok($s, 'Lainnya', Dokumen::STATUS_MENUNGGU, "D{$i}");
        }
        $this->post(route('siswa.dokumen.store'), $ok())->assertSessionHas('error');
        $this->assertSame(10, Dokumen::count());

        // akun tanpa data siswa tidak bisa mengunggah
        $yatim = User::create(['name' => 'Y', 'email' => 'y@s.test', 'password' => Hash::make('x'), 'role' => 'siswa']);
        $this->actingAs($yatim)->post(route('siswa.dokumen.store'), $ok())->assertForbidden();
    }

    public function test_hapus_dokumen_hanya_milik_sendiri_dan_belum_terverifikasi(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('dokumen/x.pdf', 'isi');
        [$andi, $uAndi] = $this->siswa('1', 'Andi');
        [, $uBudi] = $this->siswa('2', 'Budi');
        $menunggu = $this->dok($andi, 'Rapor', Dokumen::STATUS_MENUNGGU, 'Menunggu');
        $ditolak = $this->dok($andi, 'Rapor', Dokumen::STATUS_DITOLAK, 'Ditolak');
        $oke = $this->dok($andi, 'Rapor', Dokumen::STATUS_TERVERIFIKASI, 'Oke');

        $this->actingAs($uBudi)->delete(route('siswa.dokumen.destroy', $menunggu))->assertForbidden();
        $this->actingAs($uAndi)->delete(route('siswa.dokumen.destroy', $oke))->assertSessionHas('error');
        $this->assertNotNull($oke->fresh());

        $this->delete(route('siswa.dokumen.destroy', $menunggu))->assertSessionHas('success');
        $this->delete(route('siswa.dokumen.destroy', $ditolak))->assertSessionHas('success');
        $this->assertSame(1, Dokumen::count());
    }

    // ---------- Notifikasi ----------

    public function test_notifikasi_terkirim_saat_status_berubah_dan_tidak_saat_tidak_berubah(): void
    {
        [$s, $u] = $this->siswa();
        $d = $this->dok($s, 'Rapor', Dokumen::STATUS_MENUNGGU, 'Rapor Smt 1');
        $this->actingAs($this->admin());

        // dokumen dibuat (status menunggu) tidak memicu notifikasi
        $this->assertSame(0, Notifikasi::count());

        $this->patch(route('admin.dokumen.status', $d), ['status' => 'Ditolak', 'catatan' => 'Buram'])->assertSessionHasNoErrors();
        $n = Notifikasi::firstOrFail();
        $this->assertSame($u->id, $n->user_id);
        $this->assertStringContainsString('Rapor Smt 1', $n->judul);
        $this->assertStringContainsString('Ditolak', $n->judul);
        $this->assertSame('Catatan: Buram', $n->pesan);

        // simpan tanpa mengubah status -> tidak ada notifikasi baru
        $this->patch(route('admin.dokumen.status', $d), ['status' => 'Ditolak', 'catatan' => 'Buram']);
        $this->assertSame(1, Notifikasi::count());

        // massal juga mengirim notifikasi
        $d2 = $this->dok($s, 'Ijazah', Dokumen::STATUS_MENUNGGU, 'Ijazah SMP');
        $this->post(route('admin.dokumen.massal'), ['ids' => [$d2->id], 'aksi' => 'verifikasi']);
        $this->assertSame(2, Notifikasi::count());

        // legalisir, rapor, ijazah
        $l = Legalisir::create(['siswa_id' => $s->id, 'dokumen_id' => $d2->id, 'jumlah' => 1, 'keperluan' => 'x', 'status' => 'Menunggu']);
        $this->patch(route('admin.legalisir.status', $l), ['status' => 'Diproses', 'catatan' => '']);
        $p = PeminjamanRapor::create(['siswa_id' => $s->id, 'dokumen_id' => $d2->id, 'tanggal_pinjam' => now(), 'tanggal_kembali' => now()->addDays(3), 'alasan' => 'x']);
        $this->patch(route('admin.peminjaman-rapor.status', $p), ['status' => 'Disetujui']);
        $i = PengambilanIjazah::create(['siswa_id' => $s->id, 'tanggal_pengambilan' => now()->addDay(), 'pengambil' => 'Sendiri']);
        $this->patch(route('admin.pengambilan-ijazah.status', $i), ['status' => 'Disetujui']);

        $judul = Notifikasi::pluck('judul')->implode('|');
        $this->assertStringContainsString('Legalisir', $judul);
        $this->assertStringContainsString('Pinjam rapor', $judul);
        $this->assertStringContainsString('Ambil ijazah', $judul);
        $this->assertSame(5, Notifikasi::count());
    }

    public function test_siswa_tanpa_akun_tidak_membuat_notifikasi_error(): void
    {
        $s = Siswa::create(['nis' => '5', 'nama' => 'Tanpa Akun', 'kelas' => 'X', 'jurusan' => 'RPL']);
        $d = $this->dok($s, 'Rapor', Dokumen::STATUS_MENUNGGU);
        $this->actingAs($this->admin())->patch(route('admin.dokumen.status', $d), ['status' => 'Terverifikasi'])->assertSessionHasNoErrors();
        $this->assertSame(0, Notifikasi::count());
    }

    public function test_siswa_membaca_notifikasi_miliknya_saja(): void
    {
        [$s, $u] = $this->siswa('1', 'Andi');
        [, $lain] = $this->siswa('2', 'Budi');
        $n = Notifikasi::create(['user_id' => $u->id, 'judul' => 'Rapor X: Terverifikasi', 'url' => route('siswa.dokumen.index')]);
        Notifikasi::create(['user_id' => $lain->id, 'judul' => 'Rahasia Budi']);

        $this->actingAs($u)->get(route('siswa.notifikasi'))->assertOk()->assertSee('Rapor X: Terverifikasi')->assertDontSee('Rahasia Budi');
        $this->get(route('siswa.dashboard'))->assertSee('Notifikasi');

        $this->actingAs($lain)->post(route('siswa.notifikasi.baca', $n))->assertForbidden();
        $this->actingAs($u)->post(route('siswa.notifikasi.baca', $n))->assertRedirect(route('siswa.dokumen.index'));
        $this->assertNotNull($n->fresh()->dibaca_pada);

        Notifikasi::create(['user_id' => $u->id, 'judul' => 'Lagi']);
        $this->post(route('siswa.notifikasi.baca-semua'))->assertSessionHas('success');
        $this->assertSame(0, Notifikasi::where('user_id', $u->id)->whereNull('dibaca_pada')->count());
        $this->assertSame(1, Notifikasi::where('user_id', $lain->id)->whereNull('dibaca_pada')->count());
    }

    // ---------- Dashboard: checklist & pengingat ----------

    public function test_dashboard_menampilkan_checklist_dokumen_wajib(): void
    {
        [$s, $u] = $this->siswa('1', 'Andi', 'XII');
        $this->dok($s, 'Rapor');
        $this->dok($s, 'Transkrip Nilai', Dokumen::STATUS_DITOLAK);

        $this->actingAs($u)->get(route('siswa.dashboard'))->assertOk()
            ->assertSee('Kelengkapan Dokumen')->assertSee('1 dari 2 dokumen wajib')->assertSee('Ditolak, unggah ulang');

        [, $x] = $this->siswa('2', 'Kelas X', 'X');
        $this->actingAs($x)->get(route('siswa.dashboard'))->assertSee('0 dari 1 dokumen wajib')->assertSee('Belum diunggah');
    }

    public function test_pengingat_pengembalian_rapor(): void
    {
        [$s, $u] = $this->siswa();
        $d = $this->dok($s);
        $buat = fn (int $hari) => PeminjamanRapor::create([
            'siswa_id' => $s->id, 'dokumen_id' => $d->id, 'tanggal_pinjam' => now()->subDays(5),
            'tanggal_kembali' => now()->addDays($hari), 'alasan' => 'x', 'status' => PeminjamanRapor::STATUS_DIPINJAM,
        ]);

        $this->actingAs($u)->get(route('siswa.dashboard'))->assertDontSee('Pengingat:');

        $p = $buat(10); // masih lama -> tidak ada pengingat
        $this->get(route('siswa.dashboard'))->assertDontSee('Pengingat:');

        $p->update(['tanggal_kembali' => now()->addDays(2)]);
        $this->get(route('siswa.dashboard'))->assertSee('Pengingat:')->assertSee('2 hari lagi');

        $p->update(['tanggal_kembali' => now()]);
        $this->get(route('siswa.dashboard'))->assertSee('hari ini');

        $p->update(['tanggal_kembali' => now()->subDays(3)]);
        $this->get(route('siswa.dashboard'))->assertSee('terlambat 3 hari');

        $p->update(['status' => PeminjamanRapor::STATUS_DIKEMBALIKAN]);
        $this->get(route('siswa.dashboard'))->assertDontSee('Pengingat:');
    }

    // ---------- Status pengajuan ----------

    public function test_halaman_status_pengajuan_menampilkan_semua_jenis_milik_sendiri(): void
    {
        [$andi, $u] = $this->siswa('1', 'Andi');
        [$budi] = $this->siswa('2', 'Budi');
        $d = $this->dok($andi, 'Rapor', Dokumen::STATUS_TERVERIFIKASI, 'Rapor Andi');
        $dBudi = $this->dok($budi, 'Rapor', Dokumen::STATUS_TERVERIFIKASI, 'Rapor Budi');

        Legalisir::create(['siswa_id' => $andi->id, 'dokumen_id' => $d->id, 'jumlah' => 2, 'keperluan' => 'x', 'status' => 'Diproses']);
        PeminjamanRapor::create(['siswa_id' => $andi->id, 'dokumen_id' => $d->id, 'tanggal_pinjam' => now()->subDay(), 'tanggal_kembali' => now()->addDays(4), 'alasan' => 'x', 'status' => 'Dipinjam']);
        PengambilanIjazah::create(['siswa_id' => $andi->id, 'tanggal_pengambilan' => now()->addDays(2), 'pengambil' => 'Sendiri', 'status' => 'Ditolak', 'catatan' => 'Belum lunas administrasi']);
        Legalisir::create(['siswa_id' => $budi->id, 'dokumen_id' => $dBudi->id, 'jumlah' => 1, 'keperluan' => 'x', 'status' => 'Menunggu']);

        $this->actingAs($u)->get(route('siswa.pengajuan'))->assertOk()
            ->assertSee('Rapor Andi')->assertDontSee('Rapor Budi')
            ->assertSee('Berjalan (2)')->assertSee('Semua (3)')
            ->assertDontSee('Belum lunas administrasi'); // ijazah ditolak = selesai, tidak di tab Berjalan

        $this->get(route('siswa.pengajuan', ['tampil' => 'semua']))->assertOk()
            ->assertSee('Belum lunas administrasi')->assertSee('Diproses')->assertSee('Dikembalikan');
    }
}
