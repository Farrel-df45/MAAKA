<?php

namespace Tests\Feature\Admin;

use App\Models\Dokumen;
use App\Models\Guru;
use App\Models\Kelas;
use App\Models\Legalisir;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Models\WaliKelas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SiswaPortalTest extends TestCase
{
    use RefreshDatabase;

    // Siswa + akun login, ditempatkan di kelas dengan wali kelas
    private function siswaDenganAkun(string $nis = '2026001', string $nama = 'Andi Pratama'): array
    {
        $kelas = Kelas::firstOrCreate(['nama' => 'XII RPL A'], ['tingkat' => 'XII']);
        $siswa = Siswa::create(['nis' => $nis, 'nama' => $nama, 'kelas' => $kelas->nama, 'jurusan' => 'RPL']);
        $siswa->tempatkanDiKelas($kelas, TahunAjaran::aktif());
        $user = User::create(['name' => $nama, 'email' => "{$nis}@siswa.test", 'password' => Hash::make('rahasia123'), 'role' => 'siswa', 'siswa_id' => $siswa->id]);

        if (!WaliKelas::where('kelas_id', $kelas->id)->exists()) {
            $guru = Guru::create(['nip' => '800', 'nama' => 'Bu Wali', 'email' => 'wali@sekolah.test', 'jabatan' => 'Guru']);
            WaliKelas::tetapkan($guru, $kelas, TahunAjaran::aktif());
        }

        return [$siswa, $user];
    }

    private function dokumen(Siswa $siswa, string $nama, string $status = Dokumen::STATUS_TERVERIFIKASI, string $path = 'dokumen/x.pdf'): Dokumen
    {
        return Dokumen::create(['siswa_id' => $siswa->id, 'nama_dokumen' => $nama, 'kategori' => 'Rapor', 'file_path' => $path, 'status' => $status]);
    }

    public function test_dashboard_siswa_menampilkan_kelas_wali_dan_ringkasan(): void
    {
        [$siswa, $user] = $this->siswaDenganAkun();
        $this->dokumen($siswa, 'Rapor Smt 5');
        $this->dokumen($siswa, 'Sertifikat', Dokumen::STATUS_MENUNGGU);

        $this->actingAs($user)->get(route('siswa.dashboard'))->assertOk()
            ->assertSee('Dashboard Siswa')->assertSee('XII RPL A')->assertSee('Bu Wali')->assertSee('Rapor Smt 5');
    }

    public function test_siswa_hanya_melihat_dokumen_miliknya(): void
    {
        Storage::fake('local');
        [$andi, $userAndi] = $this->siswaDenganAkun('1', 'Andi');
        [$budi] = $this->siswaDenganAkun('2', 'Budi');
        Storage::disk('local')->put('dokumen/andi.pdf', 'isi-andi');
        $milikAndi = $this->dokumen($andi, 'Rapor Andi', Dokumen::STATUS_TERVERIFIKASI, 'dokumen/andi.pdf');
        $milikBudi = $this->dokumen($budi, 'Rapor Budi');

        $this->actingAs($userAndi);
        $this->get(route('siswa.dokumen.index'))->assertOk()->assertSee('Rapor Andi')->assertDontSee('Rapor Budi');
        $this->get(route('siswa.dokumen.berkas', $milikAndi))->assertOk();
        $this->get(route('siswa.dokumen.berkas', $milikBudi))->assertForbidden();

        // filter status
        $this->dokumen($andi, 'Dokumen Tertunda', Dokumen::STATUS_MENUNGGU);
        $this->get(route('siswa.dokumen.index', ['status' => Dokumen::STATUS_MENUNGGU]))
            ->assertSee('Dokumen Tertunda')->assertDontSee('Rapor Andi');
    }

    public function test_siswa_mengajukan_legalisir_hanya_untuk_dokumen_terverifikasi_miliknya(): void
    {
        [$andi, $user] = $this->siswaDenganAkun('1', 'Andi');
        [$budi] = $this->siswaDenganAkun('2', 'Budi');
        $ok = $this->dokumen($andi, 'Ijazah Andi');
        $tertunda = $this->dokumen($andi, 'Rapor Tertunda', Dokumen::STATUS_MENUNGGU);
        $milikBudi = $this->dokumen($budi, 'Ijazah Budi');

        $this->actingAs($user)->get(route('siswa.legalisir.create'))->assertOk()
            ->assertSee('Ijazah Andi')->assertDontSee('Rapor Tertunda')->assertDontSee('Ijazah Budi');

        // berhasil
        $this->post(route('siswa.legalisir.store'), ['dokumen_id' => $ok->id, 'jumlah' => 2, 'keperluan' => 'Melamar kerja'])
            ->assertRedirect(route('siswa.legalisir.create'));
        $l = Legalisir::first();
        $this->assertSame($andi->id, $l->siswa_id);
        $this->assertSame(Legalisir::STATUS_MENUNGGU, $l->status);
        $this->assertSame(2, $l->jumlah);
        $this->get(route('siswa.legalisir.create'))->assertSee('Melamar kerja');

        // ditolak: dokumen belum terverifikasi, milik siswa lain, atau sudah ada pengajuan berjalan
        foreach ([$tertunda, $milikBudi, $ok] as $dok) {
            $this->post(route('siswa.legalisir.store'), ['dokumen_id' => $dok->id, 'jumlah' => 1, 'keperluan' => 'X'])
                ->assertSessionHasErrors('dokumen_id');
        }
        $this->assertSame(1, Legalisir::count());

        // setelah pengajuan selesai, dokumen boleh diajukan lagi
        $l->update(['status' => Legalisir::STATUS_SELESAI]);
        $this->post(route('siswa.legalisir.store'), ['dokumen_id' => $ok->id, 'jumlah' => 1, 'keperluan' => 'Daftar kuliah'])
            ->assertSessionHasNoErrors();
        $this->assertSame(2, Legalisir::count());

        // validasi jumlah dan keperluan
        $l2 = $this->dokumen($andi, 'Transkrip');
        $this->post(route('siswa.legalisir.store'), ['dokumen_id' => $l2->id, 'jumlah' => 0, 'keperluan' => ''])
            ->assertSessionHasErrors(['jumlah', 'keperluan']);
        $this->post(route('siswa.legalisir.store'), ['dokumen_id' => $l2->id, 'jumlah' => 11, 'keperluan' => 'X'])
            ->assertSessionHasErrors('jumlah');
    }

    public function test_pengajuan_siswa_tampil_di_admin(): void
    {
        [$andi, $user] = $this->siswaDenganAkun();
        $dok = $this->dokumen($andi, 'Ijazah Andi');
        $this->actingAs($user)->post(route('siswa.legalisir.store'), ['dokumen_id' => $dok->id, 'jumlah' => 1, 'keperluan' => 'Melamar kerja']);

        $admin = User::create(['name' => 'Admin', 'email' => 'admin@maaka.test', 'password' => Hash::make('x'), 'role' => 'admin']);
        $this->actingAs($admin)->get(route('admin.legalisir.index'))->assertOk()->assertSee('Andi Pratama')->assertSee('Ijazah Andi');
    }

    public function test_akun_siswa_tanpa_data_siswa_dan_pembatasan_role(): void
    {
        $yatim = User::create(['name' => 'Siswa Yatim', 'email' => 'y@x.test', 'password' => Hash::make('x'), 'role' => 'siswa']);
        $this->actingAs($yatim)->get(route('siswa.dashboard'))->assertOk()->assertSee('belum terhubung');
        $this->get(route('siswa.dokumen.index'))->assertOk();
        $this->get(route('siswa.legalisir.create'))->assertOk();
        $this->post(route('siswa.legalisir.store'), ['dokumen_id' => 1, 'jumlah' => 1, 'keperluan' => 'X'])->assertForbidden();

        $guru = User::create(['name' => 'G', 'email' => 'g@x.test', 'password' => Hash::make('x'), 'role' => 'guru']);
        $this->actingAs($guru)->get(route('siswa.dashboard'))->assertForbidden();
        $this->actingAs($guru)->get(route('siswa.legalisir.create'))->assertForbidden();
        auth()->logout();
        $this->get(route('siswa.dashboard'))->assertRedirect();
    }

    public function test_login_siswa_dengan_nis_masuk_ke_dashboard(): void
    {
        $this->siswaDenganAkun('2026555', 'Cici');

        $this->post(route('siswa.login.process'), ['login' => '2026555', 'password' => 'rahasia123'])
            ->assertRedirect(route('siswa.dashboard'));
        $this->get(route('siswa.dashboard'))->assertOk()->assertSee('Cici');
    }
}
