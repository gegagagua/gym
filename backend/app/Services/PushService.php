<?php

namespace App\Services;

use App\Models\PushLog;
use App\Models\User;
use Illuminate\Support\Facades\Http;

/**
 * Expo Push. ლიმიტი queue-ს დონეზე: მაქს. 2 push დღეში.
 * აგრესიული ნოტიფიკაციები v1-ის ყველაზე გავრცელებული მიზეზია
 * აპის წაშლისა (სპეც. 15).
 */
class PushService
{
    private const ENDPOINT = 'https://exp.host/--/api/v2/push/send';

    public function send(User $user, string $type, array $data = []): bool
    {
        if (! $this->withinDailyLimit($user)) {
            return false;
        }

        $tokens = $user->devices()->whereNotNull('push_token')->pluck('push_token');
        if ($tokens->isEmpty()) {
            return false;
        }

        $locale = $user->locale ?: 'ka';
        [$title, $body] = $this->copy($type, $locale, $data);

        $messages = $tokens->map(fn ($token) => [
            'to' => $token,
            'title' => $title,
            'body' => $body,
            'sound' => 'default',
            'data' => ['type' => $type] + $data,
        ])->all();

        try {
            Http::timeout(10)->post(self::ENDPOINT, $messages);
        } catch (\Throwable $e) {
            report($e);

            return false;
        }

        PushLog::create([
            'user_id' => $user->id,
            'type' => $type,
            'sent_on' => now($user->timezone ?: 'Asia/Tbilisi')->toDateString(),
            'sent_at' => now(),
        ]);

        return true;
    }

    private function withinDailyLimit(User $user): bool
    {
        $today = now($user->timezone ?: 'Asia/Tbilisi')->toDateString();

        return PushLog::where('user_id', $user->id)->where('sent_on', $today)->count()
            < config('kalisteni.push.daily_limit');
    }

    /** ტექსტები devices.locale-ის მიხედვით (სპეც. 14) */
    private function copy(string $type, string $locale, array $data): array
    {
        $copy = [
            'streak_risk' => [
                'ka' => ['🔥 :days დღე რისკზეა', 'დღეს ჯერ არაფერი დაგილოგავს. 10 წუთი საკმარისია.'],
                'ru' => ['🔥 :days дней под угрозой', 'Сегодня пока пусто. Хватит и 10 минут.'],
                'en' => ['🔥 :days-day streak at risk', 'Nothing logged today. Ten minutes is enough.'],
            ],
            'league_result' => [
                'ka' => ['ლიგის კვირა დასრულდა', 'ნახე შენი საბოლოო ადგილი.'],
                'ru' => ['Неделя лиги завершена', 'Посмотри своё итоговое место.'],
                'en' => ['League week is over', 'See where you finished.'],
            ],
            'league_last_hours' => [
                'ka' => ['ბოლო საათები', 'შენი ადგილი ჯერ არ არის დაცული.'],
                'ru' => ['Последние часы', 'Твоё место ещё не закреплено.'],
                'en' => ['Final hours', 'Your spot is not locked in yet.'],
            ],
            'spot_nearby' => [
                'ka' => ['ახალი მოედანი ახლოს', 'შენთან 1 კმ-ში ახალი მოედანი დადასტურდა.'],
                'ru' => ['Новая площадка рядом', 'В километре от тебя подтвердили площадку.'],
                'en' => ['New spot nearby', 'A spot was verified within a kilometre of you.'],
            ],
        ];

        $set = $copy[$type][$locale] ?? $copy[$type]['en'] ?? ['Kalisteni', ''];

        $replace = fn (string $s) => str_replace(
            array_map(fn ($k) => ':'.$k, array_keys($data)),
            array_values($data),
            $s,
        );

        return [$replace($set[0]), $replace($set[1])];
    }
}
