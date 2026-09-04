<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Console\Scheduling\Schedule;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'verify.api.key' => \App\Http\Middleware\VerifyApiKey::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // ...
    })
    ->withSchedule(function (Schedule $schedule) {
        // Load schedules from Kernel using the public wrapper
        $kernel = app(\App\Console\Kernel::class);
        $kernel->defineSchedule($schedule);  // ← Use the public method
    })
    ->create();
