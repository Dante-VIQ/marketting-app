<?php

namespace App\Jobs;

use App\Models\Brand;
use App\Models\AiAction;
use App\Services\AI\ContentGeneratorService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessApprovedActionsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected Brand $brand;

    public function __construct(Brand $brand)
    {
        $this->brand = $brand;
    }

    public function handle(ContentGeneratorService $contentGenerator): void
    {
        if (!$this->brand->is_active) {
            Log::info('Brand is not active, skipping content generation', ['brand_id' => $this->brand->id]);
            return;
        }

        // Get approved actions that haven't generated content yet
        $actions = AiAction::where('brand_id', $this->brand->id)
            ->where('status', 'approved')
            ->whereDoesntHave('contentDraft')
            ->get();

        Log::info('Processing approved actions', [
            'brand_id' => $this->brand->id,
            'count' => $actions->count(),
        ]);

        foreach ($actions as $action) {
            try {
                $draft = $contentGenerator->generateForAction($action);

                if ($draft) {
                    Log::info('Content generated for action', [
                        'action_id' => $action->id,
                        'draft_id' => $draft->id,
                    ]);
                } else {
                    Log::warning('Content generation failed for action', [
                        'action_id' => $action->id,
                    ]);
                }
            } catch (\Exception $e) {
                Log::error('Error generating content for action', [
                    'action_id' => $action->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}