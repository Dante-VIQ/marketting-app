<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Services\Lead\LeadManagerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TravelLeadController extends Controller
{
    protected LeadManagerService $leadManager;

    public function __construct(LeadManagerService $leadManager)
    {
        $this->leadManager = $leadManager;
    }

    /**
     * Store a new travel booking lead.
     */
    public function store(Request $request)
    {
        // Validate the request
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:20',
            'country' => 'nullable|string|max:255',
            'tour_type' => 'nullable|string|max:255',
            'number_of_people' => 'nullable|integer|min:1',
            'preferred_date' => 'nullable|date',
            'duration_days' => 'nullable|integer|min:1',
            'budget_range' => 'nullable|string|max:255',
            'special_requests' => 'nullable|string',
            'message' => 'nullable|string',
            'brand_slug' => 'nullable|string|exists:brands,slug',
        ]);

        // Get the brand
        $brand = $this->getBrand($validated['brand_slug'] ?? null);
        if (!$brand) {
            Log::error('Brand not found for travel lead', ['slug' => $validated['brand_slug'] ?? null]);
            return response()->json([
                'success' => false,
                'message' => 'Brand not found',
            ], 404);
        }

        try {
            // Build the travel booking message
            $message = $this->buildTravelMessage($validated);

            // Create the lead
            $lead = $this->leadManager->createLead([
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'] ?? null,
                'message' => $message,
                'source' => 'website',
                'category' => 'travel', // Critical: sets the category
                'metadata' => [
                    'type' => 'travel',
                    'tour_type' => $validated['tour_type'] ?? null,
                    'number_of_people' => $validated['number_of_people'] ?? null,
                    'preferred_date' => $validated['preferred_date'] ?? null,
                    'duration_days' => $validated['duration_days'] ?? null,
                    'budget_range' => $validated['budget_range'] ?? null,
                    'country' => $validated['country'] ?? null,
                    'special_requests' => $validated['special_requests'] ?? null,
                ],
            ], $brand);

            return response()->json([
                'success' => true,
                'message' => 'Travel booking request received! We\'ll get back to you shortly.',
                'lead_id' => $lead->id,
            ], 201);
        } catch (\Exception $e) {
            Log::error('Error creating travel lead', [
                'error' => $e->getMessage(),
                'data' => $validated,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Something went wrong. Please try again.',
            ], 500);
        }
    }

    /**
     * Build a travel booking message from form data.
     */
    protected function buildTravelMessage(array $data): string
    {
        $parts = [];
        $parts[] = "🌍 NEW TRAVEL BOOKING REQUEST";
        $parts[] = "";

        if (!empty($data['tour_type'])) {
            $parts[] = "Tour Type: {$data['tour_type']}";
        }

        if (!empty($data['number_of_people'])) {
            $parts[] = "Number of People: {$data['number_of_people']}";
        }

        if (!empty($data['preferred_date'])) {
            $parts[] = "Preferred Date: {$data['preferred_date']}";
        }

        if (!empty($data['duration_days'])) {
            $parts[] = "Duration: {$data['duration_days']} days";
        }

        if (!empty($data['budget_range'])) {
            $parts[] = "Budget Range: {$data['budget_range']}";
        }

        if (!empty($data['country'])) {
            $parts[] = "Destination Country: {$data['country']}";
        }

        if (!empty($data['special_requests'])) {
            $parts[] = "Special Requests: {$data['special_requests']}";
        }

        if (!empty($data['message'])) {
            $parts[] = "";
            $parts[] = "Additional Details:";
            $parts[] = $data['message'];
        }

        return implode("\n", $parts);
    }

    /**
     * Get the brand from slug or default.
     */
    protected function getBrand(?string $slug): ?Brand
    {
        if ($slug) {
            return Brand::where('slug', $slug)->first();
        }
        return Brand::where('slug', 'vumbi-ventures')->first();
    }
}