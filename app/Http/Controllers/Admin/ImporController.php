<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Guru;
use App\Models\Kelas;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

// Impor siswa / guru dari CSV (pemisah koma atau titik koma). Baris yang valid tetap diimpor, baris bermasalah dilaporkan.
class ImporController extends Controller
{
    private const KOLOM = [
        'siswa' => ['nis', 'nama', 'kelas', 'jurusan', 'alamat', 'email', 'password'],
        'guru' => ['nip', 'nama', 'email', 'nomor_telepon', 'jabatan', 'password'],
    ];

    public function create(string $jenis): View
    {
        abort_unless(isset(self::KOLOM[$jenis]), 404);

        return view('admin.impor.create', ['jenis' => $jenis, 'kolom' => self::KOLOM[$jenis]]);
    }

    public function contoh(string $jenis)
    {
        abort_unless(isset(self::KOLOM[$jenis]), 404);

        $contoh = $jenis === 'siswa'
            ? ['2026101', 'Budi Santoso', 'X RPL A', 'RPL', 'Jl. Merdeka 1', 'budi@contoh.test', 'rahasia123']
            : ['198001012005011001', 'Ani Lestari', 'ani@contoh.test', '08123456789', 'Guru/Wali Kelas', 'rahasia123'];

        return response()->streamDownload(function () use ($jenis, $contoh) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, self::KOLOM[$jenis], ';');
            fputcsv($out, $contoh, ';');
            fclose($out);
        }, "contoh-impor-{$jenis}.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function store(Request $request, string $jenis): RedirectResponse
    {
        abort_unless(isset(self::KOLOM[$jenis]), 404);

        $request->validate(['berkas' => ['required', 'file', 'mimes:csv,txt', 'max:2048']], [
            'berkas.required' => 'Pilih berkas CSV terlebih dahulu.',
            'berkas.mimes' => 'Berkas harus berformat CSV.',
        ]);

        $baris = $this->baca($request->file('berkas')->getRealPath());

        if ($baris === null) {
            return back()->with('error', 'Berkas kosong atau header tidak sesuai. Unduh contoh berkas untuk melihat format yang benar.');
        }
        if (count($baris) > 1000) {
            return back()->with('error', 'Maksimal 1000 baris per impor.');
        }

        $hasil = $jenis === 'siswa' ? $this->imporSiswa($baris) : $this->imporGuru($baris);

        $pesan = "{$hasil['berhasil']} {$jenis} berhasil diimpor, " . count($hasil['gagal']) . ' baris dilewati.';

        return redirect()->route('admin.impor.create', $jenis)
            ->with(count($hasil['gagal']) ? 'error' : 'success', $pesan)
            ->with('gagal', $hasil['gagal']);
    }

    // Kembalikan daftar [nomorBaris => [kolom => nilai]] atau null bila header tidak sesuai
    private function baca(string $path): ?array
    {
        $isi = (string) file_get_contents($path);
        $isi = preg_replace('/^\xEF\xBB\xBF/', '', $isi);
        $baris = preg_split('/\r\n|\n|\r/', trim($isi));
        if (!$baris || $baris[0] === '') {
            return null;
        }

        $pemisah = substr_count($baris[0], ';') >= substr_count($baris[0], ',') ? ';' : ',';
        $header = array_map(fn ($h) => strtolower(trim($h)), str_getcsv($baris[0], $pemisah));

        if (!in_array('nama', $header, true)) {
            return null;
        }

        $hasil = [];
        foreach (array_slice($baris, 1) as $i => $teks) {
            if (trim($teks) === '') {
                continue;
            }
            $nilai = str_getcsv($teks, $pemisah);
            $hasil[$i + 2] = collect($header)->mapWithKeys(fn ($h, $k) => [$h => trim((string) ($nilai[$k] ?? ''))])->all();
        }

        return $hasil;
    }

