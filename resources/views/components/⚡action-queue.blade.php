<?php

use Livewire\Component;
use App\Models\AiAction;
use App\Models\Brand;
use App\Services\AI\ActionApprovalService;
use Illuminate\Support\Facades\Auth;

new class extends Component
{
    public $brandId = null;
    public $actions = [];
    public $filter = 'pending';
    public $selectedActions = [];
    public $selectAll = false;
    public $rejectionReason = 'other';
    public $rejectionNotes = '';
    public $showBulkRejectModal = false;
    public $expandedActionId = null;

    public $rejectionReasons = [];

    protected $listeners = ['brand-switched' => 'loadActions', 'action-updated' => 'loadActions'];

    public function mount()
    {
        $this->brandId = Auth::user()->active_brand_id;
        $this->rejectionReasons = [
            'too_short' => 'Too Short / Needs More Depth',
            'tone_wrong' => 'Tone is Wrong (Too Salesy / Too Formal)',
            'factually_incorrect' => 'Factually Incorrect / Hallucinated',
            'off_brand' => 'Off-Brand / Irrelevant Topic',
            'duplicate' => 'Duplicate / Already Published',
            'low_priority' => 'Low Priority / Not Urgent',
            'other' => 'Other (Please specify)',
        ];
        $this->loadActions();
    }

    public function loadActions()
    {
        if (!$this->brandId) {
            $this->actions = [];
            return;
        }

        $query = AiAction::where('brand_id', $this->brandId);

        if ($this->filter === 'pending') {
            $query->where('status', 'pending');
        } elseif ($this->filter === 'approved') {
            $query->where('status', 'approved');
        } elseif ($this->filter === 'rejected') {
            $query->where('status', 'rejected');
        } elseif ($this->filter === 'content_generated') {
            $query->where('status', 'content_generated');
        }

        $this->actions = $query->orderBy('priority', 'desc')
            ->orderBy('created_at', 'asc')
            ->get()
            ->toArray();

        $this->selectedActions = [];
        $this->selectAll = false;
    }

    public function approveAction($actionId, ActionApprovalService $approvalService)
    {
        $user = Auth::user();
        $action = AiAction::findOrFail($actionId);
        $approvalService->approve($action, $user);
        $this->loadActions();
        $this->dispatch('action-updated');
        session()->flash('message', 'Action approved successfully.');
    }

    public function rejectAction($actionId, $reason, ActionApprovalService $approvalService)
    {
        $user = Auth::user();
        $action = AiAction::findOrFail($actionId);
        $approvalService->reject($action, $user, $reason);
        $this->loadActions();
        $this->dispatch('action-updated');
        session()->flash('message', 'Action rejected successfully.');
    }

    public function toggleExpand($actionId)
    {
        $this->expandedActionId = ($this->expandedActionId === $actionId) ? null : $actionId;
    }
    

    public function toggleSelect($actionId)
    {
        if (in_array($actionId, $this->selectedActions)) {
            $this->selectedActions = array_diff($this->selectedActions, [$actionId]);
        } else {
            $this->selectedActions[] = $actionId;
        }
        $this->updateSelectAll();
    }


    public function toggleSelectAll()
    {
        $this->selectAll = !$this->selectAll;
        if ($this->selectAll) {
            $this->selectedActions = array_column($this->actions, 'id');
        } else {
            $this->selectedActions = [];
        }
    }

    protected function updateSelectAll()
    {
        $allIds = array_column($this->actions, 'id');
        $this->selectAll = count($this->selectedActions) === count($allIds) && count($allIds) > 0;
    }

    public function bulkApprove(ActionApprovalService $approvalService)
    {
        if (empty($this->selectedActions)) {
            session()->flash('error', 'No actions selected.');
            return;
        }

        $user = Auth::user();
        $count = $approvalService->bulkApprove($this->selectedActions, $user);
        $this->loadActions();
        session()->flash('message', "{$count} actions approved successfully.");
    }

    public function bulkReject(ActionApprovalService $approvalService)
    {
        if (empty($this->selectedActions)) {
            session()->flash('error', 'No actions selected.');
            return;
        }

        $user = Auth::user();
        $count = $approvalService->bulkReject(
            $this->selectedActions,
            $user,
            $this->rejectionReason,
            $this->rejectionNotes
        );

        $this->showBulkRejectModal = false;
        $this->rejectionReason = 'other';
        $this->rejectionNotes = '';
        $this->loadActions();
        session()->flash('message', "{$count} actions rejected successfully.");
    }

    public function setFilter($filter)
    {
        $this->filter = $filter;
        $this->loadActions();
    }
};
?>

