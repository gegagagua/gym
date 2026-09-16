<?php

namespace App\Enums;

/** ანტი-ჩიტის ევრისტიკები (სპეც. 8.1) */
enum Flag: string
{
    case ImpossibleRate = 'FLAG_IMPOSSIBLE_RATE';
    case NoRest = 'FLAG_NO_REST';
    case RoundNumbers = 'FLAG_ROUND_NUMBERS';
    case GpsJump = 'FLAG_GPS_JUMP';
    case ClockSkew = 'FLAG_CLOCK_SKEW';
    case Velocity = 'FLAG_VELOCITY';
}
