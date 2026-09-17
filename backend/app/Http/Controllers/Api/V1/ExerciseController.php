<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ExerciseResource;
use App\Models\Exercise;
use App\Models\SpotEquipment;
use Illuminate\Http\Request;

class ExerciseController extends Controller
{
    /**
     * პროფილში ინვენტარი წვდომის დონეა (none|bar|yard|gym), სავარჯიშოზე კი
     * ფიზიკური ტეგი (pull_up_bar…). ორი ლექსიკონი ერთმანეთს არ ცნობს —
     * თარგმანი აქ ხდება, თორემ „ჩემი ინვენტარით" ფილტრი ჩუმად ცარიელს დააბრუნებს.
     */
    private const ACCESS_TAGS = [
        'none' => [],
        'bar' => ['pull_up_bar', 'low_bar'],
        'yard' => [
            'pull_up_bar', 'low_bar', 'parallel_bars', 'wall_bars', 'rings',
            'monkey_bars', 'horizontal_ladder', 'bench', 'rope',
        ],
        'gym' => [...SpotEquipment::TAGS, ...Exercise::GYM_TAGS],
    ];

    public function index(Request $request)
    {
        $query = Exercise::active()->with(['translations', 'media']);

        if ($force = $request->query('force')) {
            $query->whereIn('force', explode(',', $force));
        }

        if ($category = $request->query('category')) {
            $query->whereIn('category', explode(',', $category));
        }

        if ($zone = $request->query('zone')) {
            $query->whereIn('zone', explode(',', $zone));
        }

        if ($group = $request->query('skill_group')) {
            $query->where('skill_group', $group);
        }

        if ($level = $request->query('level')) {
            $query->where('level_min', '<=', $level)->where('level_max', '>=', $level);
        }

        // ინვენტარი: აჩვენე ის, რაც მომხმარებლის აღჭურვილობით შესრულებადია
        if ($equipment = $request->query('equipment')) {
            $tags = self::expandEquipment(explode(',', $equipment));
            $query->where(function ($q) use ($tags) {
                $q->whereJsonLength('equipment', 0)->orWhereNull('equipment');
                foreach ($tags as $tag) {
                    $q->orWhereJsonContains('equipment', $tag);
                }
            });
        }

        // დელტა-სინქი: კლიენტი მხოლოდ შეცვლილს იღებს (სპეც. 11.6)
        if ($since = $request->query('updated_since')) {
            $query->where('updated_at', '>', $since);
        }

        if ($q = $request->query('q')) {
            $query->whereHas('translations', fn ($t) => $t->where('name', 'ilike', "%{$q}%"));
        }

        return ExerciseResource::collection(
            $query->orderBy('difficulty_coef')->limit((int) $request->query('limit', 300))->get()
        );
    }

    /**
     * @param  list<string>  $values  წვდომის დონეები ან ფიზიკური ტეგები — ორივე მიიღება
     *
     * პლანერიც (PlanGenerator) ამას იძახის — ლექსიკონის თარგმანი ერთ ადგილას რჩება.
     */
    public static function expandEquipment(array $values): array
    {
        $tags = [];

        foreach (array_filter(array_map('trim', $values)) as $value) {
            $tags = array_merge($tags, self::ACCESS_TAGS[$value] ?? [$value]);
        }

        return array_values(array_unique($tags));
    }

    public function show(Exercise $exercise)
    {
        abort_unless($exercise->is_active, 404);

        return new ExerciseResource($exercise->load(['translations', 'media', 'progressionFrom.translations', 'progressionTo.translations']));
    }
}
