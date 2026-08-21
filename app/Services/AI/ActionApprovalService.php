<?php

namespace App\Services\AI;

use App\Models\AiAction;
use App\Models\Brand;
use App\Models\User;
use App\Models\GuardianAuditLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Jobs\ScanPageJob;

class ActionApprovalService
{
    /**
     * Approve an action.
     */
    public function approve(AiAction $action, User $user, ?string $notes = null): void
    {
        DB::transaction(function () use ($action, $user, $notes) {
            $action->status = 'approved';
            $action->approved_at = Carbon::now();
            $action->reviewed_at = Carbon::now();
            $action->reviewed_by = $user->id;
            $action->review_notes = $notes;
            $action->rejection_reason = null;
            $action->rejection_notes = null;
            $action->save();

            // Update the brief if all actions are approved
            $this->updateBriefStatus($action);

            // Scan the target URL if it exists
            if ($action->target_url && $action->brand) {
                ScanPageJob::dispatch($action->brand, $action->target_url, $action);
            }

            // Log to guardian
            $this->logAction($action, $user, 'approved', $notes);

            Log::info('Action approved', [
                'action_id' => $action->id,
                'brand_id' => $action->brand_id,
                'user_id' => $user->id,
                'title' => $action->title,
            ]);
        });
    }

    /**
     * Reject an action.
     */
    public function reject(AiAction $action, User $user, string $reason, ?string $notes = null): void
    {
        $validReasons = ['too_short', 'tone_wrong', 'factually_incorrect', 'off_brand', 'duplicate', 'low_priority', 'other'];
        
        if (!in_array($reason, $validReasons)) {
            throw new \InvalidArgumentException("Invalid rejection reason. Must be one of: " . implode(', ', $validReasons));
        }

        DB::transaction(function () use ($action, $user, $reason, $notes) {
            $action->status = 'rejected';
            $action->rejected_at = Carbon::now();
            $action->reviewed_at = Carbon::now();
            $action->reviewed_by = $user->id;
            $action->review_notes = $notes;
            $action->rejection_reason = $reason;
            $action->rejection_notes = $notes;
            $action->save();

            // Update the brief if all actions are reviewed
            $this->updateBriefStatus($action);

            // Log to guardian
            $this->logAction($action, $user, 'rejected', $reason . ': ' . ($notes ?? ''));

            Log::info('Action rejected', [
                'action_id' => $action->id,
                'brand_id' => $action->brand_id,
                'user_id' => $user->id,
                'reason' => $reason,
                'title' => $action->title,
            ]);
        });
    }

    /**
     * Bulk approve multiple actions.
     */
    public function bulkApprove(array $actionIds, User $user): int
    {
        $count = 0;
        foreach ($actionIds as $actionId) {
            $action = AiAction::find($actionId);
            if ($action && $action->status === 'pending') {
                $this->approve($action, $user);
                $count++;
            }
        }
        return $count;
    }

    /**
     * Bulk reject multiple actions.
     */
    public function bulkReject(array $actionIds, User $user, string $reason, ?string $notes = null): int
    {
        $count = 0;
        foreach ($actionIds as $actionId) {
            $action = AiAction::find($actionId);
            if ($action && $action->status === 'pending') {
                $this->reject($action, $user, $reason, $notes);
                $count++;
            }
        }
        return $count;
    }

    /**
     * Update brief status based on actions.
     */
    protected function updateBriefStatus(AiAction $action): void
    {
        $brief = $action->brief;
        if (!$brief) {
            return;
        }

        $pendingActions = $brief->actions()->where('status', 'pending')->count();
        $rejectedActions = $brief->actions()->where('status', 'rejected')->count();
        $approvedActions = $brief->actions()->where('status', 'approved')->count();
        $totalActions = $brief->actions()->count();

        // If all actions are reviewed, mark the brief as approved
        if ($pendingActions === 0) {
            if ($approvedActions > 0) {
                $brief->is_approved = true;
                $brief->approved_at = Carbon::now();
                $brief->save();
            }
        }

        // Log brief status
        Log::info('Brief status updated', [
            'brief_id' => $brief->id,
            'approved' => $approvedActions,
            'rejected' => $rejectedActions,
            'pending' => $pendingActions,
            'total' => $totalActions,
        ]);
    }

    /**
     * Log action to guardian.
     */
    protected function logAction(AiAction $action, User $user, string $eventType, ?string $notes = null): void
    {
        GuardianAuditLog::create([
            'brand_id' => $action->brand_id,
            'user_id' => $user->id,
            'fingerprint' => 'action_' . $action->id . '_' . time(),
            'event_type' => 'action_' . $eventType,
            'metadata' => [
                'action_id' => $action->id,
                'action_title' => $action->title,
                'category' => $action->category,
                'notes' => $notes,
            ],
        ]);
    }

    /**
     * Get pending actions for a brand.
     */
    public function getPendingActions(Brand $brand, int $limit = 50): array
    {
        return AiAction::where('brand_id', $brand->id)
            ->where('status', 'pending')
            ->orderBy('priority', 'desc')
            ->orderBy('created_at', 'asc')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    /**
     * Get reviewed actions for a brand.
     */
    public function getReviewedActions(Brand $brand, int $days = 7): array
    {
        return AiAction::where('brand_id', $brand->id)
            ->whereIn('status', ['approved', 'rejected'])
            ->where('reviewed_at', '>=', Carbon::now()->subDays($days))
            ->orderBy('reviewed_at', 'desc')
            ->get()
            ->toArray();
    }

    /**
     * Get rejection statistics for a brand.
     */
    public function getRejectionStats(Brand $brand): array
    {
        $rejected = AiAction::where('brand_id', $brand->id)
            ->where('status', 'rejected')
            ->get();

        $stats = [];
        foreach ($rejected as $action) {
            $reason = $action->rejection_reason ?? 'other';
            if (!isset($stats[$reason])) {
                $stats[$reason] = 0;
            }
            $stats[$reason]++;
        }

        return $stats;
    }
}
