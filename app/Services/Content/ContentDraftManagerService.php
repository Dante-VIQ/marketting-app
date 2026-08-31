<?php

namespace App\Services\Content;

use App\Models\Brand;
use App\Models\ContentDraft;
use App\Models\AiAction;
use App\Services\AI\ContentGeneratorService;
use Illuminate\Support\Facades\Log;

class ContentDraftManagerService
{
    protected ContentGeneratorService $contentGenerator;

    public function __construct(ContentGeneratorService $contentGenerator)
    {
        $this->contentGenerator = $contentGenerator;
    }

    /**
     * Get drafts for a brand.
     */
    public function getDraftsForBrand(Brand $brand, ?string $status = null): array
    {
        $query = ContentDraft::where('brand_id', $brand->id);

        if ($status) {
            $query->where('status', $status);
        }

        return $query->orderBy('created_at', 'desc')->get()->toArray();
    }

    /**
     * Submit a draft for review (moves from draft to review).
     */
    public function submitForReview(ContentDraft $draft): void
    {
        $draft->moveToReview();

        Log::info('Draft submitted for review', [
            'draft_id' => $draft->id,
            'brand_id' => $draft->brand_id,
            'title' => $draft->title,
        ]);
    }

    /**
     * Request revisions for a draft (moves from review to revision).
     */
    public function requestRevision(ContentDraft $draft, string $reason, ?string $notes = null): void
    {
        $draft->moveToRevision($reason, $notes);

        // Reset the associated action to approved so it can be regenerated
        if ($draft->action_id) {
            $action = AiAction::find($draft->action_id);
            if ($action) {
                $action->status = 'approved';
                $action->save();
            }
        }

        Log::info('Draft moved to revision', [
            'draft_id' => $draft->id,
            'brand_id' => $draft->brand_id,
            'reason' => $reason,
            'revision_count' => $draft->metadata['revision_count'] ?? 0,
        ]);
    }

    /**
     * Regenerate a draft after revision (moves from revision to draft).
     */
    public function regenerateDraft(ContentDraft $draft): ?ContentDraft
    {
        // Check if there's an associated action
        if (!$draft->action_id) {
            Log::warning('Cannot regenerate draft: No associated action', [
                'draft_id' => $draft->id,
            ]);
            return null;
        }

        $action = AiAction::find($draft->action_id);
        if (!$action) {
            Log::warning('Cannot regenerate draft: Action not found', [
                'draft_id' => $draft->id,
                'action_id' => $draft->action_id,
            ]);
            return null;
        }

        // Get revision feedback
        $feedback = $draft->metadata['revision_notes'] ?? '';
        $reason = $draft->metadata['revision_reason'] ?? '';

        // Generate new content with feedback
        $newDraft = $this->contentGenerator->generateForAction($action, $feedback);

        if ($newDraft) {
            // Mark the new draft as draft (not revision)
            $newDraft->status = ContentDraft::STATUS_DRAFT;
            $newDraft->metadata = array_merge($newDraft->metadata ?? [], [
                'regenerated_from' => $draft->id,
                'regenerated_at' => now()->toDateTimeString(),
                                              'revision_feedback' => $feedback,
                                              'revision_reason' => $reason,
            ]);
            $newDraft->save();

            // Delete the old draft
            $draft->delete();

            Log::info('Draft regenerated with revisions', [
                'old_draft_id' => $draft->id,
                'new_draft_id' => $newDraft->id,
                'action_id' => $action->id,
            ]);

            return $newDraft;
        }

        Log::error('Failed to regenerate draft', [
            'draft_id' => $draft->id,
            'action_id' => $action->id,
        ]);

        return null;
    }

    /**
     * Approve a draft (moves from review to approved).
     */
    public function approveDraft(ContentDraft $draft, ?string $notes = null): void
    {
        $draft->approve($notes);

        if ($draft->action_id) {
            $action = AiAction::find($draft->action_id);
            if ($action) {
                $action->status = 'published';
                $action->executed_at = now();
                $action->save();
            }
        }

        Log::info('Draft approved', [
            'draft_id' => $draft->id,
            'brand_id' => $draft->brand_id,
            'title' => $draft->title,
        ]);
    }

    /**
     * Mark a draft as published (moves from approved to published).
     */
    public function markAsPublished(ContentDraft $draft, ?string $url = null): void
    {
        $draft->status = ContentDraft::STATUS_PUBLISHED;
        $draft->published_at = now();
        if ($url) {
            $draft->published_url = $url;
        }
        $draft->save();

        Log::info('Draft published', [
            'draft_id' => $draft->id,
            'brand_id' => $draft->brand_id,
            'url' => $url ?? 'N/A',
        ]);
    }
}
