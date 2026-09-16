<?php

return [
    /*
    |---------------------------------------------------------------------------
    | ჯანმრთელობის და ანტი-ფარმინგის ლიმიტები (სპეც. 6.4)
    |---------------------------------------------------------------------------
    | ეს პროდუქტის მოთხოვნაა, არა ოპტიმიზაცია. ლიდერბორდი + დღიური ქულები
    | + 16–25 წლის აუდიტორია = გადავარჯიშების რეალური რისკი.
    */
    'xp' => [
        'daily_cap' => (int) env('KALISTENI_DAILY_XP_CAP', 1800),
        // T0 (ხელით შეყვანილი) სესიები ლიგაში ლიმიტირებულია
        'daily_cap_t0_league' => (int) env('KALISTENI_T0_DAILY_XP_CAP', 300),
        'diminishing_reps' => (int) env('KALISTENI_DIMINISHING_REPS', 100),
        'diminishing_mod' => 0.3,
        'hold_seconds_per_unit' => 5,     // hold: base = k × (seconds / 5)
        'tempo_slow_mod' => 1.2,
        'checkin_mod' => 1.15,
        'weight_mod_max' => 2.0,
        'streak_tiers' => [30 => 1.25, 14 => 1.15, 7 => 1.10, 3 => 1.05],
        // v2: 1000 XP = 5 ₾, ვადა 12 თვე (სპეც. 19.2)
        'expiry_months' => 12,
    ],

    'session' => [
        'max_minutes' => (int) env('KALISTENI_MAX_SESSION_MINUTES', 150),
        'min_minutes' => 3,
        'min_exercises' => 2,
        'sync_batch_max' => 20,
    ],

    'anticheat' => [
        // < 0.8 წმ/გამეორება ფიზიკურად შეუძლებელია
        'min_seconds_per_rep' => 0.8,
        'min_rest_seconds' => 5,
        'no_rest_streak' => 3,
        'clock_skew_minutes' => 5,
        'gps_jump_minutes' => 10,
        'gps_jump_km' => 3,
        'flags_to_flag_session' => 2,
        'weekly_flags_to_demote' => 5,
    ],

    'spots' => [
        'checkin_radius_m' => (int) env('KALISTENI_CHECKIN_RADIUS_M', 100),
        'checkin_ttl_hours' => (int) env('KALISTENI_CHECKIN_TTL_HOURS', 3),
        'nearby_default_radius_m' => 1000,
        'nearby_limit' => 50,
        'duplicate_radius_m' => 50,
        'spot_added_xp' => 100,
        'max_photos' => 6,
    ],

    'league' => [
        // ლიგები არ ირთვება სანამ 200 კვირეული აქტიური არ გვყავს —
        // 12-კაციანი ლიგა უფრო მეტ ზიანს აყენებს ვიდრე მისი არარსებობა (სპეც. 7.1)
        'min_weekly_active' => (int) env('KALISTENI_LEAGUE_MIN_WAU', 200),
        'size' => (int) env('KALISTENI_LEAGUE_SIZE', 30),
        'promote' => 7,
        'demote' => 7,
        'divisions' => 5,
        'timezone' => 'Asia/Tbilisi',
        'active_window_days' => 7,
    ],

    'push' => [
        'daily_limit' => (int) env('KALISTENI_PUSH_DAILY_LIMIT', 2),
        'streak_risk_hour' => 20,
    ],

    'media' => [
        /*
        | სავარჯიშოს ლუპები საჯარო დისკზე ზის — არა FILESYSTEM_DISK-ზე.
        | R2 კრედენშელების გარეშე default დისკი ვერ ჩაიწერს, ლოკალური
        | გაშვება კი მედიის გარეშე დარჩება.
        */
        'disk' => env('KALISTENI_MEDIA_DISK', 'public'),
    ],

    'auth' => [
        'otp_ttl_minutes' => 5,
        'otp_max_attempts' => 5,
        'otp_length' => 6,
        'delete_grace_days' => 30,
        'min_social_age' => 16,
    ],
];
