<?php

use App\Http\Controllers\Admin\AkunController;
use App\Http\Controllers\Admin\DokumenController;
use App\Http\Controllers\Admin\GuruController;
use App\Http\Controllers\Admin\KelasController;
use App\Http\Controllers\Admin\PeminjamanRaporController as AdminPeminjamanRaporController;
use App\Http\Controllers\Admin\PengambilanIjazahController as AdminPengambilanIjazahController;
use App\Http\Controllers\Admin\TahunAjaranController;
use App\Http\Controllers\Admin\LegalisirController as AdminLegalisirController;
use App\Http\Controllers\Admin\SiswaController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Guru\DashboardController as GuruDashboardController;
use App\Http\Controllers\Siswa\DashboardController as SiswaDashboardController;
use App\Http\Controllers\Siswa\DokumenController as SiswaDokumenController;
use App\Http\Controllers\Siswa\LegalisirController as SiswaLegalisirController;
use App\Http\Controllers\Siswa\PeminjamanRaporController as SiswaPeminjamanRaporController;
use App\Http\Controllers\Siswa\PengambilanIjazahController as SiswaPengambilanIjazahController;
use App\Http\Controllers\Siswa\ProfilController as SiswaProfilController;
use App\Http\Controllers\Guru\DokumenController as GuruDokumenController;
use App\Models\AktivitasGuru;
use App\Models\Dokumen;
use App\Models\Guru;
use App\Models\Legalisir;
use App\Models\Siswa;
use App\Models\User;
use Illuminate\Support\Facades\Route;

// Halaman utama: login Siswa
Route::get('/', function () {
    return redirect()->route('siswa.login');
});

// Login Siswa
Route::get('/siswa/login', [AuthController::class, 'showSiswaLogin'])
    ->name('siswa.login');

Route::post('/siswa/login', [AuthController::class, 'loginSiswa'])
    ->name('siswa.login.process');

// Kompatibilitas dengan form login Siswa yang lama
Route::get('/login', function () {
    return redirect()->route('siswa.login');
});

Route::post('/login', [AuthController::class, 'loginSiswa'])
    ->name('login.process');

// Login Admin dan Guru
Route::get('/guru/login', [AuthController::class, 'showLogin'])
    ->name('login');

Route::post('/guru/login', [AuthController::class, 'login'])
    ->name('staff.login.process');

