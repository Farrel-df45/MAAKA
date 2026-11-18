<?php

namespace App\Http\Controllers\Siswa;

use App\Http\Controllers\Controller;
use App\Models\Notifikasi;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotifikasiController extends Controller
{
    public function index(Request $request): View
    {
        $notifikasis = Notifikasi::where('user_id', $request->user()->id)
            ->latest()->latest('id')->paginate(15);

        return view('siswa.notifikasi', compact('notifikasis'));
    }

    // Tandai dibaca lalu buka halaman terkait
    public function baca(Request $request, Notifikasi $notifikasi): RedirectResponse
    {
        abort_unless($notifikasi->user_id === $request->user()->id, 403);

        $notifikasi->update(['dibaca_pada' => $notifikasi->dibaca_pada ?? now()]);

        return redirect($notifikasi->url ?: route('siswa.notifikasi'));
    }

    public function bacaSemua(Request $request): RedirectResponse
    {
        Notifikasi::where('user_id', $request->user()->id)->whereNull('dibaca_pada')->update(['dibaca_pada' => now()]);

        return back()->with('success', 'Semua notifikasi ditandai sudah dibaca.');
    }
}
