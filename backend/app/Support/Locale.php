<?php

namespace App\Support;

class Locale
{
    public const SUPPORTED = ['ka', 'ru', 'en'];

    public const FALLBACK = 'en';

    /** Accept-Language → ჩვენი სამიდან ერთი */
    public static function fromHeader(?string $header): string
    {
        foreach (explode(',', (string) $header) as $part) {
            $code = strtolower(trim(explode(';', explode('-', $part)[0])[0]));
            if (in_array($code, self::SUPPORTED, true)) {
                return $code;
            }
        }

        return self::FALLBACK;
    }

    /** JSON-ში შენახული { ka, ru, en } → ერთი სტრიქონი fallback-ით */
    public static function pick(?array $bag, string $locale): ?string
    {
        if (! $bag) {
            return null;
        }

        return $bag[$locale] ?? $bag[self::FALLBACK] ?? (reset($bag) ?: null);
    }
}
