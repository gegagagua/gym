<?php

namespace App\Services\Media;

use RuntimeException;

/**
 * ანიმირებული GIF-ის ამწყობი.
 *
 * GD-ს `imagegif()` მხოლოდ ერთკადრიან GIF-ს წერს, ImageMagick და ffmpeg კი
 * ამ პროექტის წინაპირობებში არ არის (README — Docker-ის გარეშე, Homebrew).
 * ამიტომ კადრებს GIF89a-ს დონეზე ვაერთებთ: თითოეული კადრი თავისი
 * ლოკალური პალიტრით შედის, გლობალური პალიტრა პირველი კადრიდან მოდის.
 *
 * ფორმატი: https://www.w3.org/Graphics/GIF/spec-gif89a.txt
 */
final class AnimatedGif
{
    /**
     * @param  list<string>  $frames  ცალკადრიანი GIF-ების ნედლი ბაიტები (imagegif)
     * @param  int  $delayMs  კადრის ხანგრძლივობა მილიწამებში
     * @param  int  $loops  0 = უსასრულოდ
     */
    public static function encode(array $frames, int $delayMs, int $loops = 0): string
    {
        if (count($frames) < 2) {
            throw new RuntimeException('ანიმაციისთვის სულ მცირე ორი კადრია საჭირო.');
        }

        // GIF-ის დაყოვნების ერთეული 1/100 წმ-ია; < 2 ბრაუზერებმა თავისებურად იციან
        $delay = max(2, (int) round($delayMs / 10));

        $first = self::parse($frames[0]);

        $out = 'GIF89a'.$first['screen'].$first['palette'];

        // NETSCAPE2.0 — მარყუჟის გაფართოება
        $out .= "\x21\xFF\x0BNETSCAPE2.0\x03\x01".pack('v', $loops)."\x00";

        foreach ($frames as $raw) {
            $frame = self::parse($raw);

            // packed = 0x04: disposal method 1 („არ გაასუფთავო“), გამჭვირვალობის გარეშე
            $out .= "\x21\xF9\x04\x04".pack('v', $delay)."\x00\x00";
            $out .= self::withLocalPalette($frame);
        }

        return $out.';';
    }

    /**
     * @return array{screen: string, palette: string, paletteBits: int, image: string}
     */
    private static function parse(string $gif): array
    {
        if (strlen($gif) < 14 || substr($gif, 0, 3) !== 'GIF') {
            throw new RuntimeException('კადრი GIF არ არის.');
        }

        $packed = ord($gif[10]);
        $paletteBits = $packed & 0x07;
        $paletteLength = ($packed & 0x80) ? 3 * (1 << ($paletteBits + 1)) : 0;

        $cursor = 13 + $paletteLength;
        $length = strlen($gif);
        $image = null;

        while ($cursor < $length) {
            $marker = ord($gif[$cursor]);

            if ($marker === 0x21) {
                // გაფართოებას (GCE, კომენტარი) თავიდან ვწერთ — აქ გამოვტოვებთ
                $cursor = self::skipSubBlocks($gif, $cursor + 2);

                continue;
            }

            if ($marker === 0x2C) {
                $start = $cursor;
                $descriptor = ord($gif[$cursor + 9]);
                $cursor += 10;

                if ($descriptor & 0x80) {
                    $cursor += 3 * (1 << (($descriptor & 0x07) + 1));
                }

                $cursor++; // LZW minimum code size
                $cursor = self::skipSubBlocks($gif, $cursor);
                $image = substr($gif, $start, $cursor - $start);

                break;
            }

            if ($marker === 0x3B) {
                break;
            }

            throw new RuntimeException(sprintf('GIF-ის უცნობი ბლოკი 0x%02X.', $marker));
        }

        if ($image === null) {
            throw new RuntimeException('კადრში სურათის ბლოკი ვერ მოიძებნა.');
        }

        return [
            'screen' => substr($gif, 6, 7),
            'palette' => substr($gif, 13, $paletteLength),
            'paletteBits' => $paletteBits,
            'image' => $image,
        ];
    }

    /** სურათის ბლოკს გლობალურ პალიტრას ლოკალურად მიაბამს */
    private static function withLocalPalette(array $frame): string
    {
        $image = $frame['image'];

        // უკვე აქვს ლოკალური პალიტრა — ხელს არ ვახლებთ
        if (ord($image[9]) & 0x80) {
            return $image;
        }

        if ($frame['palette'] === '') {
            return $image;
        }

        $descriptor = substr($image, 0, 10);
        $descriptor[9] = chr(ord($descriptor[9]) | 0x80 | $frame['paletteBits']);

        return $descriptor.$frame['palette'].substr($image, 10);
    }

    private static function skipSubBlocks(string $gif, int $cursor): int
    {
        while ($cursor < strlen($gif) && ($size = ord($gif[$cursor])) !== 0) {
            $cursor += $size + 1;
        }

        return $cursor + 1;
    }
}
