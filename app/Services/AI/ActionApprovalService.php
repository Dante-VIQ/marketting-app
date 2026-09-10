<?php

namespace App\Services\AI;

use App\Jobs\ScanPageJob;
use App\Models\AiAction;
use App\Models\Brand;
use App\Models\GuardianAuditLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

class ActionApprovalService
{
  /**
     * Approve an action.
     *
     * @throws AuthorizationException
     */
    public function approve(AiAction $action, User $user, ?string $notes = null): void
    {
        // 🔒 Authorization check – enforced at the service boundary
        if (!Gate::forUser($user)->allows('approve', $action)) {
            Log::warning('Unauthorized action approval attempt', [
                'action_id' => $action->id,
                'user_id' => $user->id,
                'brand_id' => $action->brand_id,
            ]);
            throw new AuthorizationException('You are not authorized to approve this action.');
        }

        DB::transaction(function () use ($action, $user, $notes) {
            $action->status = 'approved';
            $action->approved_at = Carbon::now();
            $action->reviewed_at = Carbon::now();
            $action->reviewed_by = $user->id;
            $action->review_notes = $notes;
            $action->rejection_reason = null;
            $action->rejection_notes = null;
            $action->save();

            $this->updateBriefStatus($action);

            if ($action->target_url && $action->brand) {
                ScanPageJob::dispatch($action->brand, $action->target_url, $action);
            }

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
     *
     * @throws AuthorizationException
     */
    public function reject(AiAction $action, User $user, string $reason, ?string $notes = null): void
    {
        // 🔒 Authorization check
        if (!Gate::forUser($user)->allows('reject', $action)) {
            Log::warning('Unauthorized action rejection attempt', [
                'action_id' => $action->id,
                'user_id' => $user->id,
            ]);
            throw new AuthorizationException('You are not authorized to reject this action.');
        }

        $validReasons = [
            'too_short', 'tone_wrong', 'factually_incorrect',
            'off_brand', 'duplicate', 'low_priority', 'other'
        ];

        if (!in_array($reason, $validReasons)) {
            throw new \InvalidArgumentException(
                "Invalid rejection reason. Must be one of: " . implode(', ', $validReasons)
            );
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

            $this->updateBriefStatus($action);
            $this->logAction($action, $user, 'rejected', $reason . ': ' . ($notes ?? ''));

            Log::info('Action rejected', [
                'action_id' => $action->id,
                'brand_id' => $action->brand_id,
                'user_id' => $user->id,
                'reason' => $reason,
            ]);
        });
    }

    /**
     * Bulk approve multiple actions.
     *
     * @throws AuthorizationException
     */
    public function bulkApprove(array $actionIds, User $user): int
    {
        $count = 0;
        $denied = [];

        foreach ($actionIds as $actionId) {
            $action = AiAction::find($actionId);
            if (!$action || $action->status !== 'pending') {
                continue;
            }

            if (!Gate::forUser($user)->allows('approve', $action)) {
                $denied[] = $actionId;
                continue;
            }

            $this->approve($action, $user);
            $count++;
        }

        if (!empty($denied)) {
            Log::warning('Bulk approve: some actions denied', [
                'user_id' => $user->id,
                'denied_ids' => $denied,
                'approved_count' => $count,
            ]);
        }

        return $count;
    }

    /**
     * Bulk reject multiple actions.
     *
     * @throws AuthorizationException
     */
    public function bulkReject(array $actionIds, User $user, string $reason, ?string $notes = null): int
    {
        $count = 0;
        $denied = [];

        foreach ($actionIds as $actionId) {
            $action = AiAction::find($actionId);
            if (!$action || $action->status !== 'pending') {
                continue;
            }

            if (!Gate::forUser($user)->allows('reject', $action)) {
                $denied[] = $actionId;
                continue;
            }

            $this->reject($action, $user, $reason, $notes);
            $count++;
        }

        if (!empty($denied)) {
            Log::warning('Bulk reject: some actions denied', [
                'user_id' => $user->id,
                'denied_ids' => $denied,
                'rejected_count' => $count,
            ]);
        }

        return $count;
    }

    /**
     * Get pending actions for a brand (authorized).
     *
     * @throws AuthorizationException
     */
    public function getPendingActions(Brand $brand, User $user, int $limit = 50): array
    {
        if (!Gate::forUser($user)->allows('viewAny', AiAction::class)
            && !$user->belongsToBrand($brand->id)) {
            throw new AuthorizationException('Not authorized to view actions for this brand.');
        }

        return AiAction::where('brand_id', $brand->id)
            ->where('status', 'pending')
            ->orderBy('priority', 'desc')
            ->orderBy('created_at', 'asc')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    /**
     * Get reviewed actions for a brand (authorized).
     *
     * @throws AuthorizationException
     */
    public function getReviewedActions(Brand $brand, User $user, int $days = 7): array
    {
        if (!$user->belongsToBrand($brand->id) && !$user->hasRole('super-admin')) {
            throw new AuthorizationException('Not authorized to view actions for this brand.');
        }

        return AiAction::where('brand_id', $brand->id)
            ->whereIn('status', ['approved', 'rejected'])
            ->where('reviewed_at', '>=', Carbon::now()->subDays($days))
            ->orderBy('reviewed_at', 'desc')
            ->get()
            ->toArray();
    }

    /**
     * Get rejection statistics for a brand (authorized).
     *
     * @throws AuthorizationException
     */
    public function getRejectionStats(Brand $brand, User $user): array
    {
        if (!$user->belongsToBrand($brand->id) && !$user->hasRole('super-admin')) {
            throw new AuthorizationException('Not authorized to view stats for this brand.');
        }

        $rejected = AiAction::where('brand_id', $brand->id)
            ->where('status', 'rejected')
            ->get();

        $stats = [];
        foreach ($rejected as $action) {
            $reason = $action->rejection_reason ?? 'other';
            $stats[$reason] = ($stats[$reason] ?? 0) + 1;
        }

        return $stats;
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
}
