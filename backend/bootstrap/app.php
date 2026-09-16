<?php

use App\Http\Middleware\SetLocale;
use App\Http\Middleware\TouchLastActive;
use App\Jobs\PurgeDeletedAccounts;
use App\Jobs\RecomputeLeagueStandings;
use App\Jobs\RotateLeagues;
use App\Jobs\SendStreakRiskPush;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Schedule;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->api(prepend: [
            SetLocale::class,
            TouchLastActive::class,
        ]);
    })
    ->withSchedule(function (Schedule $schedule) {
        // ლიგის როტაცია — ორშაბათი 00:05 Asia/Tbilisi (სპეც. 7.1)
        $schedule->job(RotateLeagues::class)->weeklyOn(1, '00:05')->timezone('Asia/Tbilisi');

        // Redis-ის ინკრემენტების fallback — ledger-იდან სრული გადათვლა
        $schedule->job(RecomputeLeagueStandings::class)->everyFiveMinutes();

        // streak-ის რისკის push 20:00 ლოკალურად; job თავად ფილტრავს ზონებს
        $schedule->job(SendStreakRiskPush::class)->hourly();

        // 30-დღიანი grace გასული ანგარიშების hard delete
        $schedule->job(PurgeDeletedAccounts::class)->dailyAt('03:00')->timezone('Asia/Tbilisi');
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
