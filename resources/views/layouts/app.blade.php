<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'MAAKA')</title>
    <link rel="stylesheet" href="{{ asset('css/maaka.css') }}">
</head>
<body>

<div class="app-layout">
    <aside class="sidebar">
        <div class="brand">
            <div class="brand-logo">M</div>
            <div>
                <h2>MAAKA</h2>
                <span>Manajemen Akademik</span>
            </div>
        </div>

        <nav class="sidebar-nav">
            <a href="{{ route('dashboard') }}" class="{{ request()->routeIs('dashboard') ? 'active' : '' }}">
                <span>⌂</span>
                Dashboard
            </a>

            <a href="#">
                <span>▣</span>
                Dokumen Akademik
            </a>

            <a href="{{ route('legalisir.create') }}" class="{{ request()->routeIs('legalisir.*') ? 'active' : '' }}">
                <span>▤</span>
                Ajukan Legalisir
            </a>

            <a href="#">
                <span>◷</span>
                Riwayat Pengajuan
            </a>
        </nav>

        <div class="sidebar-bottom">
            <a href="#">
                <span>⚙</span>
                Pengaturan
            </a>
        </div>
    </aside>

    <main class="main-content">
        <header class="topbar">
            <div>
                <span class="page-label">Sistem Manajemen Dokumen Akademik</span>
            </div>

            <div class="topbar-user">
                <button class="notification">♢</button>

                <div class="user-info">
                    <strong>Admin MAAKA</strong>
                    <span>Admin/Kurikulum</span>
                </div>

                <div class="avatar">A</div>
            </div>
        </header>

        <section class="content">
            @if(session('success'))
                <div class="alert success">
                    <span>✓</span>
                    {{ session('success') }}
                </div>
            @endif

            @yield('content')
        </section>
    </main>
</div>

@stack('scripts')

</body>
</html>