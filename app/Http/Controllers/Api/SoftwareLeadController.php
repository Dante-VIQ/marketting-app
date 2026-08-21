<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Services\Lead\LeadManagerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SoftwareLeadController extends Controller
{
    protected LeadManagerService $leadManager;

    public function __construct(LeadManagerService $leadManager)
    {
        $this->leadManager = $leadManager;
    }

    /**
     * Store a new software engineering lead.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:20',
            'company' => 'nullable|string|max:255',
            'project_type' => 'nullable|string|max:255',
            'project_description' => 'nullable|string',
            'timeline' => 'nullable|string|max:255',
            'budget' => 'nullable|string|max:255',
            'current_stack' => 'nullable|string|max:255',
            'team_size' => 'nullable|integer|min:1',
            'message' => 'nullable|string',
            'brand_slug' => 'nullable|string|exists:brands,slug',
        ]);

        $brand = $this->getBrand($validated['brand_slug'] ?? null);
        if (!$brand) {
            Log::error('Brand not found for software lead', ['slug' => $validated['brand_slug'] ?? null]);
            return response()->json([
                'success' => false,
                'message' => 'Brand not found',
            ], 404);
        }

        try {
            $message = $this->buildSoftwareMessage($validated);

            $lead = $this->leadManager->createLead([
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'] ?? null,
                'company' => $validated['company'] ?? null,
                'message' => $message,
                'source' => 'website',
                'category' => 'software', // Critical: sets the category
                'metadata' => [
                    'type' => 'software',
                    'project_type' => $validated['project_type'] ?? null,
                    'project_description' => $validated['project_description'] ?? null,
                    'timeline' => $validated['timeline'] ?? null,
                    'budget' => $validated['budget'] ?? null,
                    'current_stack' => $validated['current_stack'] ?? null,
                    'team_size' => $validated['team_size'] ?? null,
                ],
            ], $brand);

            return response()->json([
                'success' => true,
                'message' => 'Software request received! We\'ll be in touch shortly.',
                'lead_id' => $lead->id,
            ], 201);
        } catch (\Exception $e) {
            Log::error('Error creating software lead', [
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
     * Build a software booking message from form data.
     */
    protected function buildSoftwareMessage(array $data): string
    {
        $parts = [];
        $parts[] = "💻 NEW SOFTWARE ENGINEERING REQUEST";
        $parts[] = "";

        if (!empty($data['project_type'])) {
            $parts[] = "Project Type: {$data['project_type']}";
        }

        if (!empty($data['project_description'])) {
            $parts[] = "";
            $parts[] = "Project Description:";
            $parts[] = $data['project_description'];
        }

        if (!empty($data['timeline'])) {
            $parts[] = "";
            $parts[] = "Timeline: {$data['timeline']}";
        }

        if (!empty($data['budget'])) {
            $parts[] = "Budget: {$data['budget']}";
        }

        if (!empty($data['current_stack'])) {
            $parts[] = "Current Tech Stack: {$data['current_stack']}";
        }

        if (!empty($data['team_size'])) {
            $parts[] = "Team Size: {$data['team_size']}";
        }

        if (!empty($data['message'])) {
            $parts[] = "";
            $parts[] = "Additional Details:";
            $parts[] = $data['message'];
        }

        return implode("\n", $parts);
    }

    protected function getBrand(?string $slug): ?Brand
    {
        if ($slug) {
            return Brand::where('slug', $slug)->first();
        }
        return Brand::where('slug', 'vumbi-ventures')->first();
    }
}