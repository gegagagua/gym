<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\FeatureFlag;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ContentController extends Controller
{
    /**
     * დელტა-სინქის მანიფესტი (სპეც. 11.6).
     *
     * 150 სავარჯიშოს მედია ~120 MB-ია — ყოველ გაშვებაზე მისი გადმოწერა
     * მიუღებელია. კლიენტი ჰეშებით ადარებს და მხოლოდ დელტას იღებს.
     */
    public function manifest(Request $request)
    {
        $since = $request->query('since');
        $locale = app()->getLocale();

        $exercises = DB::table('exercises as e')
            ->leftJoin('exercise_translations as t', fn ($j) => $j->on('t.exercise_id', '=', 'e.id')->where('t.locale', $locale))
            ->when($since, fn ($q) => $q->where(fn ($w) => $w->where('e.updated_at', '>', $since)->orWhere('t.updated_at', '>', $since)))
            ->selectRaw("e.id, e.slug, e.updated_at, md5(e.id::text || COALESCE(e.updated_at::text,'') || COALESCE(t.updated_at::text,'')) as hash")
            ->get();

        $media = DB::table('exercise_media')
            ->when($since, fn ($q) => $q->where('updated_at', '>', $since))
            ->select(['id', 'exercise_id', 'type', 'url', 'bytes', 'updated_at'])
            ->get();

        $programs = DB::table('programs as p')
            ->leftJoin('program_translations as pt', fn ($j) => $j->on('pt.program_id', '=', 'p.id')->where('pt.locale', $locale))
            ->when($since, fn ($q) => $q->where(fn ($w) => $w->where('p.updated_at', '>', $since)->orWhere('pt.updated_at', '>', $since)))
            ->selectRaw("p.id, p.slug, p.updated_at, md5(p.id::text || COALESCE(p.updated_at::text,'')) as hash")
            ->get();

        return response()->json([
            'server_time' => now()->toIso8601String(),
            'locale' => $locale,
            'exercises' => $exercises,
            'media' => $media,
            'programs' => $programs,
            'total_media_bytes' => (int) $media->sum('bytes'),
        ]);
    }

    /**
     * Feature flags per country (სპეც. 19.1).
     * v2-ში შოპი მხოლოდ GE-ში იმუშავებს — თუ ეს v1-ში არ ჩაიდება,
     * კოდბეისი ორად გაიხლიჩება.
     */
    public function config(Request $request)
    {
        $country = strtoupper($request->query('country', $request->user()?->country_code ?? 'GE'));

        $flags = Cache::remember("flags:{$country}", 300, function () use ($country) {
            return FeatureFlag::whereIn('country_code', [$country, '*'])
                ->get()
                // კონკრეტული ქვეყანა '*'-ს ფარავს
                ->sortBy(fn ($f) => $f->country_code === '*' ? 0 : 1)
                ->keyBy('key')
                ->map(fn ($f) => ['enabled' => (bool) $f->is_enabled, 'config' => $f->config])
                ->all();
        });

        return response()->json([
            'country' => $country,
            'flags' => $flags,
            'limits' => [
                'daily_xp_cap' => config('kalisteni.xp.daily_cap'),
                'max_session_minutes' => config('kalisteni.session.max_minutes'),
                'checkin_radius_m' => config('kalisteni.spots.checkin_radius_m'),
                'sync_batch_max' => config('kalisteni.session.sync_batch_max'),
            ],
            'server_time' => now()->toIso8601String(),
        ]);
    }

    /** ატრიბუციის ეკრანი — ავტომატურად exercise_media-დან (სპეც. 13.1) */
    public function attributions()
    {
        $rows = DB::table('exercise_media')
            ->whereNotIn('license', ['own', 'public-domain'])
            ->whereNotNull('attribution_text')
            ->select(['license', 'attribution_text', 'source_url'])
            ->distinct()
            ->orderBy('attribution_text')
            ->get();

        return response()->json(['data' => $rows]);
    }

    public function cities()
    {
        return response()->json([
            'data' => DB::table('cities')->where('is_active', true)->orderBy('id')->get(),
        ]);
    }
}
