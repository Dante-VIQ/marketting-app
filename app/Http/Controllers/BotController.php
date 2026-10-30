<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Services\Bot\BotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BotController extends Controller
{
    public function __construct(private BotService $bot) {}

    /**
     * POST /chat/message
     *
     * Accepts a JSON body: { "message": "...", "brand_id": 1 }
     * Returns:             { "reply": "...", "session_id": 1, "action_type": "..." }
     */
    public function message(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'message'  => ['required', 'string', 'max:2000'],
            'brand_id' => ['nullable', 'integer', 'exists:brands,id'],
        ]);

        $user = Auth::user();

        // Resolve brand — prefer explicit brand_id, fall back to user's first brand
        $brand = null;
        if (!empty($validated['brand_id'])) {
            $brand = Brand::find($validated['brand_id']);
        } else {
            $brand = $user->brands()->first();
        }

        // Onboarding mode: no brand yet
        if (!$brand) {
            $result = $this->bot->onboardingChat($validated['message'], $user);

            return response()->json([
                'reply'       => $result['reply'],
                'session_id'  => $result['session_id'],
                'action_type' => null,
                'mode'        => 'onboarding',
            ]);
        }

        // Authorise: user must belong to this brand
        $this->authorize('view', $brand);

        $result = $this->bot->chat($validated['message'], $brand, $user);

        return response()->json([
            'reply'       => $result['reply'],
            'session_id'  => $result['session_id'],
            'action_type' => $result['action_type'],
            'mode'        => 'assistant',
        ]);
    }

    /**
     * DELETE /chat/session
     *
     * Clears the current session so the conversation starts fresh.
     */
    public function clearSession(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'brand_id' => ['nullable', 'integer', 'exists:brands,id'],
        ]);

        $user  = Auth::user();
        $brand = !empty($validated['brand_id'])
            ? Brand::find($validated['brand_id'])
            : $user->brands()->first();

        if ($brand) {
            \App\Models\ChatSession::where('brand_id', $brand->id)
                ->where('user_id', $user->id)
                ->where('mode', 'assistant')
                ->delete();
        }

        return response()->json(['cleared' => true]);
    }
}
