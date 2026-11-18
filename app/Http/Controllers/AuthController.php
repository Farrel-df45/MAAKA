<?php

namespace App\Http\Controllers;

use App\Models\Guru;
use App\Models\Siswa;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    // Halaman login Admin dan Guru
    public function showLogin()
    {
        return view('auth.login-admin');
    }

    // Halaman login Siswa
    public function showSiswaLogin()
    {
        return view('auth.login');
    }

    // Proses login Admin dan Guru
    public function login(Request $request)
    {
        return $this->authenticate($request, ['admin', 'guru']);
    }

    // Proses login Siswa
    public function loginSiswa(Request $request)
    {
        return $this->authenticate($request, ['siswa']);
    }

    // Autentikasi berdasarkan role
    private function authenticate(Request $request, array $allowedRoles)
    {
        $credentials = $request->validate([
            'login' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $login = $credentials['login'];

        // Guru login memakai NIP, siswa memakai NIS: cari akun yang terhubung
        if (in_array('guru', $allowedRoles, true)) {
            $guru = Guru::with('user')->where('nip', $login)->first();

            if ($guru?->user) {
                $login = $guru->user->email;
            }
        }

        if (in_array('siswa', $allowedRoles, true)) {
            $siswa = Siswa::with('user')->where('nis', $login)->first();

            if ($siswa?->user) {
                $login = $siswa->user->email;
            }
        }

        $success = Auth::attempt([
            'email' => $login,
            'password' => $credentials['password'],
        ], $request->boolean('remember'));

        if (!$success) {
            return back()
                ->withErrors([
                    'login' => 'Email/NIS/NIP atau kata sandi tidak sesuai.',
                ])
                ->onlyInput('login');
        }

        $user = Auth::user();

        if (!in_array($user->role, $allowedRoles, true)) {
            Auth::logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return back()
                ->withErrors([
                    'login' => 'Akun tidak diizinkan masuk melalui halaman ini.',
                ])
                ->onlyInput('login');
        }

        $request->session()->regenerate();

        return match ($user->role) {
            'admin' => redirect()->route('admin.dashboard'),
            'guru' => redirect()->route('guru.dashboard'),
            'siswa' => redirect()->route('siswa.dashboard'),
            default => redirect()->route('siswa.login'),
        };
    }

    // Logout
    public function logout(Request $request)
    {
        $role = Auth::user()?->role;

        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return $role === 'siswa'
            ? redirect()->route('siswa.login')
            : redirect()->route('login');
    }
}
