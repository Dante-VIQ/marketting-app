<?php

namespace App\Jobs;

use App\Models\AiAction;
use App\Models\ContentDraft;
use App\Services\AI\ContentGeneratorService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateContentForActionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected AiAction $action;

    public function __construct(AiAction $action)
    {
        $this->action = $action;
    }

    public function handle(ContentGeneratorService $contentGenerator): void
    {
        Log::info('Job started for action', [
            'action_id' => $this->action->id,
            'title' => $this->action->title,
        ]);

        try {
            // Check if action is still valid
            if ($this->action->status !== 'approved') {
                Log::info('Content generation skipped: Action not approved', [
                    'action_id' => $this->action->id,
                    'status' => $this->action->status,
                ]);
                return;
            }

            // Check if draft already exists
            if ($this->action->contentDraft()->exists()) {
                Log::info('Content generation skipped: Draft already exists', [
                    'action_id' => $this->action->id,
                ]);
                return;
            }

            Log::info('Calling content generator for action', [
                'action_id' => $this->action->id,
            ]);

            // Generate the content
            $draft = $contentGenerator->generateForAction($this->action);

            if ($draft) {
                Log::info('Content generated successfully', [
                    'action_id' => $this->action->id,
                    'draft_id' => $draft->id,
                    'title' => $draft->title,
                    'content_length' => strlen($draft->content),
                ]);
            } else {
                Log::error('Content generation returned null', [
                    'action_id' => $this->action->id,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Content generation job failed', [
                'action_id' => $this->action->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            throw $e;
        }
    }
}