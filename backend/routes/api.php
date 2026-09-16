<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ContentController;
use App\Http\Controllers\Api\V1\ExerciseController;
use App\Http\Controllers\Api\V1\LeagueController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\ProgramController;
use App\Http\Controllers\Api\V1\SessionController;
use App\Http\Controllers\Api\V1\ShareController;
use App\Http\Controllers\Api\V1\SpotController;
use App\Http\Controllers\Api\V1\TrainerController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    // ---- საჯარო -------------------------------------------------------------
    Route::get('/health', fn () => ['ok' => true, 'time' => now()->toIso8601String()]);
    Route::get('/config', [ContentController::class, 'config']);
    Route::get('/cities', [ContentController::class, 'cities']);
    Route::get('/attributions', [ContentController::class, 'attributions']);

    // OTP — 3/სთ/ნომერზე (სპეც. 17)
    Route::post('/auth/otp/request', [AuthController::class, 'requestOtp'])->middleware('throttle:otp');
    Route::post('/auth/otp/verify', [AuthController::class, 'verifyOtp'])->middleware('throttle:10,60');
    Route::post('/auth/social', [AuthController::class, 'social'])->middleware('throttle:20,60');
    Route::post('/auth/guest', [AuthController::class, 'guest'])->middleware('throttle:10,60');

    // კონტენტი სტუმრისთვისაც ხელმისაწვდომია — ონბორდინგამდე
    Route::get('/exercises', [ExerciseController::class, 'index']);
    Route::get('/exercises/{exercise}', [ExerciseController::class, 'show']);
    Route::get('/programs', [ProgramController::class, 'index']);
    Route::get('/programs/{program}', [ProgramController::class, 'show']);
    Route::get('/content/manifest', [ContentController::class, 'manifest']);
    Route::get('/spots/nearby', [SpotController::class, 'nearby']);
    Route::get('/spots/{spot}', [SpotController::class, 'show']);
    Route::get('/trainers', [TrainerController::class, 'index']);

    // ---- ავტორიზებული -------------------------------------------------------
    Route::middleware('auth:sanctum')->group(function () {

        Route::post('/auth/refresh', [AuthController::class, 'refresh']);
        Route::post('/auth/upgrade', [AuthController::class, 'upgradeGuest'])->middleware('throttle:10,60');
        Route::delete('/auth/logout', [AuthController::class, 'logout']);
        Route::delete('/account', [AuthController::class, 'destroyAccount']);

        Route::get('/me', [MeController::class, 'show']);
        Route::patch('/me', [MeController::class, 'update']);
        Route::patch('/me/profile', [MeController::class, 'updateProfile']);
        Route::post('/me/level-test', [MeController::class, 'submitLevelTest']);
        Route::get('/me/stats', [MeController::class, 'stats']);
        Route::get('/me/records', [MeController::class, 'records']);

        Route::post('/programs/{program}/enroll', [ProgramController::class, 'enroll']);
        Route::get('/me/program', [ProgramController::class, 'current']);
        Route::post('/me/program/advance', [ProgramController::class, 'advance']);

        // ოფლაინ სინქის ბირთვი — 60/სთ (სპეც. 17)
        Route::post('/sessions/sync', [SessionController::class, 'store'])->middleware('throttle:sync');
        Route::get('/sessions', [SessionController::class, 'index']);

        Route::get('/league/current', [LeagueController::class, 'current']);
        Route::get('/leaderboard/friends', [LeagueController::class, 'friends']);
        Route::get('/leaderboard/spot/{spot}', [LeagueController::class, 'spot']);
        Route::get('/leaderboard/city/{cityId}', [LeagueController::class, 'city']);
        Route::get('/leaderboard/records/{exerciseId}', [LeagueController::class, 'records']);

        // UGC — 10/დღეში (სპეც. 17)
        Route::post('/spots', [SpotController::class, 'store'])->middleware('throttle:ugc');
        Route::post('/spots/{spot}/checkin', [SpotController::class, 'checkIn'])->middleware('throttle:30,60');
        Route::post('/spots/{spot}/rating', [SpotController::class, 'rate']);
        Route::get('/me/checkin', [SpotController::class, 'myCheckin']);

        Route::post('/share/card', [ShareController::class, 'store'])->middleware('throttle:20,60');
        Route::get('/share/card/{card}', [ShareController::class, 'show']);
    });
});
