<?php

namespace App\Jobs;

use App\Models\Brand;
use App\Models\Lead;
use App\Services\Lead\LeadManagerService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessNewLeadsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected Brand $brand;

    public function __construct(Brand $brand)
    {
        $this->brand = $brand;
    }

    public function handle(LeadManagerService $leadManager): void
    {
        if (!$this->brand->is_active) {
            Log::info('Brand is not active, skipping lead processing', ['brand_id' => $this->brand->id]);
            return;
        }

        // Get unprocessed leads
        $leads = Lead::where('brand_id', $this->brand->id)
            ->whereNull('ai_summary')
            ->limit(50)
            ->get();

        Log::info('Processing new leads', [
            'brand_id' => $this->brand->id,
            'count' => $leads->count(),
        ]);

        foreach ($leads as $lead) {
            try {
                $leadManager->processLeadWithAI($lead);
            } catch (\Exception $e) {
                Log::error('Error processing lead', [
                    'lead_id' => $lead->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}