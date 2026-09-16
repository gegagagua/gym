<?php

namespace App\Jobs;

use App\Models\ShareCard;
use App\Models\User;
use App\Models\WorkoutSession;
use App\Services\StatsService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\Geometry\Factories\RectangleFactory;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\ImageInterface;
use Intervention\Image\Typography\FontFactory;

/**
 * 1080×1920 ბარათი Instagram Stories-ისთვის.
 * ფონი „ბეტონია“, აქცენტი ლაიმისფერი — იგივე ვიზუალური ენა, რაც აპში.
 *
 * ტიპოგრაფია ბრენდის ფონტებია (`resources/fonts`, SIL OFL): GD-ს TTF-ის
 * გარეშე მხოლოდ bitmap ფონტი აქვს და 1080px-იან ტილოზე ის იკითხება არა.
 */
class RenderShareCard implements ShouldQueue
{
    use Queueable;

    private const WIDTH = 1080;

    private const HEIGHT = 1920;

    private const BG = '#06070A';

    private const LIME = '#D7FF3E';

    private const CHALK = '#F4F6FA';

    private const ASH = '#6B7280';

    public function __construct(public int $cardId) {}

    public function handle(StatsService $stats): void
    {
        $card = ShareCard::find($this->cardId);
        if (! $card) {
            return;
        }

        try {
            $user = $card->user_id ? User::find($card->user_id) : null;
            $summary = $user ? $stats->summary($user, 'week') : [];

            [$headline, $caption] = $this->copy($card, $summary);

            $image = (new ImageManager(new Driver))->decodeBinary($this->backdrop());

            // ლაიმისფერი აქცენტის ზოლი — ბრენდის ერთადერთი მუდმივი ელემენტი
            $this->bar($image, 80, 1180, 180, 10, self::LIME);

            $image->text(mb_strtoupper($caption), 80, 1280, function (FontFactory $font) {
                $font->filename($this->font('NotoSansGeorgian_700Bold.ttf'));
                $font->size(38);
                $font->color(self::ASH);
                $font->align('left');
            });

            $image->text($headline, 80, 1420, function (FontFactory $font) {
                $font->filename($this->font('Unbounded_900Black.ttf'));
                $font->size(120);
                $font->color(self::CHALK);
                $font->align('left');
            });

            $image->text('KALISTENI', 80, 1720, function (FontFactory $font) {
                $font->filename($this->font('Unbounded_900Black.ttf'));
                $font->size(42);
                $font->color(self::LIME);
                $font->align('left');
            });

            // საჯარო დისკი და არა default — `local`-ს URL არ აქვს და
            // ბარათი „ready" სტატუსით მიუწვდომელ მისამართზე დარჩებოდა
            $disk = Storage::disk(config('kalisteni.media.disk'));
            $path = "share/{$card->id}.jpg";
            $disk->put($path, (string) $image->encode(new JpegEncoder(quality: 88)), 'public');

            $card->update(['url' => $disk->url($path), 'status' => 'ready']);
        } catch (\Throwable $e) {
            report($e);
            $card->update(['status' => 'failed']);
        }
    }

    /** @return array{0: string, 1: string} */
    private function copy(ShareCard $card, array $summary): array
    {
        return match ($card->type) {
            'streak' => [($summary['streak_days'] ?? 0).' DAYS', __('Streak')],
            'league' => [(string) ($summary['xp'] ?? 0).' XP', __('League week')],
            'record' => [(string) ($summary['xp'] ?? 0).' XP', __('New record')],
            default => [
                $card->session_id
                    ? ((int) WorkoutSession::find($card->session_id)?->total_xp).' XP'
                    : ($summary['xp'] ?? 0).' XP',
                __('Workout complete'),
            ],
        };
    }

