<?php

namespace Tests\Feature\Admin;

use App\Models\Kelas;
use App\Models\Siswa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class SiswaAkunTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create(['name' => 'Admin', 'email' => 'admin@maaka.test', 'password' => Hash::make('rahasia123'), 'role' => 'admin']);
    }

    private function kelas(string $nama = 'XII RPL A'): Kelas
    {
        return Kelas::firstOrCreate(['nama' => $nama], ['tingkat' => 'XII']);
    }

    // Siswa langsung di database, sudah ditempatkan di kelas tahun ajaran aktif
    private function buatSiswa(): Siswa
    {
        $siswa = Siswa::create(['nis' => '2026001', 'nama' => 'Andi Pratama', 'kelas' => 'XII RPL A', 'jurusan' => 'RPL']);
        $siswa->tempatkanDiKelas($this->kelas(), \App\Models\TahunAjaran::aktif());

        return $siswa;
    }

    private function data(array $extra = []): array
    {
        return array_merge(['nis' => '2026001', 'nama' => 'Andi Pratama', 'kelas_id' => $this->kelas()->id, 'jurusan' => 'RPL'], $extra);
    }

    public function test_buat_siswa_dengan_akun_dan_login_dengan_nis(): void
    {
        $this->actingAs($this->admin());

        $this->post(route('admin.siswa.store'), $this->data([
            'email' => 'andi@siswa.test', 'password' => 'siswa12345', 'password_confirmation' => 'siswa12345',
        ]))->assertRedirect(route('admin.siswa.index'));

        $siswa = Siswa::firstWhere('nis', '2026001');
        $akun = User::firstWhere('siswa_id', $siswa->id);
        $this->assertSame('siswa', $akun->role);
        $this->assertSame('Andi Pratama', $akun->name);
        $this->assertTrue(Hash::check('siswa12345', $akun->password));

        // akun siswa muncul di Manajemen Akun
        $this->get(route('admin.akun.index', ['role' => 'siswa']))->assertSee('andi@siswa.test');

        Auth::logout();

        // login lewat halaman siswa: NIS dan email sama-sama bisa
        $this->post('/siswa/login', ['login' => '2026001', 'password' => 'siswa12345'])->assertRedirect(route('siswa.dashboard'));
        $this->assertAuthenticatedAs($akun);
        Auth::logout();
        $this->post('/siswa/login', ['login' => 'andi@siswa.test', 'password' => 'siswa12345'])->assertRedirect(route('siswa.dashboard'));
        Auth::logout();
        $this->post('/siswa/login', ['login' => '2026001', 'password' => 'salah'])->assertSessionHasErrors('login');

        // siswa tidak bisa masuk lewat halaman admin/guru
        $this->post('/guru/login', ['login' => '2026001', 'password' => 'siswa12345'])->assertSessionHasErrors('login');
        $this->assertGuest();
    }

    public function test_siswa_tanpa_password_tidak_membuat_akun_dan_validasi_akun(): void
    {
        $this->actingAs($this->admin());

        $this->post(route('admin.siswa.store'), $this->data())->assertSessionHasNoErrors();
        $this->assertSame(1, Siswa::count());
        $this->assertSame(1, User::count());

        // password diisi tapi email kosong / password pendek / email dipakai akun lain
        $this->post(route('admin.siswa.store'), $this->data(['nis' => '2', 'password' => 'siswa12345', 'password_confirmation' => 'siswa12345']))
            ->assertSessionHasErrors('email');
        $this->post(route('admin.siswa.store'), $this->data(['nis' => '2', 'email' => 'x@siswa.test', 'password' => 'pendek', 'password_confirmation' => 'pendek']))
            ->assertSessionHasErrors('password');
        $this->post(route('admin.siswa.store'), $this->data(['nis' => '2', 'email' => 'admin@maaka.test', 'password' => 'siswa12345', 'password_confirmation' => 'siswa12345']))
            ->assertSessionHasErrors('email');
        $this->assertSame(1, Siswa::count());
    }

    public function test_tambah_akun_untuk_siswa_lama_ubah_dan_reset_password(): void
    {
        $this->actingAs($this->admin());
        $siswa = $this->buatSiswa();

        // edit + isi email/password => akun dibuat
        $this->put(route('admin.siswa.update', $siswa), $this->data([
            'email' => 'andi@siswa.test', 'password' => 'siswa12345', 'password_confirmation' => 'siswa12345',
        ]))->assertRedirect(route('admin.siswa.index'));
        $akun = User::firstWhere('siswa_id', $siswa->id);
        $this->assertNotNull($akun);

        // ubah nama + email tanpa password => password lama tetap
        $this->put(route('admin.siswa.update', $siswa), $this->data(['nama' => 'Andi P.', 'email' => 'andi2@siswa.test']));
        $akun->refresh();
        $this->assertSame('Andi P.', $akun->name);
        $this->assertSame('andi2@siswa.test', $akun->email);
        $this->assertTrue(Hash::check('siswa12345', $akun->password));

        // reset password
        $this->put(route('admin.siswa.update', $siswa), $this->data(['nama' => 'Andi P.', 'email' => 'andi2@siswa.test', 'password' => 'baru12345', 'password_confirmation' => 'baru12345']));
        $this->assertTrue(Hash::check('baru12345', $akun->fresh()->password));

        $this->get(route('admin.siswa.edit', $siswa))->assertOk()->assertSee('andi2@siswa.test');
        $this->get(route('admin.siswa.index'))->assertOk()->assertSee('Login: 2026001');
    }

    public function test_hapus_siswa_ikut_menghapus_akunnya(): void
    {
        $this->actingAs($this->admin());
        $siswa = $this->buatSiswa();
        $akun = User::create(['name' => 'Andi', 'email' => 'a@s.test', 'password' => Hash::make('x'), 'role' => 'siswa', 'siswa_id' => $siswa->id]);

        $this->delete(route('admin.siswa.destroy', $siswa))->assertRedirect(route('admin.siswa.index'));
        $this->assertModelMissing($siswa);
        $this->assertModelMissing($akun);
    }

    public function test_sesi_kedaluwarsa_419_diarahkan_ke_login_bukan_halaman_buntu(): void
    {
        Route::middleware('web')->group(function () {
            Route::post('/_uji-419', fn () => throw new TokenMismatchException('x'));
            Route::post('/siswa/_uji-419', fn () => throw new TokenMismatchException('x'));
        });

        $this->post('/_uji-419')->assertRedirect(route('login'))->assertSessionHasErrors('login');
        $this->post('/siswa/_uji-419')->assertRedirect(route('siswa.login'))->assertSessionHasErrors('login');
    }
}
