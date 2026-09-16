<?php

namespace App\Services;

use App\Models\OtpCode;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

/**
 * ტელეფონის OTP. SMS-ის რეალური გამგზავნი v1-ში ლოკალური
 * პროვაიდერით ჩაირთვება — აქ ინტერფეისი და ვალიდაციაა.
 *
 * Rate limit (3/სთ/ნომერზე) მარშრუტის დონეზეა (სპეც. 17).
 */
class OtpService
{
    public function issue(string $phone, ?string $ip = null): array
    {
        $cfg = config('kalisteni.auth');
        $code = str_pad((string) random_int(0, 10 ** $cfg['otp_length'] - 1), $cfg['otp_length'], '0', STR_PAD_LEFT);

        OtpCode::where('phone', $phone)->whereNull('consumed_at')->delete();

        OtpCode::create([
            'phone' => $phone,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes($cfg['otp_ttl_minutes']),
            'ip' => $ip,
        ]);

        // SMS-ის რეალური გაგზავნა მხოლოდ production/staging-ზე ხდება.
        // local/testing გარემოში კოდი პასუხშივე ბრუნდება — სხვა გარემოში
        // ეს გასაღების გაცემა იქნებოდა, ამიტომ პირობა მკაცრია.
        $exposed = app()->environment('local', 'testing');

        if ($exposed) {
            Log::info("OTP for {$phone}: {$code}");
        }

        return [
            'expires_in' => $cfg['otp_ttl_minutes'] * 60,
            'debug_code' => $exposed ? $code : null,
        ];
    }

    public function verify(string $phone, string $code): bool
    {
        $otp = OtpCode::where('phone', $phone)
            ->whereNull('consumed_at')
            ->where('expires_at', '>', now())
            ->latest('id')
            ->first();

        if (! $otp) {
            return false;
        }

        if ($otp->attempts >= config('kalisteni.auth.otp_max_attempts')) {
            return false;
        }

        $otp->increment('attempts');

        if (! Hash::check($code, $otp->code_hash)) {
            return false;
        }

        $otp->update(['consumed_at' => now()]);

        return true;
    }
}
