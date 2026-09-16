<?php

namespace App\Services\Media;

use App\Models\Exercise;
use App\Models\ExerciseMedia;
use GdImage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * სავარჯიშოს ლუპების აწყობა ღია წყაროებიდან (სპეც. 13.1).
 *
 * ორი წყარო გვაქვს და ორივე ხელით არის დამაგრებული
 * `database/data/exercise_media_sources.json`-ში — ავტომატური ძებნა
 * არასწორ მოძრაობას აბრუნებს და ეს ინსტრუქციული კონტენტია, არა დეკორაცია:
 *
 *   free-exercise-db  — Unlicense (public domain), 2 კადრი მოძრაობაზე
 *   Wikimedia Commons — CC BY-SA, ხელით შერჩეული ფაილები
 *
 * ლიცენზია და ავტორი `exercise_media`-ში იწერება; `/v1/attributions`
 * ატრიბუციის ეკრანს სწორედ იქიდან აგებს.
 */
class ExerciseMediaFetcher
{
    public const WIDTH = 480;

    public const HEIGHT = 360;

    private const USER_AGENT = 'Kalisteni/1.0 (https://kalisteni.ge; exercise loop fetcher)';

    private ?array $sources = null;

    public function __construct(private readonly ?string $disk = null) {}

    public function sources(): array
    {
        return $this->sources ??= json_decode(
            file_get_contents(database_path('data/exercise_media_sources.json')),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
    }

    /** სავარჯიშოები, რომლებსაც ღია წყარო არ აქვთ და საკუთარ გადაღებას ელოდებიან */
    public function pending(): array
    {
        return $this->sources()['pending_own_footage'] ?? [];
    }

    public function hasSource(string $slug): bool
    {
        return isset($this->sources()['exercises'][$slug]);
    }

    /**
     * ერთი სავარჯიშოს კადრების ჩამოტვირთვა, ლუპის აწყობა და დარეგისტრირება.
     *
     * @return array{status: 'synced'|'skipped'|'pending', frames?: int, bytes?: int, reason?: string}
     */
    public function sync(Exercise $exercise, bool $force = false): array
    {
        $entry = $this->sources()['exercises'][$exercise->slug] ?? null;

        if (! $entry) {
            return ['status' => 'pending', 'reason' => $this->pending()[$exercise->slug] ?? 'no open-source frames mapped'];
        }

        $disk = Storage::disk($this->disk ?? config('kalisteni.media.disk'));
        $loopPath = "exercises/{$exercise->slug}/loop.gif";
        $posterPath = "exercises/{$exercise->slug}/poster.jpg";

        if (! $force && $disk->exists($posterPath) && $exercise->media()->exists()) {
            return ['status' => 'skipped', 'reason' => 'already fetched'];
        }

        $provider = $this->sources()['providers'][$entry['provider']]
            ?? throw new RuntimeException("უცნობი პროვაიდერი: {$entry['provider']}");

        $frames = match ($entry['provider']) {
            'free-exercise-db' => $this->resolveGithubFrames($entry, $provider),
            'wikimedia-commons' => $this->resolveCommonsFrames($entry, $provider),
            default => throw new RuntimeException("უცნობი პროვაიდერი: {$entry['provider']}"),
        };

        $images = array_map(fn (array $frame) => $this->normalize($this->download($frame['url'])), $frames);

        $poster = $this->toJpeg($images[0]);
        $disk->put($posterPath, $poster, 'public');

        $loopBytes = null;

        if (count($images) > 1) {
            $gif = AnimatedGif::encode(
                array_map(fn (GdImage $image) => $this->toGif($image), $images),
                (int) ($entry['frame_ms'] ?? $provider['frame_ms'] ?? 700),
            );

            $disk->put($loopPath, $gif, 'public');
            $loopBytes = strlen($gif);
        }

        foreach ($images as $image) {
            imagedestroy($image);
        }

        $attribution = $this->attribution($frames, $provider, $entry);

        $this->register($exercise, 'thumbnail', $posterPath, strlen($poster), null, $attribution, $disk);

        if ($loopBytes !== null) {
            $this->register(
                $exercise,
                'loop',
                $loopPath,
                $loopBytes,
                count($images) * (int) ($entry['frame_ms'] ?? $provider['frame_ms'] ?? 700),
                $attribution,
                $disk,
            );
        } else {
            // ერთკადრიან წყაროზე ძველი ლუპი აღარ უნდა დარჩეს
            $exercise->media()->where('type', 'loop')->delete();
            $disk->delete($loopPath);
        }

        return ['status' => 'synced', 'frames' => count($images), 'bytes' => ($loopBytes ?? 0) + strlen($poster)];
    }

    /** უკვე ჩამოტვირთული ფაილების დარეგისტრირება — ქსელის გარეშე (სიდერისთვის) */
    public function registerExisting(Exercise $exercise): bool
    {
        $entry = $this->sources()['exercises'][$exercise->slug] ?? null;

        if (! $entry) {
            return false;
        }

        $disk = Storage::disk($this->disk ?? config('kalisteni.media.disk'));
        $posterPath = "exercises/{$exercise->slug}/poster.jpg";

        if (! $disk->exists($posterPath)) {
            return false;
        }

        $provider = $this->sources()['providers'][$entry['provider']];
        $attribution = [
            'license' => $entry['license'] ?? $provider['license'],
            'attribution_text' => $entry['attribution_text'] ?? $provider['attribution_text'] ?? $entry['provider'],
            'source_url' => $entry['source_url'] ?? $provider['source_url'] ?? null,
        ];

        $this->register($exercise, 'thumbnail', $posterPath, $disk->size($posterPath), null, $attribution, $disk);

        $loopPath = "exercises/{$exercise->slug}/loop.gif";

        if ($disk->exists($loopPath)) {
            $frames = count($entry['files']);
            $this->register(
                $exercise,
                'loop',
                $loopPath,
                $disk->size($loopPath),
                $frames * (int) ($entry['frame_ms'] ?? $provider['frame_ms'] ?? 700),
                $attribution,
                $disk,
            );
        }

        return true;
    }

    // ---- წყაროები ---------------------------------------------------------

    private function resolveGithubFrames(array $entry, array $provider): array
    {
        $folder = Str::before($entry['files'][0], '/');

        return array_map(fn (string $file) => [
            'url' => $provider['base_url'].str_replace(' ', '%20', $file),
            'license' => $provider['license'],
            'attribution_text' => $provider['attribution_text'],
            'source_url' => rtrim($provider['source_url'], '/')."/tree/main/exercises/{$folder}",
        ], $entry['files']);
    }

    /**
     * Commons-ის ფაილები სათაურით მოგვყავს — extmetadata გვაძლევს ავტორსა
     * და ლიცენზიას, რომლის ჩვენებაც CC BY-SA-ზე სავალდებულოა.
     */
    private function resolveCommonsFrames(array $entry, array $provider): array
    {
        $titles = collect($entry['files'])->map(fn (string $file) => "File:{$file}")->implode('|');

        $response = Http::withHeaders(['User-Agent' => self::USER_AGENT])
            ->timeout(30)
            ->retry(2, 500)
            ->get($provider['api_url'], [
                'action' => 'query',
                'format' => 'json',
                'prop' => 'imageinfo',
                'iiprop' => 'url|extmetadata',
                'iiurlwidth' => self::WIDTH * 2,
                'titles' => $titles,
            ])
            ->throw()
            ->json();

        $pages = collect($response['query']['pages'] ?? [])
            ->filter(fn (array $page) => isset($page['imageinfo'][0]))
            ->keyBy('title');

        return array_map(function (string $file) use ($pages, $provider) {
            $page = $pages->get("File:{$file}")
                ?? throw new RuntimeException("Commons-ზე ფაილი ვერ მოიძებნა: {$file}");

            $info = $page['imageinfo'][0];
            $meta = $info['extmetadata'] ?? [];
            $artist = trim(html_entity_decode(strip_tags($meta['Artist']['value'] ?? ''))) ?: 'Wikimedia Commons';
            $licenseName = $meta['LicenseShortName']['value'] ?? 'CC';

            return [
                'url' => $info['thumburl'] ?? $info['url'],
                'license' => $this->normalizeLicense($meta['License']['value'] ?? $provider['license']),
                'attribution_text' => "{$artist} — {$licenseName} · Wikimedia Commons",
                'source_url' => $info['descriptionurl'] ?? str_replace('{file}', rawurlencode($file), $provider['source_url']),
            ];
        }, $entry['files']);
    }

    /** Commons-ის ლიცენზიის კოდი → ჩვენი enum (მიგრაცია 000004) */
    private function normalizeLicense(string $code): string
    {
        $code = strtolower($code);

        return match (true) {
            str_starts_with($code, 'cc-by-sa') => 'cc-by-sa',
            str_starts_with($code, 'cc-by') => 'cc-by',
            str_contains($code, 'cc0'), str_contains($code, 'pd'), str_contains($code, 'public') => 'public-domain',
            default => 'licensed',
        };
    }

    /**
     * ატრიბუციის სტრიქონი ერთი სავარჯიშოს ყველა კადრზე. სხვადასხვა ავტორის
     * კადრები ერთ ლუპში რომ მოხვდეს, ორივე უნდა გამოჩნდეს.
     */
    private function attribution(array $frames, array $provider, array $entry): array
    {
        $texts = collect($frames)->pluck('attribution_text')->filter()->unique()->values();

        return [
            'license' => collect($frames)->pluck('license')->filter()->first() ?? $provider['license'],
            'attribution_text' => Str::limit($texts->implode('; '), 250, ''),
            'source_url' => $frames[0]['source_url'] ?? ($provider['source_url'] ?? null),
        ];
    }

    // ---- სურათები ---------------------------------------------------------

    private function download(string $url): GdImage
    {
        $body = Http::withHeaders(['User-Agent' => self::USER_AGENT])
            ->timeout(30)
            ->retry(2, 500)
            ->get($url)
            ->throw()
            ->body();

        $image = @imagecreatefromstring($body);

        if (! $image instanceof GdImage) {
            throw new RuntimeException("სურათი ვერ წაიკითხა: {$url}");
        }

        return $image;
    }

    /** cover-კადრირება ერთ ზომაზე — ანიმაციის ყველა კადრი იდენტური უნდა იყოს */
    private function normalize(GdImage $source): GdImage
    {
        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);
        $scale = max(self::WIDTH / $sourceWidth, self::HEIGHT / $sourceHeight);

        $cropWidth = (int) round(self::WIDTH / $scale);
        $cropHeight = (int) round(self::HEIGHT / $scale);

        $canvas = imagecreatetruecolor(self::WIDTH, self::HEIGHT);
        imagecopyresampled(
            $canvas,
            $source,
            0, 0,
            (int) round(($sourceWidth - $cropWidth) / 2),
            (int) round(($sourceHeight - $cropHeight) / 2),
            self::WIDTH, self::HEIGHT,
            min($cropWidth, $sourceWidth),
            min($cropHeight, $sourceHeight),
        );

        imagedestroy($source);

        return $canvas;
    }

    private function toGif(GdImage $image): string
    {
        ob_start();
        imagegif($image);

        return (string) ob_get_clean();
    }

    private function toJpeg(GdImage $image): string
    {
        ob_start();
        imagejpeg($image, null, 82);

        return (string) ob_get_clean();
    }

    private function register(
        Exercise $exercise,
        string $type,
        string $path,
        int $bytes,
        ?int $durationMs,
        array $attribution,
        $disk,
    ): void {
        ExerciseMedia::updateOrCreate(
            ['exercise_id' => $exercise->id, 'type' => $type],
            [
                'url' => $disk->url($path),
                'width' => self::WIDTH,
                'height' => self::HEIGHT,
                'duration_ms' => $durationMs,
                'bytes' => $bytes,
                'sort_order' => $type === 'loop' ? 0 : 1,
                'license' => $attribution['license'],
                'attribution_text' => $attribution['attribution_text'],
                'source_url' => $attribution['source_url'],
            ],
        );
    }
}