// Logout
Route::post('/logout', [AuthController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');

// Dashboard Admin
Route::middleware(['auth', 'role:admin', 'catat.admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/dashboard', function () {
            return view('admin.dashboard', [
                'totalSiswa' => Siswa::count(),
                'totalGuru' => Guru::count(),
                'totalAdmin' => User::where('role', 'admin')->count(),
                'totalDokumen' => Dokumen::count(),
                'dokumenMenunggu' => Dokumen::where(
                    'status',
                    Dokumen::STATUS_MENUNGGU
                )->count(),
                'totalLegalisir' => Legalisir::count(),
                'legalisirMenunggu' => Legalisir::where(
                    'status',
                    Legalisir::STATUS_MENUNGGU
                )->count(),
                'tahunAktif' => \App\Models\TahunAjaran::aktif(),
                'totalKelas' => \App\Models\Kelas::count(),
                'rporTunggu' => \App\Models\PeminjamanRapor::where('status', \App\Models\PeminjamanRapor::STATUS_MENUNGGU)->count(),
                'rporTerlambat' => \App\Models\PeminjamanRapor::where('status', \App\Models\PeminjamanRapor::STATUS_DIPINJAM)
                    ->whereDate('tanggal_kembali', '<', now()->toDateString())->count(),
                'ijazahTunggu' => \App\Models\PengambilanIjazah::where('status', \App\Models\PengambilanIjazah::STATUS_MENUNGGU)->count(),
                'belumLengkap' => \App\Http\Controllers\Admin\KelengkapanController::data(request())['ringkasan']['belum'],
                'aktivitasAdmin' => \App\Models\AktivitasAdmin::latest()->latest('id')->take(5)->get(),
                'aktivitasGuru' => AktivitasGuru::latest()->latest('id')->take(8)->get(),
                'aktivitasHariIni' => AktivitasGuru::where('created_at', '>=', now()->startOfDay())->count(),
                'dokumenTerbaru' => Dokumen::with('siswa')
                    ->latest()
                    ->take(5)
                    ->get(),
            ]);
        })->name('dashboard');

        // Data Siswa (tambah, lihat, ubah, hapus) + riwayat kelas & dokumen per tahun ajaran
        Route::get('siswa/{siswa}/riwayat', [SiswaController::class, 'riwayat'])->name('siswa.riwayat');
        Route::get('impor/{jenis}', [\App\Http\Controllers\Admin\ImporController::class, 'create'])->name('impor.create');
        Route::get('impor/{jenis}/contoh', [\App\Http\Controllers\Admin\ImporController::class, 'contoh'])->name('impor.contoh');
        Route::post('impor/{jenis}', [\App\Http\Controllers\Admin\ImporController::class, 'store'])->name('impor.store');
        Route::get('ekspor/{jenis}', \App\Http\Controllers\Admin\EksporController::class)->name('ekspor');
        Route::get('kelengkapan', [\App\Http\Controllers\Admin\KelengkapanController::class, 'index'])->name('kelengkapan.index');
        Route::get('aktivitas-admin', [\App\Http\Controllers\Admin\AktivitasAdminController::class, 'index'])->name('aktivitas-admin.index');
        Route::resource('siswa', SiswaController::class)->except('show');

        // Data Guru (+ riwayat unggah/ubah/hapus dokumen oleh guru)
        Route::get('guru/aktivitas', [GuruController::class, 'aktivitas'])->name('guru.aktivitas');
        Route::get('guru/{guru}/riwayat', [GuruController::class, 'aktivitas'])->name('guru.riwayat');
        Route::get('guru/{guru}/wali-kelas', [GuruController::class, 'waliKelas'])->name('guru.wali');
        Route::resource('guru', GuruController::class)
            ->except('show')
            ->parameters(['guru' => 'guru']);

        // Dokumen Akademik (+ lihat berkas & ubah status verifikasi)
        Route::get('dokumen/{dokumen}/berkas', [DokumenController::class, 'berkas'])
            ->name('dokumen.berkas');
        Route::post('dokumen/massal', [DokumenController::class, 'massal'])->name('dokumen.massal');
        Route::patch('dokumen/{dokumen}/status', [DokumenController::class, 'updateStatus'])
            ->name('dokumen.status');
        Route::resource('dokumen', DokumenController::class)
            ->except('show')
            ->parameters(['dokumen' => 'dokumen']);

        // Permohonan Legalisir (+ proses status)
        // Peminjaman rapor & pengambilan ijazah asli
        Route::get('peminjaman-rapor', [AdminPeminjamanRaporController::class, 'index'])->name('peminjaman-rapor.index');
        Route::get('peminjaman-rapor/{peminjaman}', [AdminPeminjamanRaporController::class, 'show'])->name('peminjaman-rapor.show');
        Route::patch('peminjaman-rapor/{peminjaman}/status', [AdminPeminjamanRaporController::class, 'updateStatus'])->name('peminjaman-rapor.status');
        Route::get('pengambilan-ijazah', [AdminPengambilanIjazahController::class, 'index'])->name('pengambilan-ijazah.index');
        Route::get('pengambilan-ijazah/{pengambilan}', [AdminPengambilanIjazahController::class, 'show'])->name('pengambilan-ijazah.show');
        Route::patch('pengambilan-ijazah/{pengambilan}/status', [AdminPengambilanIjazahController::class, 'updateStatus'])->name('pengambilan-ijazah.status');

        Route::patch('legalisir/{legalisir}/status', [AdminLegalisirController::class, 'updateStatus'])
            ->name('legalisir.status');
        Route::resource('legalisir', AdminLegalisirController::class)
            ->only(['index', 'show', 'destroy']);

        // Kelas (+ wali kelas tahun ajaran aktif)
        Route::get('kelas', [KelasController::class, 'index'])->name('kelas.index');
        Route::get('kelas/create', [KelasController::class, 'create'])->name('kelas.create');
        Route::post('kelas', [KelasController::class, 'store'])->name('kelas.store');
        Route::get('kelas/{kelas}/edit', [KelasController::class, 'edit'])->name('kelas.edit');
        Route::put('kelas/{kelas}', [KelasController::class, 'update'])->name('kelas.update');
        Route::delete('kelas/{kelas}', [KelasController::class, 'destroy'])->name('kelas.destroy');

        // Tahun ajaran & naik kelas massal
        Route::get('tahun-ajaran', [TahunAjaranController::class, 'index'])->name('tahun-ajaran.index');
        Route::get('naik-kelas', [TahunAjaranController::class, 'create'])->name('naik-kelas.create');
        Route::post('naik-kelas', [TahunAjaranController::class, 'store'])->name('naik-kelas.store');

        // Manajemen Akun
        Route::resource('akun', AkunController::class)
            ->except('show')
            ->parameters(['akun' => 'akun']);
    });

// Dashboard Guru
Route::middleware(['auth', 'role:guru'])
    ->prefix('guru')
    ->name('guru.')
    ->group(function () {
        Route::get('/dashboard', [GuruDashboardController::class, 'index'])->name('dashboard');

        // Dokumen akademik milik guru (unggah, ubah, hapus tercatat di riwayat)
        Route::get('dokumen/{dokumen}/berkas', [GuruDokumenController::class, 'berkas'])->name('dokumen.berkas');
        Route::resource('dokumen', GuruDokumenController::class)
            ->except('show')
            ->parameters(['dokumen' => 'dokumen']);
    });

// Dashboard Siswa
Route::middleware(['auth', 'role:siswa'])
    ->prefix('siswa')
    ->name('siswa.')
    ->group(function () {
        Route::get('/dashboard', [SiswaDashboardController::class, 'index'])->name('dashboard');

        // Dokumen milik siswa (hanya lihat)
        Route::get('/dokumen', [SiswaDokumenController::class, 'index'])->name('dokumen.index');
        Route::get('/dokumen/{dokumen}/berkas', [SiswaDokumenController::class, 'berkas'])->name('dokumen.berkas');

        Route::get('/dokumen/unggah', [SiswaDokumenController::class, 'create'])->name('dokumen.create');
        Route::post('/dokumen', [SiswaDokumenController::class, 'store'])->name('dokumen.store');
        Route::delete('/dokumen/{dokumen}', [SiswaDokumenController::class, 'destroy'])->name('dokumen.destroy');

        // Status semua pengajuan & notifikasi
        Route::get('/pengajuan', [\App\Http\Controllers\Siswa\PengajuanController::class, 'index'])->name('pengajuan');
        Route::get('/notifikasi', [\App\Http\Controllers\Siswa\NotifikasiController::class, 'index'])->name('notifikasi');
        Route::post('/notifikasi/baca-semua', [\App\Http\Controllers\Siswa\NotifikasiController::class, 'bacaSemua'])->name('notifikasi.baca-semua');
        Route::post('/notifikasi/{notifikasi}/baca', [\App\Http\Controllers\Siswa\NotifikasiController::class, 'baca'])->name('notifikasi.baca');

        // Pengajuan legalisir
        Route::get('/legalisir', [SiswaLegalisirController::class, 'create'])->name('legalisir.create');
        Route::post('/legalisir', [SiswaLegalisirController::class, 'store'])->name('legalisir.store');
        Route::patch('/legalisir/{legalisir}/batal', [SiswaLegalisirController::class, 'batal'])->name('legalisir.batal');

        // Peminjaman rapor
        Route::get('/rapor', [SiswaPeminjamanRaporController::class, 'create'])->name('rapor.create');
        Route::post('/rapor', [SiswaPeminjamanRaporController::class, 'store'])->name('rapor.store');
        Route::patch('/rapor/{peminjaman}/batal', [SiswaPeminjamanRaporController::class, 'batal'])->name('rapor.batal');

        // Pengambilan ijazah asli (siswa lulus)
        Route::get('/ijazah', [SiswaPengambilanIjazahController::class, 'create'])->name('ijazah.create');
        Route::post('/ijazah', [SiswaPengambilanIjazahController::class, 'store'])->name('ijazah.store');
        Route::patch('/ijazah/{pengambilan}/batal', [SiswaPengambilanIjazahController::class, 'batal'])->name('ijazah.batal');

        // Profil siswa
        Route::get('/profil', [SiswaProfilController::class, 'index'])->name('profil');
    });
