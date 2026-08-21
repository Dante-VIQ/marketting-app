<?php

namespace App\Services\Content;

use App\Models\Brand;
use App\Models\ContentDraft;
use App\Models\AiAction;
use Illuminate\Support\Facades\Log;

class ContentDraftManagerService
{
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
     * Approve a draft.
     */
    public function approveDraft(ContentDraft $draft, ?string $notes = null): void
    {
        $draft->status = 'approved';
        $draft->reviewed_at = now();
        $draft->reviewed_by = auth()->id();
        $draft->save();

        // Update the associated action if it exists
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
     * Reject a draft.
     */
    public function rejectDraft(ContentDraft $draft, string $reason, ?string $notes = null): void
    {
        $draft->status = 'draft'; // Return to draft for revision
        $draft->metadata = array_merge($draft->metadata ?? [], [
            'rejected_at' => now()->toDateTimeString(),
            'rejection_reason' => $reason,
            'rejection_notes' => $notes,
        ]);
        $draft->save();

        Log::info('Draft rejected', [
            'draft_id' => $draft->id,
            'brand_id' => $draft->brand_id,
            'reason' => $reason,
        ]);
    }

    /**
     * Mark a draft as published.
     */
    public function markAsPublished(ContentDraft $draft, ?string $url = null): void
    {
        $draft->status = 'published';
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