    /**
     * ფონი: ლაიმის რბილი ნათება ბეტონის შავზე + მარცვალი.
     *
     * GD-ს რადიალური გრადიენტი არ აქვს, გამჭვირვალე სწორკუთხედების დაწყობა
     * კი ხილულ ზოლებს ტოვებს. ამიტომ ნათება პატარა ტილოზე პიქსელ-პიქსელ
     * ითვლება და შემდეგ ბილინეარულად იჭიმება — გადასვლა სუფთა გამოდის.
     */
    private function backdrop(): string
    {
        $w = 108;
        $h = 192;
        $small = imagecreatetruecolor($w, $h);

        [$br, $bg, $bb] = $this->rgb(self::BG);
        [$lr, $lg, $lb] = $this->rgb(self::LIME);

        // სინათლის წყარო ზედა მარცხენა მესამედში — ტექსტი ქვემოთ სუფთა რჩება
        $fx = 0.34 * $w;
        $fy = 0.22 * $h;
        $reach = 0.74 * $h;

        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                $dx = ($x - $fx) * ($h / $w) * 0.55;
                $dy = $y - $fy;
                $t = max(0.0, 1 - sqrt($dx * $dx + $dy * $dy) / $reach);
                $k = $t ** 3.1 * 0.26;

                imagesetpixel($small, $x, $y, imagecolorallocate(
                    $small,
                    (int) round($br + ($lr - $br) * $k),
                    (int) round($bg + ($lg - $bg) * $k),
                    (int) round($bb + ($lb - $bb) * $k),
                ));
            }
        }

        $canvas = imagecreatetruecolor(self::WIDTH, self::HEIGHT);
        imagecopyresampled($canvas, $small, 0, 0, 0, 0, self::WIDTH, self::HEIGHT, $w, $h);
        imagedestroy($small);

        $this->paintGrain($canvas);
        $this->paintFooterScrim($canvas);

        ob_start();
        imagepng($canvas, null, 6);
        imagedestroy($canvas);

        return (string) ob_get_clean();
    }

    /** ბეტონის მარცვალი — იგივე ტექსტურა, რაც აპში; ზოლებსაც ფარავს */
    private function paintGrain($canvas): void
    {
        $chalk = imagecolorallocatealpha($canvas, 244, 246, 250, 112);

        // დეტერმინისტული LCG — ერთი და იგივე ბარათი ყოველთვის ერთნაირია
        $seed = 991;
        $next = function () use (&$seed) {
            $seed = ($seed * 1103515245 + 12345) % 2147483648;

            return $seed / 2147483648;
        };

        for ($i = 0; $i < 2600; $i++) {
            imagefilledellipse(
                $canvas,
                (int) ($next() * self::WIDTH),
                (int) ($next() * self::HEIGHT),
                2,
                2,
                $chalk,
            );
        }
    }

    /** ქვედა მესამედის დამძიმება — ტიპოგრაფიას სუფთა ფონი სჭირდება */
    private function paintFooterScrim($canvas): void
    {
        [$br, $bg, $bb] = $this->rgb(self::BG);
        $from = 820;

        for ($y = $from; $y < self::HEIGHT; $y++) {
            $t = min(1.0, ($y - $from) / 380);
            $alpha = (int) round(127 - $t * 127);
            $color = imagecolorallocatealpha($canvas, $br, $bg, $bb, $alpha);
            imageline($canvas, 0, $y, self::WIDTH, $y, $color);
        }
    }

    /** @return array{0: int, 1: int, 2: int} */
    private function rgb(string $hex): array
    {
        $hex = ltrim($hex, '#');

        return [
            (int) hexdec(substr($hex, 0, 2)),
            (int) hexdec(substr($hex, 2, 2)),
            (int) hexdec(substr($hex, 4, 2)),
        ];
    }

    private function bar(ImageInterface $image, int $x, int $y, int $width, int $height, string $color): void
    {
        $image->drawRectangle(function (RectangleFactory $rectangle) use ($x, $y, $width, $height, $color) {
            $rectangle->at($x, $y);
            $rectangle->size($width, $height);
            $rectangle->background($color);
        });
    }

    private function font(string $file): string
    {
        return resource_path("fonts/{$file}");
    }
}
