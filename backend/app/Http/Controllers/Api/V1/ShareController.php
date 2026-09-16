<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Jobs\RenderShareCard;
use App\Models\ShareCard;
use Illuminate\Http\Request;

class ShareController extends Controller
{
    /** გაზიარების ბარათი Stories-ისთვის — რენდერი queue-ში (სპეც. 11.4) */
    public function store(Request $request)
    {
        $data = $request->validate([
            'type' => ['required', 'in:session,streak,league,record'],
            'session_id' => ['nullable', 'integer'],
            'period' => ['nullable', 'in:week,month'],
        ]);

        $card = ShareCard::create([
            'user_id' => $request->user()->id,
            'session_id' => $data['session_id'] ?? null,
            'type' => $data['type'],
            'status' => 'queued',
            'expires_at' => now()->addDays(7),
        ]);

        RenderShareCard::dispatch($card->id);

        return response()->json(['card' => $card], 202);
    }

    public function show(Request $request, ShareCard $card)
    {
        abort_unless($card->user_id === $request->user()->id, 403);

        return response()->json(['card' => $card]);
    }
}
