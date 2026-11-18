<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'MAAKA')</title>

    <style>
        :root {
            --navy: #142b4a;
            --navy-hover: #23426a;
            --accent: #3b82f6;
            --bg: #f4f7fb;
            --card: #ffffff;
            --line: #e3e9f2;
            --text: #17243b;
            --muted: #6b7a90;
            --green: #15803d;  --green-bg: #dcfce7;
            --amber: #b45309;  --amber-bg: #fef3c7;
            --red: #b91c1c;    --red-bg: #fee2e2;
            --blue: #1d4ed8;   --blue-bg: #dbeafe;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: "Segoe UI", system-ui, -apple-system, Arial, sans-serif;
            background: var(--bg);
            color: var(--text);
            line-height: 1.5;
        }

        a { color: inherit; text-decoration: none; }

        .layout { display: flex; min-height: 100vh; }

        /* ---------- Sidebar ---------- */
        .sidebar {
            width: 250px;
            flex-shrink: 0;
            background: var(--navy);
            color: #fff;
            padding: 24px 16px;
            display: flex;
            flex-direction: column;
            position: sticky;
            top: 0;
            height: 100vh;
            overflow-y: auto;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 0 10px 22px;
            border-bottom: 1px solid rgba(255,255,255,.12);
        }

        .brand-logo {
            width: 40px; height: 40px;
            display: grid; place-items: center;
            border-radius: 11px;
            background: var(--accent);
            font-weight: 700; font-size: 18px;
        }

        .brand strong { display: block; font-size: 18px; letter-spacing: .5px; }
        .brand span   { display: block; font-size: 11px; color: #9fb4d0; }

        .nav-label {
            margin: 24px 10px 10px;
            font-size: 11px;
            letter-spacing: 1.2px;
            text-transform: uppercase;
            color: #8aa2c2;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 11px 12px;
            margin-bottom: 4px;
            border-radius: 9px;
            font-size: 14px;
            color: #d9e4f3;
            transition: background .15s;
        }

        .nav-link svg { width: 18px; height: 18px; flex-shrink: 0; stroke: currentColor; fill: none; stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round; }

        a.nav-link:hover { background: var(--navy-hover); color: #fff; }
        .nav-link.active { background: var(--navy-hover); color: #fff; box-shadow: inset 3px 0 var(--accent); }
        .nav-link.disabled { opacity: .5; cursor: not-allowed; }
        .nav-link em {
            margin-left: auto;
            font-style: normal;
            font-size: 10px;
            padding: 2px 7px;
            border-radius: 999px;
            background: rgba(255,255,255,.14);
        }

        .sidebar-bottom { margin-top: auto; padding-top: 22px; }

        .logout-button {
            width: 100%;
            display: flex; align-items: center; justify-content: center; gap: 8px;
            padding: 11px;
            border: 1px solid rgba(255,255,255,.25);
            border-radius: 9px;
            background: transparent;
            color: #fff;
            font-size: 14px;
            cursor: pointer;
        }
        .logout-button:hover { background: var(--navy-hover); }
        .logout-button svg { width: 17px; height: 17px; stroke: currentColor; fill: none; stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round; }

        /* ---------- Main ---------- */
        .main { flex: 1; min-width: 0; padding: 28px 32px 40px; }

        .topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 26px;
        }

        .topbar-left { display: flex; align-items: center; gap: 14px; min-width: 0; }
        .topbar h1 { font-size: 24px; line-height: 1.25; }
        .topbar p  { font-size: 14px; color: var(--muted); margin-top: 2px; }

        .menu-toggle {
            display: none;
            width: 40px; height: 40px;
            border: 1px solid var(--line);
            border-radius: 10px;
            background: var(--card);
            cursor: pointer;
            font-size: 20px;
        }

        .profile {
            display: flex; align-items: center; gap: 12px;
            padding: 8px 14px 8px 8px;
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: 12px;
            flex-shrink: 0;
        }
        .avatar {
            width: 36px; height: 36px;
            display: grid; place-items: center;
            border-radius: 10px;
            background: var(--blue-bg); color: var(--blue);
            font-weight: 700;
        }
        .profile strong { display: block; font-size: 14px; line-height: 1.2; }
        .profile span   { display: block; font-size: 12px; color: var(--muted); }

        /* ---------- Komponen bersama ---------- */
        .alert {
            padding: 12px 16px;
            margin-bottom: 20px;
            border-radius: 10px;
            font-size: 14px;
        }
        .alert.success { background: var(--green-bg); color: var(--green); }
        .alert.error   { background: var(--red-bg);   color: var(--red); }

        .card {
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: 14px;
        }

        .card-head {
            display: flex; align-items: center; justify-content: space-between; gap: 12px;
            padding: 18px 22px;
            border-bottom: 1px solid var(--line);
        }
        .card-head h2 { font-size: 16px; }
        .card-head p  { font-size: 13px; color: var(--muted); }

        .badge {
            display: inline-block;
            padding: 3px 11px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 600;
            white-space: nowrap;
        }
        .badge.success { background: var(--green-bg); color: var(--green); }
        .badge.pending { background: var(--amber-bg); color: var(--amber); }
        .badge.danger  { background: var(--red-bg);   color: var(--red); }
        .badge.muted   { background: #eef2f7; color: #64748b; }

        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th {
            text-align: left;
            padding: 12px 22px;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: .6px;
            color: var(--muted);
            background: #f8fafd;
            white-space: nowrap;
        }
        td { padding: 14px 22px; font-size: 14px; border-top: 1px solid var(--line); }
        td small { display: block; color: var(--muted); font-size: 12px; }
        .empty { padding: 36px 22px; text-align: center; color: var(--muted); font-size: 14px; }

        .badge.info    { background: var(--blue-bg);  color: var(--blue); }

        /* Komponen bersama halaman admin */
        .btn {
            display: inline-flex; align-items: center; justify-content: center; gap: 6px;
            padding: 9px 16px;
            border: 1px solid transparent;
            border-radius: 9px;
            font-size: 14px; font-weight: 600; font-family: inherit;
            cursor: pointer; white-space: nowrap;
        }
        .btn-primary { background: var(--blue); color: #fff; }
        .btn-primary:hover { background: #1e40af; }
        .btn-success { background: var(--green); color: #fff; }
        .btn-success:hover { background: #166534; }
        .btn-light { background: #fff; color: var(--text); border-color: var(--line); }
        .btn-light:hover { background: #f1f5fb; }
        .btn-danger { background: #fff; color: var(--red); border-color: #fecaca; }
        .btn-danger:hover { background: var(--red-bg); }
        .btn-sm { padding: 6px 12px; font-size: 13px; }
        .btn.is-disabled { opacity: .45; pointer-events: none; }

        .toolbar { display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; margin-bottom: 18px; }
        .search { display: flex; gap: 8px; flex: 1; flex-wrap: wrap; max-width: 640px; }
        .search input, .search select {
            padding: 9px 14px; border: 1px solid var(--line); border-radius: 9px;
            font-size: 14px; font-family: inherit; background: #fff;
        }
        .search input { flex: 1; min-width: 160px; }
        .search input:focus, .search select:focus { outline: none; border-color: var(--accent); box-shadow: 0 0 0 3px rgba(59,130,246,.15); }

        .row-actions { display: flex; gap: 8px; justify-content: flex-end; flex-wrap: wrap; }
        .row-actions form { display: inline; }
        td.actions { text-align: right; }

        .pager { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 14px 22px; border-top: 1px solid var(--line); font-size: 13px; color: var(--muted); }
        .pager-buttons { display: flex; gap: 8px; }

        .form-card { max-width: 760px; }
        .form-body { padding: 24px 22px 8px; display: grid; grid-template-columns: 1fr 1fr; gap: 18px 20px; }
        .field.full { grid-column: 1 / -1; }
        .field label { display: block; margin-bottom: 7px; font-size: 14px; font-weight: 600; }
        .field input, .field select, .field textarea {
            width: 100%; padding: 11px 14px; border: 1px solid #cbd5e1; border-radius: 9px;
            font-size: 15px; font-family: inherit; background: #fff;
        }
        .field textarea { resize: vertical; min-height: 90px; }
        .field input:focus, .field select:focus, .field textarea:focus { outline: none; border-color: var(--accent); box-shadow: 0 0 0 3px rgba(59,130,246,.15); }
        .field input[readonly] { background: #f1f5fb; color: var(--muted); }
        .field .invalid { border-color: #f87171; }
        .field .hint  { margin-top: 5px; font-size: 12px; color: var(--muted); }
        .field .error { margin-top: 5px; font-size: 13px; color: var(--red); }
        .form-actions { display: flex; justify-content: flex-end; gap: 10px; padding: 18px 22px 22px; }
        @media (max-width: 640px) { .form-body { grid-template-columns: minmax(0, 1fr); } }

        .detail-list { padding: 8px 22px 18px; }
        .detail-row { display: flex; gap: 16px; padding: 12px 0; border-bottom: 1px solid var(--line); font-size: 14px; }
        .detail-row:last-child { border-bottom: 0; }
        .detail-row dt { width: 150px; flex-shrink: 0; color: var(--muted); }
        .detail-row dd { flex: 1; min-width: 0; word-break: break-word; }

        /* ---------- Responsif ---------- */
        @media (max-width: 900px) {
            .sidebar {
                position: fixed;
                z-index: 50;
                left: 0;
                transform: translateX(-100%);
                transition: transform .2s;
            }
            body.menu-open .sidebar { transform: translateX(0); }
            .menu-toggle { display: grid; place-items: center; }
            .main { padding: 20px 16px 32px; }
            .profile div:not(.avatar) { display: none; }
            .profile { padding: 6px; flex-shrink: 0; }
        }

        /* Ponsel */
        @media (max-width: 520px) {
            .stats { grid-template-columns: repeat(2, minmax(0, 1fr)) !important; gap: 10px !important; }
            .stat { padding: 14px !important; }
            .stat strong { font-size: 26px !important; }
            .stat-icon { display: none !important; }
        }
        @media (max-width: 640px) {
            .main { padding: 16px 12px 28px; }
            .topbar { gap: 8px; margin-bottom: 18px; }
            .topbar-left { gap: 10px; flex: 1; }
            .topbar h1 { font-size: 20px; }
            .topbar p { font-size: 13px; }
            .toolbar { flex-direction: column; align-items: stretch; }
            .search { max-width: none; }
            .search input, .search select { flex: 1 1 140px; min-width: 0; }
            .toolbar > .btn, .toolbar > .row-actions, .toolbar > form:not(.search) { align-self: stretch; }
            .toolbar > .row-actions { justify-content: flex-start; }
            .card-head { flex-wrap: wrap; gap: 10px; padding-left: 16px; padding-right: 16px; }
            th, td { padding: 12px 14px; }
            td small { white-space: normal; }
            .detail-row { flex-direction: column; gap: 2px; }
            .detail-row dt { width: auto; }
            .form-actions { flex-wrap: wrap; }
            .alert { margin-left: 0; margin-right: 0; }
        }
    </style>

    @stack('styles')
</head>
<body>
@php
    $user = auth()->user();

    // Daftar menu per role. Menu otomatis aktif jika route-nya sudah dibuat,
    // jika belum ada tampil redup dengan label "Segera".
    $icons = [
        'home'   => '<path d="M3 11l9-8 9 8"/><path d="M5 10v10h14V10"/>',
        'users'  => '<circle cx="9" cy="8" r="3.2"/><path d="M3 20c0-3.3 2.7-6 6-6s6 2.7 6 6"/><path d="M16 5.5a3 3 0 010 5.5M18 14.5c1.8.8 3 2.6 3 5.5"/>',
        'teach'  => '<path d="M3 8l9-4 9 4-9 4-9-4z"/><path d="M7 10.5V15c0 1.4 2.2 3 5 3s5-1.6 5-3v-4.5"/>',
        'file'   => '<path d="M7 3h7l5 5v13H7z"/><path d="M14 3v5h5"/>',
        'stamp'  => '<path d="M5 20h14"/><path d="M8 16h8l-1-5a3 3 0 10-6 0z"/>',
        'key'    => '<circle cx="8" cy="12" r="3.5"/><path d="M11.5 12H21M17 12v3M20 12v2"/>',
        'upload' => '<path d="M12 16V4M7 9l5-5 5 5"/><path d="M4 20h16"/>',
        'book'   => '<path d="M5 4h11a3 3 0 013 3v13H8a3 3 0 01-3-3z"/><path d="M5 17a3 3 0 013-3h11"/>',
        'class'  => '<path d="M3 20V9l9-5 9 5v11"/><path d="M9 20v-6h6v6"/>',
        'cal'    => '<rect x="4" y="5" width="16" height="15" rx="2"/><path d="M4 10h16M9 3v4M15 3v4"/>',
        'eye'    => '<path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>',
        'box'    => '<path d="M4 8l8-4 8 4v8l-8 4-8-4z"/><path d="M4 8l8 4 8-4M12 12v8"/>',
    ];

    $menus = [
        'admin' => [
            ['Dashboard',            'admin.dashboard',        'admin.dashboard', 'home'],
            ['Data Siswa',           'admin.siswa.index',      'admin.siswa.*',   'users'],
            ['Data Guru',            'admin.guru.index',       ['admin.guru.index', 'admin.guru.create', 'admin.guru.edit', 'admin.guru.riwayat', 'admin.guru.wali'], 'teach'],
            ['Kelas',                'admin.kelas.index',      'admin.kelas.*',   'class'],
            ['Tahun Ajaran',         'admin.tahun-ajaran.index', ['admin.tahun-ajaran.*', 'admin.naik-kelas.*'], 'cal'],
            ['Pantau Guru',          'admin.guru.aktivitas',   'admin.guru.aktivitas', 'eye'],
            ['Dokumen Akademik',     'admin.dokumen.index',    'admin.dokumen.*', 'file'],
            ['Kelengkapan Dokumen',  'admin.kelengkapan.index','admin.kelengkapan.*', 'eye'],
            ['Permohonan Legalisir', 'admin.legalisir.index',  'admin.legalisir.*', 'stamp'],
            ['Peminjaman Rapor',     'admin.peminjaman-rapor.index', 'admin.peminjaman-rapor.*', 'book'],
            ['Pengambilan Ijazah',   'admin.pengambilan-ijazah.index', 'admin.pengambilan-ijazah.*', 'box'],
            ['Manajemen Akun',       'admin.akun.index',       'admin.akun.*',    'key'],
            ['Log Admin',            'admin.aktivitas-admin.index', 'admin.aktivitas-admin.*', 'cal'],
        ],
        'guru' => [
            ['Dashboard',        'guru.dashboard',     'guru.dashboard', 'home'],
            ['Dokumen Saya',     'guru.dokumen.index', 'guru.dokumen.index', 'file'],
            ['Unggah Dokumen',   'guru.dokumen.create','guru.dokumen.create', 'upload'],
        ],
        'siswa' => [
            ['Dashboard',            'siswa.dashboard',      'siswa.dashboard',  'home'],
            ['Dokumen Saya',         'siswa.dokumen.index',  'siswa.dokumen.*',  'file'],
            ['Status Pengajuan',     'siswa.pengajuan',      'siswa.pengajuan',  'eye'],
            ['Ajukan Legalisir',     'siswa.legalisir.create','siswa.legalisir.*','stamp'],
            ['Pinjam Rapor',         'siswa.rapor.create',   'siswa.rapor.*',    'book'],
            ['Ambil Ijazah Asli',    'siswa.ijazah.create',  'siswa.ijazah.*',   'box'],
            ['Profil Saya',          'siswa.profil',         'siswa.profil',     'users'],
        ],
    ];

    $roleLabel = ['admin' => 'Administrator', 'guru' => 'Guru', 'siswa' => 'Siswa'][$user->role] ?? ucfirst($user->role);
    $menu = $menus[$user->role] ?? [];
@endphp

<div class="layout">
    <aside class="sidebar" id="sidebar">
        <div class="brand">
            <div class="brand-logo">M</div>
            <div>
                <strong>MAAKA</strong>
                <span>Manajemen Akademik</span>
            </div>
        </div>

        <div class="nav-label">Menu</div>
        <nav>
            @foreach ($menu as [$label, $routeName, $pattern, $icon])
                @if (Route::has($routeName))
                    <a href="{{ route($routeName) }}" class="nav-link {{ request()->routeIs(...(array) $pattern) ? 'active' : '' }}">
                        <svg viewBox="0 0 24 24">{!! $icons[$icon] !!}</svg>
                        {{ $label }}
                    </a>
                @else
                    <span class="nav-link disabled" title="Fitur ini segera tersedia">
                        <svg viewBox="0 0 24 24">{!! $icons[$icon] !!}</svg>
                        {{ $label }}
                        <em>Segera</em>
                    </span>
                @endif
            @endforeach
        </nav>

        <div class="sidebar-bottom">
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="logout-button">
                    <svg viewBox="0 0 24 24"><path d="M9 4H5v16h4"/><path d="M16 8l4 4-4 4M20 12H9"/></svg>
                    Keluar
                </button>
            </form>
        </div>
    </aside>

    <main class="main">
        <header class="topbar">
            <div class="topbar-left">
                <button type="button" class="menu-toggle" aria-label="Buka menu"
                        onclick="document.body.classList.toggle('menu-open')">☰</button>
                <div>
                    <h1>@yield('heading', 'Dashboard')</h1>
                    <p>@yield('subheading')</p>
                </div>
            </div>

            @if ($user->role === 'siswa')
                @php $belumDibaca = \App\Models\Notifikasi::where('user_id', $user->id)->whereNull('dibaca_pada')->count(); @endphp
                <a href="{{ route('siswa.notifikasi') }}" class="btn btn-light btn-sm" aria-label="Notifikasi" style="margin-left:auto;margin-right:12px">
                    Notifikasi @if ($belumDibaca > 0)<span class="badge danger" style="margin-left:4px">{{ $belumDibaca }}</span>@endif
                </a>
            @endif

            <div class="profile">
                <div class="avatar">{{ strtoupper(mb_substr($user->name, 0, 1)) }}</div>
                <div>
                    <strong>{{ $user->name }}</strong>
                    <span>{{ $roleLabel }}</span>
                </div>
            </div>
        </header>

        @if (session('success'))
            <div class="alert success">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="alert error">{{ session('error') }}</div>
        @endif

        @yield('content')
    </main>
</div>

@stack('scripts')

<script>
    // Tutup menu di mobile saat area konten diketuk
    // (klik tombol hamburger diabaikan, kalau tidak menu langsung tertutup lagi karena tombol ada di dalam .main)
    document.querySelector('.main').addEventListener('click', function (e) {
        if (e.target.closest('.menu-toggle')) { return; }
        document.body.classList.remove('menu-open');
    });

    // Konfirmasi generik: <form data-confirm="Pesan konfirmasi">
    document.querySelectorAll('form[data-confirm]').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            if (!confirm(form.dataset.confirm)) { e.preventDefault(); }
        });
    });
</script>
</body>
</html>