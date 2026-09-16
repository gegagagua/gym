<?php

namespace App\Enums;

/**
 * ვერიფიკაციის დონეები (სპეც. 8).
 * განსაზღვრავს რაში ითვლება სესია — პირად სტატისტიკაში, ლიგაში თუ PR ბორდზე.
 */
enum VerificationTier: int
{
    /** ხელით შეყვანილი — აპი სესიის დროს არ იყო გახსნილი */
    case Manual = 0;
    /** სესია აპში, ტაიმერით, რეალისტური დროის შტამპებით */
    case InApp = 1;
    /** T1 + GPS check-in ვერიფიცირებულ მოედანზე */
    case Checkin = 2;
    /** ვიდეო-PR, მოდერირებული */
    case Video = 3;

    public function countsForLeague(): bool
    {
        return $this !== self::Manual;
    }

    public function countsForRecordBoard(): bool
    {
        return $this === self::Video;
    }
}
