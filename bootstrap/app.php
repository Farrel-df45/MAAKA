<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
    'role' => \App\Http\Middleware\RoleMiddleware::class,
    'catat.admin' => \App\Http\Middleware\CatatAktivitasAdmin::class,
]);
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Sesi/token kedaluwarsa (419): kembalikan ke halaman login, bukan halaman buntu
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\HttpException $e, Request $request) {
            if ($e->getStatusCode() !== 419 || $request->expectsJson()) {
                return null;
            }

            $tujuan = str_starts_with($request->path(), 'siswa') || $request->path() === 'login'
                ? 'siswa.login'
                : 'login';

            return redirect()->route($tujuan)->withErrors([
                'login' => 'Sesi telah berakhir. Silakan login kembali.',
            ]);
        });

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
