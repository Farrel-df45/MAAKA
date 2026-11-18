<?php

namespace App\Http\Middleware;

use App\Models\AktivitasAdmin;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

// Mencatat setiap perubahan data yang berhasil dilakukan admin
class CatatAktivitasAdmin
{
    private const AKSI = [
        'store' => 'Tambah', 'update' => 'Ubah', 'destroy' => 'Hapus', 'status' => 'Ubah Status',
        'massal' => 'Verifikasi Massal', 'impor' => 'Impor Data',
    ];

    private const OBJEK = [
        'siswa' => 'Siswa', 'guru' => 'Guru', 'dokumen' => 'Dokumen', 'legalisir' => 'Legalisir',
        'kelas' => 'Kelas', 'akun' => 'Akun', 'peminjaman-rapor' => 'Peminjaman Rapor',
        'pengambilan-ijazah' => 'Pengambilan Ijazah', 'naik-kelas' => 'Naik Kelas', 'impor' => 'Siswa/Guru (CSV)',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        try {
            $this->catat($request, $response);
        } catch (\Throwable $e) {
            report($e); // pencatatan tidak boleh menggagalkan aksi admin
        }

        return $response;
    }

    private function catat(Request $request, Response $response): void
    {
        if ($request->isMethodSafe() || !$request->user() || $response->getStatusCode() >= 400) {
            return;
        }

        // 'errors' yang baru di-flash oleh request ini (bukan sisa request sebelumnya) = validasi gagal
        $gagal = $request->hasSession() && in_array('errors', $request->session()->get('_flash.new', []), true);
        $nama = $request->route()?->getName();
        if ($gagal || !$nama || !str_starts_with($nama, 'admin.')) {
            return;
        }

        [, $objek, $aksi] = array_pad(explode('.', $nama), 3, null);
        $aksi = match (true) {
            $aksi === 'store' && $objek === 'naik-kelas' => 'Proses',
            $aksi === 'store' && $objek === 'impor' => 'Impor Data',
            default => self::AKSI[$aksi] ?? null,
        };
        if (!$aksi) {
            return;
        }

        $label = null;
        foreach ($request->route()->parameters() as $param) {
            if (is_object($param)) {
                $label = $param->nama ?? $param->nama_dokumen ?? $param->name ?? ('#' . $param->getKey());
                break;
            }
        }

        $ket = [];
        if ($request->filled('status')) {
            $ket[] = 'Status: ' . $request->input('status');
        }
        if ($request->filled('aksi')) {
            $ket[] = 'Aksi: ' . $request->input('aksi');
        }
        if (is_array($request->input('ids'))) {
            $ket[] = count($request->input('ids')) . ' dokumen';
        }

        AktivitasAdmin::create([
            'user_id' => $request->user()->id,
            'admin_nama' => $request->user()->name,
            'aksi' => $aksi,
            'objek' => self::OBJEK[$objek] ?? ucfirst((string) $objek),
            'keterangan' => trim(($label ? "{$label}. " : '') . implode(' · ', $ket)) ?: null,
        ]);
    }
}
