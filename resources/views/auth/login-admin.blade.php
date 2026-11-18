
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Admin & Guru | MAAKA</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            font-family: Arial, sans-serif;
            background-image:
                linear-gradient(
                    rgba(15, 23, 42, 0.35),
                    rgba(15, 23, 42, 0.35)
                ),
                url('/images/login-background.jpg');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            background-attachment: fixed;
            color: #172033;
        }

        .login-card {
            width: 100%;
            max-width: 420px;
            padding: 36px;
            background: rgba(255, 255, 255, 0.96);
            border: 1px solid rgba(255, 255, 255, 0.7);
            border-radius: 18px;
            box-shadow: 0 12px 40px rgba(15, 23, 42, 0.2);
            backdrop-filter: blur(8px);
        }

        .brand {
            margin-bottom: 28px;
            text-align: center;
        }

        .brand-icon {
            width: 58px;
            height: 58px;
            display: grid;
            place-items: center;
            margin: 0 auto 16px;
            border-radius: 16px;
            background: #1d4ed8;
            color: white;
            font-size: 25px;
            font-weight: bold;
        }

        h1 {
            margin: 0 0 9px;
            font-size: 25px;
        }

        .subtitle {
            margin: 0;
            color: #64748b;
            font-size: 14px;
            line-height: 1.6;
        }

        .field {
            margin-bottom: 19px;
        }

        .field label {
            display: block;
            margin-bottom: 8px;
            font-size: 14px;
            font-weight: 600;
        }

        .field input {
            width: 100%;
            padding: 13px 14px;
            border: 1px solid #cbd5e1;
            border-radius: 9px;
            font-size: 15px;
            outline: none;
            background: #fff;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .field input:focus {
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12);
        }

        .remember {
            display: flex;
            align-items: center;
            gap: 9px;
            margin: 2px 0 22px;
            color: #475569;
            font-size: 14px;
            cursor: pointer;
        }

        .remember input {
            width: 16px;
            height: 16px;
            accent-color: #1d4ed8;
        }

        button {
            width: 100%;
            padding: 14px;
            border: none;
            border-radius: 9px;
            background: #1d4ed8;
            color: white;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            transition: background 0.2s, transform 0.2s;
        }

        button:hover {
            background: #1e40af;
            transform: translateY(-1px);
        }

        .error {
            margin: 0 0 18px;
            padding: 12px;
            border-radius: 8px;
            background: #fef2f2;
            color: #b91c1c;
            font-size: 14px;
        }

        .footer {
            margin-top: 24px;
            text-align: center;
            font-size: 13px;
            color: #64748b;
        }

        .footer a {
            color: #1d4ed8;
            font-weight: 600;
            text-decoration: none;
        }

        .footer a:hover {
            text-decoration: underline;
        }

        @media (max-width: 480px) {
            body {
                padding: 16px;
                background-attachment: scroll;
            }

            .login-card {
                padding: 28px 22px;
            }
        }
    </style>
</head>

<body>
    <main class="login-card">
        <header class="brand">
            <div class="brand-icon">M</div>

            <h1>MAAKA</h1>

            <p class="subtitle">
                Manajemen Akademik<br>
                Login khusus Admin dan Guru
            </p>
        </header>

        @if ($errors->any())
            <div class="error" role="alert">
                {{ $errors->first('login') ?? $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('staff.login.process') }}">
            @csrf

            <div class="field">
                <label for="login">Email atau NIP</label>

                <input
                    id="login"
                    type="text"
                    name="login"
                    value="{{ old('login') }}"
                    placeholder="Masukkan email atau NIP"
                    autocomplete="username"
                    required
                    autofocus
                >
            </div>

            <div class="field">
                <label for="password">Kata sandi</label>

                <input
                    id="password"
                    type="password"
                    name="password"
                    placeholder="Masukkan kata sandi"
                    autocomplete="current-password"
                    required
                >
            </div>

            <label class="remember">
                <input type="checkbox" name="remember" value="1">
                Ingat saya
            </label>

            <button type="submit">Masuk</button>
        </form>

        <footer class="footer">
            Login sebagai siswa?
            <a href="{{ route('siswa.login') }}">Masuk di sini</a>
        </footer>
    </main>
</body>
</html>
