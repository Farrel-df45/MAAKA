<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Guru;
use App\Models\Kelas;
use App\Models\RiwayatKelas;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\WaliKelas;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TahunAjaranController extends Controller
{
    public const LULUS = 'lulus';

    // Daftar tahun ajaran beserta ringkasan siswa dan wali kelasnya
    public function index(): View
    {
        return view('admin.tahun-ajaran.index', [
            'tahuns' => TahunAjaran::withCount(['riwayatKelas', 'waliKelas'])->orderByDesc('id')->get(),
        ]);
    }

    // Form naik kelas massal: tentukan kelas tujuan tiap kelas dan wali kelas tahun baru
    public function create(): View
    {
        $tahun = TahunAjaran::aktif();

        return view('admin.tahun-ajaran.naik-kelas', [
            'tahun' => $tahun,
            'namaSaran' => $this->namaTahunBerikutnya($tahun),
            'sumber' => $tahun ? $this->kelasSumber($tahun) : collect(),
            'semuaKelas' => Kelas::orderBy('tingkat')->orderBy('nama')->get(),
            'waliSaatIni' => $tahun
                ? WaliKelas::where('tahun_ajaran_id', $tahun->id)->pluck('guru_id', 'kelas_id')
                : collect(),
            'gurus' => Guru::orderBy('nama')->get(['id', 'nip', 'nama']),
            'saran' => $tahun ? $this->saranTujuan($this->kelasSumber($tahun)) : [],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $lama = TahunAjaran::aktif();
        abort_unless($lama, 422, 'Belum ada tahun ajaran aktif.');

        $sumber = $this->kelasSumber($lama);
        $idKelas = Kelas::pluck('id')->map(fn ($id) => (string) $id)->all();

        $data = $request->validate([
            'tahun_baru' => ['required', 'string', 'max:20', Rule::unique('tahun_ajarans', 'nama')],
            'tujuan' => ['array'],
            'tujuan.*' => ['nullable', Rule::in([...$idKelas, self::LULUS])],
            'wali' => ['array'],
            'wali.*' => ['nullable', 'exists:gurus,id'],
        ], [
            'tahun_baru.required' => 'Nama tahun ajaran baru wajib diisi.',
            'tahun_baru.unique' => 'Tahun ajaran ini sudah ada.',
            'tujuan.*.in' => 'Kelas tujuan tidak valid.',
        ]);

        // Setiap kelas yang masih punya siswa harus punya tujuan
        foreach ($sumber as $kelas) {
            if ($kelas->siswa_aktif_count > 0 && blank($data['tujuan'][$kelas->id] ?? null)) {
                throw ValidationException::withMessages([
                    "tujuan.{$kelas->id}" => "Pilih kelas tujuan untuk {$kelas->nama} (atau Lulus).",
                ]);
            }
        }

        // Satu guru hanya boleh menjadi wali satu kelas
        $guruDipilih = collect($data['wali'] ?? [])->filter()->values();
        if ($guruDipilih->count() !== $guruDipilih->unique()->count()) {
            throw ValidationException::withMessages([
                'wali' => 'Satu guru tidak boleh menjadi wali lebih dari satu kelas.',
            ]);
        }

        $ringkas = DB::transaction(function () use ($lama, $data, $sumber) {
            $lama->update(['aktif' => false]);
            $baru = TahunAjaran::create(['nama' => trim($data['tahun_baru']), 'aktif' => true]);

            $naik = 0;
            $lulus = 0;

            foreach ($sumber as $kelas) {
                $tujuan = $data['tujuan'][$kelas->id] ?? null;
                if (blank($tujuan)) {
                    continue;
                }

                $siswas = Siswa::whereIn('id', RiwayatKelas::where('kelas_id', $kelas->id)
                    ->where('tahun_ajaran_id', $lama->id)->pluck('siswa_id'))->get();

                foreach ($siswas as $siswa) {
                    if ($tujuan === self::LULUS) {
                        // Tanpa penempatan baru; riwayat kelas lama tetap tersimpan
                        $siswa->forceFill(['kelas' => 'Lulus'])->save();
                        $lulus++;
                    } else {
                        $siswa->tempatkanDiKelas(Kelas::findOrFail($tujuan), $baru);
                        $naik++;
                    }
                }
            }

            foreach ($data['wali'] ?? [] as $kelasId => $guruId) {
                if (filled($guruId)) {
                    WaliKelas::tetapkan(Guru::findOrFail($guruId), Kelas::findOrFail($kelasId), $baru);
                }
            }

            return compact('baru', 'naik', 'lulus');
        });

        return redirect()->route('admin.tahun-ajaran.index')->with(
            'success',
            "Tahun ajaran {$ringkas['baru']->nama} aktif. {$ringkas['naik']} siswa dipindahkan, {$ringkas['lulus']} siswa lulus. Riwayat tahun sebelumnya tetap tersimpan."
        );
    }

    // Kelas yang punya siswa pada tahun ajaran tertentu
    private function kelasSumber(TahunAjaran $tahun)
    {
        return Kelas::query()
            ->withCount(['siswaAktif'])
            ->orderBy('tingkat')->orderBy('nama')
            ->get()
            ->filter(fn ($k) => $k->siswa_aktif_count > 0 || $k->waliAktif()->exists())
            ->values();
    }

    // Saran tujuan: X -> XI, XI -> XII, XII -> lulus (nama kelas tujuan diganti tingkatnya)
    private function saranTujuan($sumber): array
    {
        $peta = ['X' => 'XI', 'XI' => 'XII'];
        $semua = Kelas::pluck('id', 'nama');
        $hasil = [];

        foreach ($sumber as $kelas) {
            if ($kelas->tingkat === 'XII') {
                $hasil[$kelas->id] = self::LULUS;
            } elseif (isset($peta[$kelas->tingkat])) {
                $nama = preg_replace('/^' . preg_quote($kelas->tingkat, '/') . '\b/', $peta[$kelas->tingkat], $kelas->nama);
                $hasil[$kelas->id] = $semua[$nama] ?? null;
            }
        }

        return $hasil;
    }

    private function namaTahunBerikutnya(?TahunAjaran $tahun): string
    {
        if ($tahun && preg_match('/^(\d{4})\/(\d{4})$/', $tahun->nama, $m)) {
            return ((int) $m[1] + 1) . '/' . ((int) $m[2] + 1);
        }

        return '';
    }
}
