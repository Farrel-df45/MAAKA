<?php

namespace Tests\Feature\Admin;

use App\Models\Dokumen;
use App\Models\Kelas;
use App\Models\Legalisir;
use App\Models\PeminjamanRapor;
use App\Models\PengambilanIjazah;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SiswaLayananTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::firstWhere('email', 'admin@maaka.test')
            ?? User::create(['name' => 'Admin', 'email' => 'admin@maaka.test', 'password' => Hash::make('x'), 'role' => 'admin']);
    }

    // Siswa aktif (di kelas tahun ajaran aktif) atau lulus (hanya punya penempatan di tahun ajaran lama)
    private function siswa(string $nis = '1', string $nama = 'Andi', bool $lulus = false): array
    {
        $kelas = Kelas::firstOrCreate(['nama' => 'XII RPL A'], ['tingkat' => 'XII']);
        $siswa = Siswa::create(['nis' => $nis, 'nama' => $nama, 'kelas' => $lulus ? 'Lulus' : $kelas->nama, 'jurusan' => 'RPL']);

        if ($lulus) {
            $lama = TahunAjaran::firstOrCreate(['nama' => '2020/2021'], ['aktif' => false]);
            $siswa->tempatkanDiKelas($kelas, $lama);
        } else {
            $siswa->tempatkanDiKelas($kelas, TahunAjaran::aktif());
        }

        $user = User::create(['name' => $nama, 'email' => "{$nis}@siswa.test", 'password' => Hash::make('x'), 'role' => 'siswa', 'siswa_id' => $siswa->id]);

        return [$siswa, $user];
    }

    private function dokumen(Siswa $siswa, string $nama = 'Rapor Smt 6', string $kategori = 'Rapor', string $status = Dokumen::STATUS_TERVERIFIKASI): Dokumen
    {
        return Dokumen::create(['siswa_id' => $siswa->id, 'nama_dokumen' => $nama, 'kategori' => $kategori, 'file_path' => 'dokumen/x.pdf', 'status' => $status]);
    }

    private function pinjam(Dokumen $dok, array $extra = []): array
    {
        return array_merge([
            'dokumen_id' => $dok->id,
            'tanggal_pinjam' => now()->toDateString(),
            'tanggal_kembali' => now()->addDays(7)->toDateString(),
            'alasan' => 'Daftar beasiswa',
        ], $extra);
    }

    // ---------- Pinjam rapor (siswa) ----------

    public function test_siswa_meminjam_rapor_dengan_aturan(): void
    {
        [$andi, $user] = $this->siswa('1', 'Andi');
        [$budi] = $this->siswa('2', 'Budi');
        $rapor = $this->dokumen($andi);
        $ijazah = $this->dokumen($andi, 'Ijazah SMP', 'Ijazah');
        $tertunda = $this->dokumen($andi, 'Rapor Tertunda', 'Rapor', Dokumen::STATUS_MENUNGGU);
        $punyaBudi = $this->dokumen($budi, 'Rapor Budi');

        $this->actingAs($user)->get(route('siswa.rapor.create'))->assertOk()
            ->assertSee('Rapor Smt 6')->assertDontSee('Ijazah SMP')->assertDontSee('Rapor Tertunda')->assertDontSee('Rapor Budi');

        // berhasil
        $this->post(route('siswa.rapor.store'), $this->pinjam($rapor))->assertRedirect(route('siswa.rapor.create'));
        $p = PeminjamanRapor::firstOrFail();
        $this->assertSame($andi->id, $p->siswa_id);
        $this->assertSame(PeminjamanRapor::STATUS_MENUNGGU, $p->status);

        // ditolak: bukan rapor, belum terverifikasi, milik siswa lain, sudah diajukan
        foreach ([$ijazah, $tertunda, $punyaBudi, $rapor] as $dok) {
            $this->post(route('siswa.rapor.store'), $this->pinjam($dok))->assertSessionHasErrors('dokumen_id');
        }
        $this->assertSame(1, PeminjamanRapor::count());

        // validasi tanggal & lama pinjam
        $lain = $this->dokumen($andi, 'Rapor Smt 5');
        $this->post(route('siswa.rapor.store'), $this->pinjam($lain, ['tanggal_pinjam' => now()->subDay()->toDateString()]))->assertSessionHasErrors('tanggal_pinjam');
        $this->post(route('siswa.rapor.store'), $this->pinjam($lain, ['tanggal_kembali' => now()->toDateString()]))->assertSessionHasErrors('tanggal_kembali');
        $this->post(route('siswa.rapor.store'), $this->pinjam($lain, ['tanggal_kembali' => now()->addDays(PeminjamanRapor::MAKS_HARI + 1)->toDateString()]))
            ->assertSessionHasErrors('tanggal_kembali');
        $this->post(route('siswa.rapor.store'), $this->pinjam($lain, ['alasan' => '']))->assertSessionHasErrors('alasan');
        $this->post(route('siswa.rapor.store'), $this->pinjam($lain, ['tanggal_kembali' => now()->addDays(PeminjamanRapor::MAKS_HARI)->toDateString()]))->assertSessionHasNoErrors();
        $this->assertSame(2, PeminjamanRapor::count());
    }

    public function test_siswa_dengan_rapor_terlambat_tidak_bisa_meminjam_lagi(): void
    {
        [$andi, $user] = $this->siswa();
        $lama = $this->dokumen($andi, 'Rapor Lama');
        $baru = $this->dokumen($andi, 'Rapor Baru');
        $telat = PeminjamanRapor::create([
            'siswa_id' => $andi->id, 'dokumen_id' => $lama->id, 'tanggal_pinjam' => now()->subDays(10), 'tanggal_kembali' => now()->subDays(3),
            'alasan' => 'x', 'status' => PeminjamanRapor::STATUS_DIPINJAM, 'dipinjam_pada' => now()->subDays(10),
        ]);
        $this->assertTrue($telat->terlambat());

        $this->actingAs($user)->get(route('siswa.rapor.create'))->assertOk()->assertSee('terlambat');
        $this->post(route('siswa.rapor.store'), $this->pinjam($baru))->assertSessionHas('error');
        $this->assertSame(1, PeminjamanRapor::count());
    }

    public function test_siswa_batalkan_pengajuan_hanya_miliknya_dan_hanya_saat_menunggu(): void
    {
        [$andi, $userAndi] = $this->siswa('1', 'Andi');
        [, $userBudi] = $this->siswa('2', 'Budi');
        $p = PeminjamanRapor::create(['siswa_id' => $andi->id, 'dokumen_id' => $this->dokumen($andi)->id, 'tanggal_pinjam' => now(), 'tanggal_kembali' => now()->addDays(3), 'alasan' => 'x']);

        $this->actingAs($userBudi)->patch(route('siswa.rapor.batal', $p))->assertForbidden();
        $this->assertSame('Menunggu', $p->fresh()->status);

        $this->actingAs($userAndi)->patch(route('siswa.rapor.batal', $p))->assertSessionHas('success');
        $this->assertSame('Dibatalkan', $p->fresh()->status);

        // setelah dibatalkan, rapor yang sama boleh diajukan lagi
        $this->get(route('siswa.rapor.create'))->assertSee('Rapor Smt 6');

        // yang sudah diproses tidak bisa dibatalkan
        $p->update(['status' => PeminjamanRapor::STATUS_DISETUJUI]);
        $this->patch(route('siswa.rapor.batal', $p))->assertSessionHas('error');
        $this->assertSame('Disetujui', $p->fresh()->status);
    }

    // ---------- Pinjam rapor (admin) ----------

    public function test_admin_memproses_peminjaman_rapor_sampai_dikembalikan(): void
    {
        [$andi] = $this->siswa();
        $p = PeminjamanRapor::create(['siswa_id' => $andi->id, 'dokumen_id' => $this->dokumen($andi)->id, 'tanggal_pinjam' => now(), 'tanggal_kembali' => now()->addDays(3), 'alasan' => 'x']);
        $this->actingAs($this->admin());

        $this->get(route('admin.peminjaman-rapor.index'))->assertOk()->assertSee('Andi');
        $this->get(route('admin.peminjaman-rapor.show', $p))->assertOk()->assertSee('Rapor Smt 6');

        // lompat status tidak diizinkan
        $this->patch(route('admin.peminjaman-rapor.status', $p), ['status' => 'Dipinjam'])->assertSessionHasErrors('status');
        $this->patch(route('admin.peminjaman-rapor.status', $p), ['status' => 'Dikembalikan'])->assertSessionHasErrors('status');
        $this->assertSame('Menunggu', $p->fresh()->status);

        $this->patch(route('admin.peminjaman-rapor.status', $p), ['status' => 'Disetujui'])->assertRedirect();
        $this->patch(route('admin.peminjaman-rapor.status', $p), ['status' => 'Dipinjam'])->assertRedirect();
        $this->assertNotNull($p->fresh()->dipinjam_pada);
        $this->patch(route('admin.peminjaman-rapor.status', $p), ['status' => 'Dikembalikan'])->assertRedirect();
        $this->assertNotNull($p->fresh()->dikembalikan_pada);

        // status final tidak bisa diubah lagi
        $this->patch(route('admin.peminjaman-rapor.status', $p), ['status' => 'Disetujui'])->assertSessionHas('error');
        $this->assertSame('Dikembalikan', $p->fresh()->status);
    }

    public function test_admin_tolak_peminjaman_wajib_alasan_dan_filter_terlambat(): void
    {
        [$andi] = $this->siswa();
        $p = PeminjamanRapor::create(['siswa_id' => $andi->id, 'dokumen_id' => $this->dokumen($andi)->id, 'tanggal_pinjam' => now(), 'tanggal_kembali' => now()->addDays(3), 'alasan' => 'x']);
        $this->actingAs($this->admin());

        $this->patch(route('admin.peminjaman-rapor.status', $p), ['status' => 'Ditolak'])->assertSessionHasErrors('catatan');
        $this->patch(route('admin.peminjaman-rapor.status', $p), ['status' => 'Ditolak', 'catatan' => 'Rapor sedang dipakai'])->assertRedirect();
        $this->assertSame('Ditolak', $p->fresh()->status);

        $telat = PeminjamanRapor::create(['siswa_id' => $andi->id, 'dokumen_id' => $this->dokumen($andi, 'Rapor Telat')->id, 'tanggal_pinjam' => now()->subDays(9), 'tanggal_kembali' => now()->subDays(2),
            'alasan' => 'x', 'status' => PeminjamanRapor::STATUS_DIPINJAM]);
        $this->get(route('admin.peminjaman-rapor.index', ['status' => 'Terlambat']))->assertOk()->assertSee('Rapor Telat')->assertSee('Terlambat');
        $this->get(route('admin.peminjaman-rapor.index', ['status' => 'Ditolak']))->assertOk()->assertDontSee('Rapor Telat');
    }

    // ---------- Ambil ijazah ----------

    public function test_ijazah_hanya_untuk_siswa_lulus(): void
    {
        [, $aktif] = $this->siswa('1', 'Masih Sekolah');
        $data = ['tanggal_pengambilan' => now()->addDays(2)->toDateString(), 'pengambil' => 'Sendiri'];

        $this->actingAs($aktif)->get(route('siswa.ijazah.create'))->assertOk()->assertSee('sudah lulus');
        $this->post(route('siswa.ijazah.store'), $data)->assertSessionHas('error');
        $this->assertSame(0, PengambilanIjazah::count());
    }

    public function test_siswa_lulus_mengajukan_ijazah_sendiri_atau_diwakilkan(): void
    {
        [$sari, $user] = $this->siswa('3', 'Sari Lulus', true);
        $this->assertTrue($sari->isLulus());
        $tgl = now()->addDays(2)->toDateString();

        $this->actingAs($user)->get(route('siswa.ijazah.create'))->assertOk();

        // validasi: tanggal lampau, diwakilkan tanpa nama
        $this->post(route('siswa.ijazah.store'), ['tanggal_pengambilan' => now()->subDay()->toDateString(), 'pengambil' => 'Sendiri'])->assertSessionHasErrors('tanggal_pengambilan');
        $this->post(route('siswa.ijazah.store'), ['tanggal_pengambilan' => $tgl, 'pengambil' => 'Diwakilkan'])->assertSessionHasErrors(['nama_wakil', 'hubungan_wakil']);
        $this->post(route('siswa.ijazah.store'), ['tanggal_pengambilan' => $tgl, 'pengambil' => 'Tetangga'])->assertSessionHasErrors('pengambil');

        // diwakilkan lengkap
        $this->post(route('siswa.ijazah.store'), ['tanggal_pengambilan' => $tgl, 'pengambil' => 'Diwakilkan', 'nama_wakil' => 'Pak Budi', 'hubungan_wakil' => 'Ayah'])
            ->assertRedirect(route('siswa.ijazah.create'));
        $p = PengambilanIjazah::firstOrFail();
        $this->assertSame('Pak Budi', $p->nama_wakil);

        // pengajuan kedua selama masih berjalan ditolak
        $this->post(route('siswa.ijazah.store'), ['tanggal_pengambilan' => $tgl, 'pengambil' => 'Sendiri'])->assertSessionHas('error');
        $this->assertSame(1, PengambilanIjazah::count());

        // batalkan lalu ajukan sendiri (data perwakilan dibersihkan)
        $this->patch(route('siswa.ijazah.batal', $p))->assertSessionHas('success');
        $this->post(route('siswa.ijazah.store'), ['tanggal_pengambilan' => $tgl, 'pengambil' => 'Sendiri', 'nama_wakil' => 'Diabaikan', 'hubungan_wakil' => 'Diabaikan'])
            ->assertSessionHasNoErrors();
        $baru = PengambilanIjazah::latest('id')->firstOrFail();
        $this->assertNull($baru->nama_wakil);
        $this->assertSame('Menunggu', $baru->status);
    }

    public function test_admin_memproses_ijazah_dan_siswa_lain_tidak_bisa_membatalkan(): void
    {
        [$sari] = $this->siswa('3', 'Sari Lulus', true);
        [, $orangLain] = $this->siswa('4', 'Orang Lain');
        $p = PengambilanIjazah::create(['siswa_id' => $sari->id, 'tanggal_pengambilan' => now()->addDays(2), 'pengambil' => 'Sendiri']);

        $this->actingAs($orangLain)->patch(route('siswa.ijazah.batal', $p))->assertForbidden();

        $this->actingAs($this->admin());
        $this->get(route('admin.pengambilan-ijazah.index'))->assertOk()->assertSee('Sari Lulus');
        $this->get(route('admin.pengambilan-ijazah.show', $p))->assertOk();
        $this->patch(route('admin.pengambilan-ijazah.status', $p), ['status' => 'Sudah Diambil'])->assertSessionHasErrors('status');
        $this->patch(route('admin.pengambilan-ijazah.status', $p), ['status' => 'Disetujui'])->assertRedirect();
        $this->patch(route('admin.pengambilan-ijazah.status', $p), ['status' => 'Sudah Diambil'])->assertRedirect();
        $this->assertNotNull($p->fresh()->diambil_pada);
        $this->patch(route('admin.pengambilan-ijazah.status', $p), ['status' => 'Ditolak', 'catatan' => 'x'])->assertSessionHas('error');
        $this->assertSame('Sudah Diambil', $p->fresh()->status);

        // sudah diambil: tidak bisa mengajukan lagi
        $user = User::where('siswa_id', $sari->id)->firstOrFail();
        $this->actingAs($user)->post(route('siswa.ijazah.store'), ['tanggal_pengambilan' => now()->addDay()->toDateString(), 'pengambil' => 'Sendiri'])->assertSessionHas('error');
    }

    // ---------- Batalkan legalisir ----------

    public function test_siswa_membatalkan_legalisir_dan_dokumen_bisa_diajukan_lagi(): void
    {
        [$andi, $user] = $this->siswa('1', 'Andi');
        [, $lain] = $this->siswa('2', 'Budi');
        $dok = $this->dokumen($andi, 'Ijazah Andi', 'Ijazah');
        $l = Legalisir::create(['siswa_id' => $andi->id, 'dokumen_id' => $dok->id, 'jumlah' => 1, 'keperluan' => 'x', 'status' => Legalisir::STATUS_MENUNGGU]);

        $this->actingAs($lain)->patch(route('siswa.legalisir.batal', $l))->assertForbidden();

        $this->actingAs($user)->get(route('siswa.legalisir.create'))->assertOk()->assertSee('Batalkan');
        $this->patch(route('siswa.legalisir.batal', $l))->assertSessionHas('success');
        $this->assertSame('Dibatalkan', $l->fresh()->status);
        $this->get(route('siswa.legalisir.create'))->assertOk()->assertSee('Ijazah Andi');

        // diproses admin -> tidak bisa dibatalkan
        $l->update(['status' => Legalisir::STATUS_DIPROSES]);
        $this->patch(route('siswa.legalisir.batal', $l))->assertSessionHas('error');
        $this->assertSame('Diproses', $l->fresh()->status);

        // admin masih bisa melihat status Dibatalkan di filter
        $this->actingAs($this->admin())->get(route('admin.legalisir.index', ['status' => 'Dibatalkan']))->assertOk();
    }

    // ---------- Profil ----------

    public function test_profil_siswa_menampilkan_data_dan_riwayat_kelas(): void
    {
        [$andi, $user] = $this->siswa('1', 'Andi Profil');

        $this->actingAs($user)->get(route('siswa.profil'))->assertOk()
            ->assertSee('Andi Profil')->assertSee('XII RPL A')->assertSee(TahunAjaran::aktif()->nama);

        [, $lulus] = $this->siswa('9', 'Sari Alumni', true);
        $this->actingAs($lulus)->get(route('siswa.profil'))->assertOk()->assertSee('Lulus')->assertSee('2020/2021');

        $yatim = User::create(['name' => 'Y', 'email' => 'y@x.test', 'password' => Hash::make('x'), 'role' => 'siswa']);
        $this->actingAs($yatim)->get(route('siswa.profil'))->assertOk()->assertSee('belum terhubung');
    }

    public function test_halaman_layanan_hanya_untuk_role_yang_sesuai(): void
    {
        [, $siswa] = $this->siswa();
        $this->get(route('siswa.rapor.create'))->assertRedirect();
        $this->actingAs($siswa);
        foreach (['admin.peminjaman-rapor.index', 'admin.pengambilan-ijazah.index'] as $r) {
            $this->get(route($r))->assertForbidden();
        }
        $this->actingAs($this->admin());
        foreach (['siswa.rapor.create', 'siswa.ijazah.create', 'siswa.profil'] as $r) {
            $this->get(route($r))->assertForbidden();
        }
    }
}
