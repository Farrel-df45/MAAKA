<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AktivitasAdmin;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AktivitasAdminController extends Controller
{
    public function index(Request $request): View
    {
        $q = trim((string) $request->input('q', ''));
        $aksi = (string) $request->input('aksi', '');

        $aktivitas = AktivitasAdmin::query()
            ->when($q !== '', fn ($query) => $query->where(
                fn ($w) => $w->where('admin_nama', 'like', "%{$q}%")
                    ->orWhere('objek', 'like', "%{$q}%")
                    ->orWhere('keterangan', 'like', "%{$q}%")
            ))
            ->when($aksi !== '', fn ($query) => $query->where('aksi', $aksi))
            ->latest()->latest('id')
            ->paginate(15)
            ->withQueryString();

        return view('admin.aktivitas-admin.index', [
            'aktivitas' => $aktivitas,
            'q' => $q,
            'aksi' => $aksi,
            'daftarAksi' => AktivitasAdmin::query()->distinct()->orderBy('aksi')->pluck('aksi'),
        ]);
    }
}
