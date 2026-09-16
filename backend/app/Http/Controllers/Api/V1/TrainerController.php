<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Trainer;
use App\Support\Locale;
use Illuminate\Http\Request;

class TrainerController extends Controller
{
    public function index(Request $request)
    {
        $query = Trainer::query()->with('spots:id,name');

        if ($spotId = $request->query('spot_id')) {
            $query->whereHas('spots', fn ($q) => $q->where('spots.id', $spotId));
        }

        if ($cityId = $request->query('city_id')) {
            $query->where('city_id', $cityId);
        }

        // featured → basic → free; ფასიანი ლისტინგი ზემოთ ჩანს
        $trainers = $query
            ->orderByRaw("CASE listing_tier WHEN 'featured' THEN 0 WHEN 'basic' THEN 1 ELSE 2 END")
            ->orderByDesc('is_verified')
            ->limit(50)
            ->get();

        return response()->json([
            'data' => $trainers->map(fn (Trainer $t) => [
                'id' => $t->id,
                'name' => $t->name,
                'photo_url' => $t->photo_url,
                'bio' => Locale::pick($t->bio, app()->getLocale()),
                'is_verified' => (bool) $t->is_verified,
                'listing_tier' => $t->listing_tier,
                'spots' => $t->spots->map(fn ($s) => ['id' => $s->id, 'name' => $s->name]),
                'contacts' => $t->showsContacts() ? [
                    'instagram' => $t->contact_instagram,
                    'phone' => $t->contact_phone,
                    'telegram' => $t->contact_telegram,
                ] : null,
            ]),
        ]);
    }
}
