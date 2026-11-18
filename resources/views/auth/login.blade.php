<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - MAAKA</title>

    <link rel="stylesheet" href="{{ asset('css/maaka.css') }}">
</head>

<body class="login-page">

<div class="login-layout">

    <section class="login-showcase">

        <div class="showcase-brand">
            <div class="showcase-logo">▣</div>

            <div>
                <h2>MAAKA</h2>
                <span>ADMINISTRASI AKADEMIK</span>
            </div>
        </div>

        <div class="showcase-content">

            <span class="showcase-label">
                SISTEM INFORMASI AKADEMIK
            </span>

            <h1>
                Layanan Akademik
                <br>
                Terpadu
            </h1>

            <p>
                Sistem informasi untuk mengelola dokumen akademik,
                pengajuan layanan, dan administrasi siswa secara
                mudah, cepat, dan transparan.
            </p>

            <div class="showcase-services">

                <div>
                    <span>▣</span>
                    <strong>Peminjaman Rapor Fisik</strong>
                </div>

                <div>
                    <span>✓</span>
                    <strong>Legalisir Ijazah & Rapor</strong>
                </div>

                <div>
                    <span>▤</span>
                    <strong>Pengambilan Ijazah Asli</strong>
                </div>

            </div>

        </div>

        <div class="showcase-footer">
            MAAKA — Manajemen Administrasi Akademik
        </div>

    </section>


    <section class="login-form-area">

        <div class="login-card">

            <div class="login-heading">
                <span>Selamat Datang</span>

                <h1>Masuk</h1>

                <p>
                    Masuk untuk mengakses layanan akademik
                </p>
            </div>

            @if($errors->any())
                <div class="login-error">
                    {{ $errors->first() }}
                </div>
            @endif

            <form action="{{ route('login.process') }}" method="POST">
                @csrf

                <div class="form-group">
                    <label>Email / NIS</label>

                    <div class="input-wrapper">
                        <span>@</span>

                        <input
                            type="text"
                            name="login"
                            value="{{ old('login') }}"
                            placeholder="nama@email.com atau NIS"
                            required
                        >
                    </div>
                </div>

                <div class="form-group">
                    <label>Kata Sandi</label>

                    <div class="input-wrapper">
                        <span>⌑</span>

                        <input
                            type="password"
                            name="password"
                            placeholder="Masukkan kata sandi"
                            required
                        >
                    </div>
                </div>

                <div class="login-options">
                    <label>
                        <input type="checkbox" name="remember">
                        <span>Ingat saya</span>
                    </label>

                    <a href="#">
                        Lupa password?
                    </a>
                </div>

                <button class="login-button" type="submit">
                    Masuk
                </button>
            </form>

            <div class="login-help">
                Hubungi Tata Usaha jika mengalami masalah saat login.
            </div>

        </div>

    </section>

</div>

</body>
</html>