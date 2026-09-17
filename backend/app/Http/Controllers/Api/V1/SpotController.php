<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\SpotResource;
use App\Models\Spot;
use App\Models\SpotEquipment;
use App\Models\SpotMedia;
use App\Models\SpotRating;
use App\Services\SpotService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class SpotController extends Controller
{
    public function __construct(private readonly SpotService $spots) {}

    public function nearby(Request $request)
    {
        $data = $request->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
            'radius' => ['nullable', 'integer', 'min:100', 'max:50000'],
            'equipment' => ['nullable', 'array'],
            'equipment.*' => ['in:'.implode(',', SpotEquipment::TAGS)],
        ]);

        return response()->json([
            'data' => $this->spots->nearby(
                (float) $data['lat'],
                (float) $data['lng'],
                $data['radius'] ?? null,
                $data['equipment'] ?? [],
            ),
        ]);
    }

    public function show(Spot $spot)
    {
        abort_unless($spot->status === 'verified', 404);

        $spot = Spot::withCoordinates()
            ->with(['equipment', 'media', 'trainers'])
            ->findOrFail($spot->id);

        return new SpotResource($spot);
    }

    /**
     * UGC მოედნის დამატება. სტატუსი pending → ავტო-შემოწმება
     * დუბლიკატზე → მოდერატორის დადასტურება (სპეც. 9.2).
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
            'type' => ['required', 'in:yard,park,school,stadium,commercial'],
            'access' => ['nullable', 'in:public,paid,restricted'],
            'condition_rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'has_lighting' => ['nullable', 'boolean'],
            'city_id' => ['nullable', 'exists:cities,id'],
            'description' => ['nullable', 'array'],
            'equipment' => ['required', 'array', 'min:1'],
            'equipment.*' => ['in:'.implode(',', SpotEquipment::TAGS)],
            'photos' => ['nullable', 'array', 'max:'.config('kalisteni.spots.max_photos')],
            'photos.*' => ['image', 'max:8192'],
        ]);

        if ($duplicate = $this->spots->findDuplicate((float) $data['lat'], (float) $data['lng'])) {
            return response()->json([
                'error' => 'duplicate',
                'message' => __('A spot already exists within :m metres.', ['m' => config('kalisteni.spots.duplicate_radius_m')]),
                'existing_spot_id' => $duplicate->id,
            ], 409);
        }

        $spot = $this->spots->create([
            'name' => $data['name'],
            'type' => $data['type'],
            'access' => $data['access'] ?? 'public',
            'condition_rating' => $data['condition_rating'] ?? 3,
            'has_lighting' => $data['has_lighting'] ?? false,
            'city_id' => $data['city_id'] ?? null,
            'description' => $data['description'] ?? null,
            'status' => 'pending',
            'source' => 'ugc',
            'created_by_user_id' => $request->user()->id,
        ], (float) $data['lat'], (float) $data['lng']);

        foreach ($data['equipment'] as $tag) {
            SpotEquipment::create(['spot_id' => $spot->id, 'equipment_tag' => $tag]);
        }

        foreach ($request->file('photos', []) as $i => $photo) {
            // EXIF-ის სრული გაწმენდა GPS-ის გაჟონვის თავიდან ასაცილებლად
            // ხდება queue job-ში ტრანსკოდირებასთან ერთად (სპეც. 17).
            $path = $photo->store("spots/{$spot->id}", config('filesystems.default'));

            SpotMedia::create([
                'spot_id' => $spot->id,
                'url' => Storage::url($path),
                'uploaded_by_user_id' => $request->user()->id,
                'is_primary' => $i === 0,
                'status' => 'pending',
            ]);
        }

        return response()->json([
            'spot' => new SpotResource($spot->load('equipment', 'media')),
            'message' => __('Submitted for moderation. You get :xp XP once it is verified.', [
                'xp' => config('kalisteni.spots.spot_added_xp'),
            ]),
        ], 201);
    }

    public function checkIn(Request $request, Spot $spot)
    {
        $data = $request->validate([
            'lat' => ['required', 'numeric'],
            'lng' => ['required', 'numeric'],
            'accuracy_m' => ['nullable', 'integer', 'min:0', 'max:10000'],
        ]);

        $result = $this->spots->checkIn(
            $request->user(),
            $spot,
            (float) $data['lat'],
            (float) $data['lng'],
            $data['accuracy_m'] ?? null,
        );

        if (! $result['ok']) {
            return response()->json([
                'error' => 'too_far',
                'distance_m' => $result['distance_m'],
                'required_m' => config('kalisteni.spots.checkin_radius_m'),
            ], 422);
        }

        return response()->json([
            'checkin_id' => $result['checkin']->id,
            'expires_at' => $result['checkin']->expires_at->toIso8601String(),
            'distance_m' => $result['distance_m'],
            'xp_multiplier' => $result['checkin']->is_valid ? config('kalisteni.xp.checkin_mod') : 1.0,
        ], 201);
    }

    public function rate(Request $request, Spot $spot)
    {
        $data = $request->validate(['condition_rating' => ['required', 'integer', 'min:1', 'max:5']]);

        DB::transaction(function () use ($request, $spot, $data) {
            $existing = SpotRating::where('spot_id', $spot->id)->where('user_id', $request->user()->id)->first();

            if ($existing) {
                $spot->decrement('rating_sum', $existing->condition_rating);
                $existing->update($data);
            } else {
                SpotRating::create($data + ['spot_id' => $spot->id, 'user_id' => $request->user()->id]);
                $spot->increment('rating_count');
            }

            $spot->increment('rating_sum', $data['condition_rating']);
        });

        return response()->json(['condition_rating' => $spot->fresh()->averageRating()]);
    }

    public function myCheckin(Request $request)
    {
        $checkin = $request->user()->checkins()
            ->with('spot')
            ->where('expires_at', '>', now())
            ->where('is_valid', true)
            ->latest('checked_in_at')
            ->first();

        return response()->json(['checkin' => $checkin]);
    }
}