    private function imporSiswa(array $baris): array
    {
        $tahun = TahunAjaran::aktif();
        if (!$tahun) {
            return ['berhasil' => 0, 'gagal' => ['Belum ada tahun ajaran aktif.']];
        }

        $kelasByNama = Kelas::all()->keyBy(fn ($k) => mb_strtolower($k->nama));
        $berhasil = 0;
        $gagal = [];
        $nisBerkas = [];
        $emailBerkas = [];

        foreach ($baris as $no => $r) {
            $v = Validator::make($r, [
                'nis' => ['required', 'max:50', 'unique:siswas,nis'],
                'nama' => ['required', 'max:255'],
                'kelas' => ['required'],
                'jurusan' => ['required', 'max:255'],
                'alamat' => ['nullable', 'max:255'],
                'email' => ['nullable', 'required_with:password', 'email', 'max:255', 'unique:users,email'],
                'password' => ['nullable', 'min:8'],
            ], ['unique' => ':attribute sudah terdaftar.', 'required_with' => 'email wajib bila password diisi.']);

            $kelas = $kelasByNama->get(mb_strtolower($r['kelas'] ?? ''));
            $galat = $v->errors()->all();
            if (($r['kelas'] ?? '') !== '' && !$kelas) {
                $galat[] = "Kelas \"{$r['kelas']}\" tidak ditemukan (buat dulu di menu Kelas).";
            }
            if (($r['nis'] ?? '') !== '' && in_array($r['nis'], $nisBerkas, true)) {
                $galat[] = 'NIS dobel di dalam berkas.';
            }
            if (($r['email'] ?? '') !== '' && in_array(strtolower($r['email']), $emailBerkas, true)) {
                $galat[] = 'Email dobel di dalam berkas.';
            }
            if ($galat) {
                $gagal[] = "Baris {$no}: " . implode(' ', $galat);
                continue;
            }

            DB::transaction(function () use ($r, $kelas, $tahun) {
                $siswa = Siswa::create([
                    'nis' => $r['nis'], 'nama' => $r['nama'], 'kelas' => $kelas->nama,
                    'jurusan' => $r['jurusan'], 'alamat' => $r['alamat'] ?: null,
                ]);
                $siswa->tempatkanDiKelas($kelas, $tahun);

                if (($r['password'] ?? '') !== '') {
                    User::create([
                        'name' => $siswa->nama, 'email' => $r['email'], 'password' => Hash::make($r['password']),
                        'role' => 'siswa', 'siswa_id' => $siswa->id,
                    ]);
                }
            });

            $nisBerkas[] = $r['nis'];
            if (($r['email'] ?? '') !== '') {
                $emailBerkas[] = strtolower($r['email']);
            }
            $berhasil++;
        }

        return compact('berhasil', 'gagal');
    }

    private function imporGuru(array $baris): array
    {
        $berhasil = 0;
        $gagal = [];
        $nipBerkas = [];
        $emailBerkas = [];

        foreach ($baris as $no => $r) {
            $v = Validator::make($r, [
                'nip' => ['required', 'max:50', 'unique:gurus,nip'],
                'nama' => ['required', 'max:255'],
                'email' => ['required', 'email', 'max:255', 'unique:gurus,email', 'unique:users,email'],
                'nomor_telepon' => ['nullable', 'max:30'],
                'jabatan' => ['nullable', 'max:255'],
                'password' => ['nullable', 'min:8'],
            ], ['unique' => ':attribute sudah terdaftar.']);

            $galat = $v->errors()->all();
            if (($r['nip'] ?? '') !== '' && in_array($r['nip'], $nipBerkas, true)) {
                $galat[] = 'NIP dobel di dalam berkas.';
            }
            if (($r['email'] ?? '') !== '' && in_array(strtolower($r['email']), $emailBerkas, true)) {
                $galat[] = 'Email dobel di dalam berkas.';
            }
            if ($galat) {
                $gagal[] = "Baris {$no}: " . implode(' ', $galat);
                continue;
            }

            DB::transaction(function () use ($r) {
                $guru = Guru::create([
                    'nip' => $r['nip'], 'nama' => $r['nama'], 'email' => $r['email'],
                    'nomor_telepon' => $r['nomor_telepon'] ?: null, 'jabatan' => $r['jabatan'] ?: 'Guru/Wali Kelas',
                ]);

                if (($r['password'] ?? '') !== '') {
                    User::create([
                        'name' => $guru->nama, 'email' => $guru->email, 'password' => Hash::make($r['password']),
                        'role' => 'guru', 'guru_id' => $guru->id,
                    ]);
                }
            });

            $nipBerkas[] = $r['nip'];
            $emailBerkas[] = strtolower($r['email']);
            $berhasil++;
        }

        return compact('berhasil', 'gagal');
    }
}
