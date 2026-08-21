<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Services\Lead\LeadManagerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class LeadInteractionController extends Controller
{
    protected LeadManagerService $leadManager;

    public function __construct(LeadManagerService $leadManager)
    {
        $this->leadManager = $leadManager;
    }

    public function store(Request $request, Lead $lead)
    {
        $validated = $request->validate([
            'type' => 'required|in:email,call,meeting,note,task,follow_up',
            'content' => 'required|string',
            'notes' => 'nullable|string',
        ]);

        try {
            $interaction = $this->leadManager->addInteraction(
                $lead,
                $validated['type'],
                $validated['content'],
                $validated['notes'] ?? null
            );

            return response()->json([
                'success' => true,
                'message' => 'Interaction added successfully',
                'interaction' => $interaction,
            ], 201);
        } catch (\Exception $e) {
            Log::error('Error adding interaction', [
                'lead_id' => $lead->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Something went wrong',
            ], 500);
        }
    }
}