<div>
    <!-- Flash Messages -->
    @if(session()->has('message'))
        <div class="mb-4 p-4 bg-green-100 border border-green-400 text-green-700 rounded-lg">
            {{ session('message') }}
        </div>
    @endif

    @if(session()->has('error'))
        <div class="mb-4 p-4 bg-red-100 border border-red-400 text-red-700 rounded-lg">
            {{ session('error') }}
        </div>
    @endif

    <!-- Header -->
    <div class="flex flex-wrap items-center justify-between gap-4 mb-4">
        <div class="flex items-center space-x-2">
            <h2 class="text-lg font-semibold text-gray-900">Action Queue</h2>
            <span class="px-2 py-1 text-xs rounded-full bg-blue-100 text-blue-800">
                {{ count($actions) }} items
            </span>
        </div>
        
        <div class="flex flex-wrap items-center gap-2">
            <!-- Filter Buttons -->
            <div class="flex space-x-1">
                <button wire:click="setFilter('pending')" 
                        class="px-3 py-1 text-sm rounded {{ $filter === 'pending' ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' }}">
                    Pending
                </button>
                <button wire:click="setFilter('approved')" 
                        class="px-3 py-1 text-sm rounded {{ $filter === 'approved' ? 'bg-green-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' }}">
                    Approved
                </button>
                <button wire:click="setFilter('rejected')" 
                        class="px-3 py-1 text-sm rounded {{ $filter === 'rejected' ? 'bg-red-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' }}">
                    Rejected
                </button>
            </div>

            <!-- Bulk Actions -->
            @if($filter === 'pending' && !empty($actions))
                <div class="flex space-x-1">
                    <button wire:click="bulkApprove" 
                            class="px-3 py-1 text-sm bg-green-600 text-white rounded hover:bg-green-700"
                            wire:confirm="Are you sure you want to approve {{ count($selectedActions) }} selected actions?">
                        Approve Selected
                    </button>
                    <button wire:click="$set('showBulkRejectModal', true)" 
                            class="px-3 py-1 text-sm bg-red-600 text-white rounded hover:bg-red-700">
                        Reject Selected
                    </button>
                </div>
            @endif
        </div>
    </div>

    @if(empty($actions))
        <div class="bg-gray-50 p-8 rounded-lg border border-gray-200 text-center">
            <p class="text-gray-500">No {{ $filter }} actions found.</p>
            @if($filter === 'pending')
                <p class="text-sm text-gray-400 mt-1">All caught up! Check back later for new actions.</p>
            @endif
        </div>
    @else
        <!-- Action Cards -->
        <div class="space-y-3">
            @foreach($actions as $action)
                <div class="bg-white p-4 rounded-lg border border-gray-200 hover:shadow-md transition">
                    <div class="flex items-start gap-4">
                        <!-- Checkbox (only for pending) -->
                        @if($filter === 'pending')
                            <div class="pt-1">
                                <input type="checkbox" 
                                       wire:click="toggleSelect({{ $action['id'] }})"
                                       {{ in_array($action['id'], $selectedActions) ? 'checked' : '' }}
                                       class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                            </div>
                        @endif

                        <!-- Content -->
                        <div class="flex-1">
                            <div class="flex flex-wrap items-center gap-2 mb-1">
                                <span class="px-2 py-1 text-xs rounded-full 
                                    {{ $action['category'] === 'seo' ? 'bg-blue-100 text-blue-800' : '' }}
                                    {{ $action['category'] === 'content' ? 'bg-green-100 text-green-800' : '' }}
                                    {{ $action['category'] === 'social' ? 'bg-purple-100 text-purple-800' : '' }}
                                    {{ $action['category'] === 'email' ? 'bg-yellow-100 text-yellow-800' : '' }}
                                    {{ $action['category'] === 'campaign' ? 'bg-red-100 text-red-800' : '' }}
                                    {{ $action['category'] === 'strategy' ? 'bg-indigo-100 text-indigo-800' : '' }}
                                    {{ $action['category'] === 'web_copy' ? 'bg-teal-100 text-teal-800' : '' }}
                                    {{ $action['category'] === 'analytics' ? 'bg-gray-100 text-gray-800' : '' }}
                                ">
                                    {{ ucfirst($action['category']) }}
                                </span>
                                <span class="text-xs text-gray-500">Priority: {{ $action['priority'] }}/5</span>
                                @if($action['status'] === 'approved')
                                    <span class="px-2 py-1 text-xs rounded-full bg-green-100 text-green-800">✅ Approved</span>
                                @elseif($action['status'] === 'rejected')
                                    <span class="px-2 py-1 text-xs rounded-full bg-red-100 text-red-800">❌ Rejected</span>
                                @else
                                    <span class="px-2 py-1 text-xs rounded-full bg-yellow-100 text-yellow-800">⏳ Pending</span>
                                @endif
                            </div>

                            <h3 class="font-medium text-gray-900">{{ $action['title'] }}</h3>
                            <p class="text-sm text-gray-600 mt-1">{{ $action['description'] }}</p>
                            
                            @if($action['suggested_content'])
                                <div class="mt-2 p-3 bg-gray-50 rounded-lg text-sm text-gray-700 border border-gray-100">
                                    <strong>Suggested:</strong> {{ $action['suggested_content'] }}
                                </div>
                            @endif

                            @if($action['content_draft'])
                                <div class="mt-2">
                                    <button wire:click="$set('expandedActionId', {{ $action['id'] }})" 
                                            class="text-sm text-blue-600 hover:text-blue-800">
                                        📄 View Full Draft
                                    </button>
                                </div>
                            @endif

                            @if($action['estimated_impact'])
                                <div class="mt-1 text-sm text-green-600">
                                    💰 Estimated impact: ${{ number_format($action['estimated_impact'], 2) }}
                                </div>
                            @endif

                            @if($action['rejection_reason'] && $action['status'] === 'rejected')
                                <div class="mt-2 p-2 bg-red-50 rounded-lg border border-red-200">
                                    <p class="text-sm text-red-700">
                                        <strong>Rejected:</strong> {{ ucfirst(str_replace('_', ' ', $action['rejection_reason'])) }}
                                        @if($action['rejection_notes'])
                                            <br><span class="text-xs text-red-600">Reason: {{ $action['rejection_notes'] }}</span>
                                        @endif
                                    </p>
                                </div>
                            @endif

                            @if($action['review_notes'] && $action['status'] === 'approved')
                                <div class="mt-2 p-2 bg-green-50 rounded-lg border border-green-200">
                                    <p class="text-sm text-green-700">
                                        <strong>Review Note:</strong> {{ $action['review_notes'] }}
                                    </p>
                                </div>
                            @endif
                        </div>

                        <!-- Action Buttons -->
                        @if($action['status'] === 'pending')
                            <div class="flex flex-col space-y-2 ml-4">
                                <button wire:click="approveAction({{ $action['id'] }})" 
                                        wire:confirm="Are you sure you want to approve this action?"
                                        class="px-4 py-2 text-sm bg-green-600 text-white rounded-lg hover:bg-green-700 transition">
                                    ✅ Approve
                                </button>
                                <button wire:click="rejectAction({{ $action['id'] }}, 'other')" 
                                        class="px-4 py-2 text-sm bg-red-600 text-white rounded-lg hover:bg-red-700 transition">
                                    ❌ Reject
                                </button>
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    <!-- Bulk Reject Modal -->
    @if($showBulkRejectModal)
    <div class="fixed inset-0 bg-gray-500 bg-opacity-75 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg shadow-xl max-w-md w-full p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Bulk Reject Actions</h3>
            <p class="text-sm text-gray-600 mb-4">
                You are about to reject {{ count($selectedActions) }} actions. Please select a reason.
            </p>

            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Rejection Reason</label>
                    <select wire:model="rejectionReason" 
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-red-500 focus:ring-red-500">
                        @foreach($rejectionReasons as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Additional Notes (Optional)</label>
                    <textarea wire:model="rejectionNotes" rows="3"
                              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-red-500 focus:ring-red-500"
                              placeholder="Add any additional context..."></textarea>
                </div>
            </div>

            <div class="mt-6 flex justify-end space-x-3">
                <button wire:click="$set('showBulkRejectModal', false)" 
                        class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">
                    Cancel
                </button>
                <button wire:click="bulkReject" 
                        class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition">
                    Reject All
                </button>
            </div>
        </div>
    </div>
    @endif
</div>