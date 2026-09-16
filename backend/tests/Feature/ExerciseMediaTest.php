<?php

namespace Tests\Feature;

use App\Models\Exercise;
use App\Models\ExerciseMedia;
use App\Services\Media\AnimatedGif;
use App\Services\Media\ExerciseMediaFetcher;
use Database\Seeders\ExerciseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ExerciseMediaTest extends TestCase
{
    use RefreshDatabase;

    /** ცალკადრიანი GIF GD-დან — ენკოდერის შესატანი */
    private function frame(int $r, int $g, int $b): string
    {
        $image = imagecreatetruecolor(ExerciseMediaFetcher::WIDTH, ExerciseMediaFetcher::HEIGHT);
        imagefilledrectangle($image, 0, 0, 480, 360, imagecolorallocate($image, $r, $g, $b));

        ob_start();
        imagegif($image);
        imagedestroy($image);

        return (string) ob_get_clean();
    }

    /** GIF89a-ს ბლოკებზე გავლა — რამდენი კადრი ჩაიწერა და ლუპავს თუ არა */
    private function inspect(string $gif): array
    {
        $cursor = 13;
        $packed = ord($gif[10]);

        if ($packed & 0x80) {
            $cursor += 3 * (1 << (($packed & 0x07) + 1));
        }

        $images = 0;
        $delays = [];

        while ($cursor < strlen($gif)) {
            $marker = ord($gif[$cursor]);

            if ($marker === 0x3B) {
                break;
            }

            if ($marker === 0x21) {
                if (ord($gif[$cursor + 1]) === 0xF9) {
                    $delays[] = unpack('v', substr($gif, $cursor + 4, 2))[1];
                }

                $cursor += 2;
            } else {
                $images++;
                $descriptor = ord($gif[$cursor + 9]);
                $cursor += 10;

                if ($descriptor & 0x80) {
                    $cursor += 3 * (1 << (($descriptor & 0x07) + 1));
                }

                $cursor++;
            }

            while (($size = ord($gif[$cursor])) !== 0) {
                $cursor += $size + 1;
            }

            $cursor++;
        }

        return ['images' => $images, 'delays' => $delays, 'loops' => str_contains($gif, 'NETSCAPE2.0')];
    }

    public function test_it_stitches_gd_frames_into_a_looping_animation(): void
    {
        $gif = AnimatedGif::encode([$this->frame(255, 0, 0), $this->frame(0, 255, 0), $this->frame(0, 0, 255)], 700);

        $this->assertSame('GIF89a', substr($gif, 0, 6));
        $this->assertSame(';', substr($gif, -1));

        $parsed = $this->inspect($gif);

        $this->assertSame(3, $parsed['images']);
        $this->assertTrue($parsed['loops']);
        // 700 ms → 70 ერთეული (1/100 წმ)
        $this->assertSame([70, 70, 70], $parsed['delays']);
    }

    public function test_a_single_frame_is_not_an_animation(): void
    {
        $this->expectException(\RuntimeException::class);

        AnimatedGif::encode([$this->frame(255, 0, 0)], 700);
    }

    public function test_every_mapped_slug_exists_in_the_exercise_catalogue(): void
    {
        $this->seed(ExerciseSeeder::class);

        $sources = app(ExerciseMediaFetcher::class)->sources();
        $slugs = Exercise::pluck('slug')->all();

        foreach (array_keys($sources['exercises']) as $slug) {
            $this->assertContains($slug, $slugs, "მედიის რუკაში უცნობი slug: {$slug}");
        }

        // ყოველი სავარჯიშო ან წყაროშია, ან საკუთარ გადაღებას ელოდება — უჩუმრად არ იკარგება
        foreach ($slugs as $slug) {
            $this->assertTrue(
                isset($sources['exercises'][$slug]) || isset($sources['pending_own_footage'][$slug]),
                "{$slug} არც წყაროშია და არც მოსალოდნელებში",
            );
        }
    }

    public function test_it_registers_already_downloaded_files_without_network(): void
    {
        Storage::fake('public');
        $this->seed(ExerciseSeeder::class);

        Storage::disk('public')->put('exercises/push-up/poster.jpg', 'jpeg-bytes');
        Storage::disk('public')->put('exercises/push-up/loop.gif', 'gif-bytes');

        $exercise = Exercise::where('slug', 'push-up')->firstOrFail();

        $this->assertTrue(app(ExerciseMediaFetcher::class)->registerExisting($exercise));

        $loop = $exercise->media()->where('type', 'loop')->firstOrFail();

        $this->assertSame('public-domain', $loop->license);
        $this->assertStringContainsString('free-exercise-db', $loop->attribution_text);
        $this->assertFalse($loop->requiresAttribution());
        $this->assertSame(2, $exercise->media()->count());
    }

    /** პროფილის „წვდომის დონე" და სავარჯიშოს ფიზიკური ტეგი ორი ლექსიკონია */
    public function test_the_equipment_filter_understands_profile_access_levels(): void
    {
        $this->seed(ExerciseSeeder::class);

        $bodyweight = fn (array $data) => collect($data)->every(fn ($row) => $row['equipment'] === []);

        // „none" — მხოლოდ ინვენტარის გარეშე შესრულებადი
        $none = $this->getJson('/api/v1/exercises?equipment=none')->assertOk()->json('data');
        $this->assertTrue($bodyweight($none));

        // „bar" — ტურნიკიც ემატება
        $bar = $this->getJson('/api/v1/exercises?equipment=bar')->assertOk()->json('data');
        $this->assertGreaterThan(count($none), count($bar));
        $this->assertContains('pull-up', collect($bar)->pluck('slug')->all());

        // ფიზიკური ტეგიც პირდაპირ მუშაობს — API ორივე ლექსიკონს იღებს
        $parallel = $this->getJson('/api/v1/exercises?equipment=parallel_bars')->assertOk()->json('data');
        $this->assertContains('dip', collect($parallel)->pluck('slug')->all());
        $this->assertNotContains('pull-up', collect($parallel)->pluck('slug')->all());
    }

    public function test_the_api_exposes_loops_and_the_attribution_screen_lists_only_credited_sources(): void
    {
        $this->seed(ExerciseSeeder::class);

        $exercise = Exercise::where('slug', 'burpee')->firstOrFail();

        ExerciseMedia::create([
            'exercise_id' => $exercise->id,
            'type' => 'loop',
            'url' => 'http://localhost/storage/exercises/burpee/loop.gif',
            'width' => 480,
            'height' => 360,
            'duration_ms' => 2700,
            'bytes' => 1024,
            'license' => 'cc-by-sa',
            'attribution_text' => 'Taco fleur — CC BY-SA 4.0 · Wikimedia Commons',
            'source_url' => 'https://commons.wikimedia.org/wiki/File:Burpee_2_Squat.jpg',
        ]);

        ExerciseMedia::create([
            'exercise_id' => Exercise::where('slug', 'push-up')->value('id'),
            'type' => 'loop',
            'url' => 'http://localhost/storage/exercises/push-up/loop.gif',
            'license' => 'public-domain',
            'attribution_text' => 'free-exercise-db — The Unlicense',
            'source_url' => 'https://github.com/yuhonas/free-exercise-db',
        ]);

        $this->getJson("/api/v1/exercises/{$exercise->id}")
            ->assertOk()
            ->assertJsonPath('data.media.0.type', 'loop')
            ->assertJsonPath('data.media.0.license', 'cc-by-sa');

        // public-domain ატრიბუციას არ საჭიროებს — ეკრანზე მხოლოდ CC უნდა იყოს (სპეც. 13.1)
        $this->getJson('/api/v1/attributions')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.license', 'cc-by-sa');
    }
}
