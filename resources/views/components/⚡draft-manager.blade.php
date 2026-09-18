<?php

use Livewire\Component;
use App\Models\ContentDraft;
use App\Services\Content\ContentDraftManagerService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Auth\Access\AuthorizationException;

new class extends Component {
    public $brandId = null;
    public $drafts = [];
    public $filter = 'all';
    public $expandedDraftId = null;

    // Revision modal
    public $showRevisionModal = false;
    public $revisionDraftId = null;
    public $revisionReason = 'needs_more_detail';
    public $revisionNotes = '';

    protected $listeners = ['brand-switched' => 'loadDrafts'];

    public function mount()
    {
        $this->brandId = Auth::user()->active_brand_id;
        $this->loadDrafts();
    }

    public function loadDrafts()
    {
        if (!$this->brandId) {
            $this->drafts = [];
            return;
        }

        $query = ContentDraft::where('brand_id', $this->brandId)
            ->with('action');

        if ($this->filter !== 'all') {
            $query->where('status', $this->filter);
        }

        $drafts = $query->orderBy('created_at', 'desc')->get();
        $user = Auth::user();

        $this->drafts = $drafts->map(function ($draft) use ($user) {
            $data = $draft->toArray();

            if ($draft->action) {
                $data['action'] = [
                    'id'               => $draft->action->id,
                    'title'            => $draft->action->title,
                    'category'         => $draft->action->category,
                    'priority'         => $draft->action->priority,
                    'status'           => $draft->action->status,
                    'estimated_impact' => $draft->action->estimated_impact,
                    'target_url'       => $draft->action->target_url,
                    'description'      => $draft->action->description,
                    'created_at'       => $draft->action->created_at->toDateTimeString(),
                ];
            }

            // Render markdown safely — for SEO meta drafts, content is
            // often just the meta description itself, so markdown still works.
            $data['content_html'] = Str::markdown($draft->content ?? '', [
                'html_input'         => 'strip',
                'allow_unsafe_links' => false,
            ]);

            $data['type_label']             = $draft->type_label;
            $data['source_category']        = $draft->source_category;
            $data['source_description']     = $draft->source_description;
            $data['word_count']             = $draft->word_count;
            $data['meta_title_length']      = strlen($draft->meta_title ?? '');
            $data['meta_description_length'] = strlen($draft->meta_description ?? '');
            $data['status_badge']           = $draft->status_badge;
            $data['status_label']           = $draft->status_label;
            $data['can_publish']            = $user->can('publish', $draft);

            return $data;
        })->toArray();
    }

    public function submitForReview($draftId, ContentDraftManagerService $draftManager)
    {
        $draft = ContentDraft::findOrFail($draftId);

        if (!Auth::user()->can('update', $draft)) {
            session()->flash('error', 'You are not authorized to submit this draft.');
            return;
        }

        $draftManager->submitForReview($draft);
        $this->loadDrafts();
        session()->flash('message', 'Draft submitted for review.');
    }

    public function approveDraft($draftId, ContentDraftManagerService $draftManager)
    {
        $draft = ContentDraft::findOrFail($draftId);

        if (!Auth::user()->can('update', $draft)) {
            session()->flash('error', 'You are not authorized to approve this draft.');
            return;
        }

        // Service handles updating the linked AiAction — no need to duplicate
        $draftManager->approveDraft($draft);
        $this->loadDrafts();
        session()->flash('message', 'Draft approved successfully.');
    }

    public function openRevisionModal($draftId)
    {
        $this->revisionDraftId = $draftId;
        $this->revisionReason = 'needs_more_detail';
        $this->revisionNotes = '';
        $this->showRevisionModal = true;
    }

    public function requestRevision(ContentDraftManagerService $draftManager)
    {
        if (!$this->revisionDraftId) {
            session()->flash('error', 'No draft selected for revision.');
            return;
        }

        $draft = ContentDraft::findOrFail($this->revisionDraftId);

        if (!Auth::user()->can('update', $draft)) {
            session()->flash('error', 'You are not authorized to request revisions.');
            return;
        }

        $notes = $this->revisionNotes ?: $this->revisionReason;
        $draftManager->requestRevision($draft, $this->revisionReason, $notes);

        $this->showRevisionModal = false;
        $this->revisionDraftId = null;
        $this->loadDrafts();
        session()->flash('message', 'Draft sent for revision. The AI will regenerate with your feedback.');
    }

    public function regenerateDraft($draftId, ContentDraftManagerService $draftManager)
    {
        $draft = ContentDraft::findOrFail($draftId);

        if (!Auth::user()->can('update', $draft)) {
            session()->flash('error', 'You are not authorized to regenerate this draft.');
            return;
        }

        if (!$draft->needsRevision()) {
            session()->flash('error', 'This draft does not need revision.');
            return;
        }

        $newDraft = $draftManager->regenerateDraft($draft);

        if ($newDraft) {
            $this->loadDrafts();
            session()->flash('message', 'Draft regenerated with your feedback. Please review again.');
        } else {
            session()->flash('error', 'Failed to regenerate draft. Please try again.');
        }
    }

    public function markPublished($draftId, ContentDraftManagerService $draftManager)
    {
        $draft = ContentDraft::findOrFail($draftId);

        // Uses the ContentDraftPolicy::publish method
        if (!Auth::user()->can('publish', $draft)) {
            session()->flash('error', 'You are not authorized to publish this content.');
            return;
        }

        $draftManager->markAsPublished($draft);
        $this->loadDrafts();
        session()->flash('message', '✅ Content published.');
    }

    public function toggleExpand($draftId)
    {
        $this->expandedDraftId = ($this->expandedDraftId === $draftId) ? null : $draftId;
    }

    public function setFilter($filter)
    {
        $this->filter = $filter;
        $this->loadDrafts();
    }
};
?>

<div>
    {{-- Flash messages --}}
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

    {{-- Header + filters --}}
    <div class="flex flex-wrap items-center justify-between gap-4 mb-4">
        <div class="flex items-center space-x-2">
            <h2 class="text-lg font-semibold text-gray-900">Content Drafts</h2>
            <span class="px-2 py-1 text-xs rounded-full bg-blue-100 text-blue-800">
                {{ count($drafts) }} items
            </span>
        </div>

        <div class="flex space-x-1 flex-wrap">
            @foreach([
                'all'       => ['All',         'bg-gray-600'],
                'draft'     => ['Drafts',      'bg-yellow-600'],
                'review'    => ['In Review',   'bg-blue-600'],
                'revision'  => ['Revision',    'bg-orange-600'],
                'approved'  => ['Approved',    'bg-green-600'],
                'published' => ['Published',   'bg-purple-600'],
            ] as $key => [$label, $activeColor])
                <button wire:click="setFilter('{{ $key }}')"
                    class="px-3 py-1 text-sm rounded transition
                        {{ $filter === $key ? $activeColor . ' text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' }}">
                    {{ $label }}
                </button>
            @endforeach
        </div>
    </div>

    {{-- Empty state --}}
    @if(empty($drafts))
        <div class="bg-gray-50 p-8 rounded-lg border border-gray-200 text-center">
            <p class="text-gray-500">No drafts found.</p>
            <p class="text-sm text-gray-400 mt-1">Content will appear here once approved actions are processed.</p>
        </div>
    @else
        <div class="space-y-4">
            @foreach($drafts as $draft)
                <div class="bg-white p-4 rounded-lg border border-gray-200 hover:shadow-md transition">

                    {{-- Source action --}}
                    @if(!empty($draft['action']))
                        <div class="mb-3 p-3 bg-gray-50 rounded-lg border border-gray-200">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="text-xs font-medium text-gray-500">📌 From Action:</span>
                                <span class="text-sm font-medium text-gray-900">{{ $draft['action']['title'] }}</span>
                            </div>
                            <div class="flex flex-wrap items-center gap-2 mt-1">
                                <span class="px-2 py-0.5 text-xs rounded-full
                                    {{ $draft['action']['category'] === 'seo'     ? 'bg-blue-100 text-blue-800' : '' }}
                                    {{ $draft['action']['category'] === 'content' ? 'bg-green-100 text-green-800' : '' }}
                                    {{ $draft['action']['category'] === 'social'  ? 'bg-purple-100 text-purple-800' : '' }}">
                                    {{ $draft['action']['category'] ?? 'Unknown' }}
                                </span>
                                <span class="px-2 py-0.5 text-xs rounded-full bg-gray-100 text-gray-600">
                                    Priority: {{ $draft['action']['priority'] ?? 0 }}/5
                                </span>
                                @if(!empty($draft['action']['estimated_impact']))
                                    <span class="px-2 py-0.5 text-xs rounded-full bg-green-100 text-green-800">
                                        Impact: ${{ number_format($draft['action']['estimated_impact'], 2) }}
                                    </span>
                                @endif
                                <span class="px-2 py-0.5 text-xs rounded-full
                                    {{ $draft['action']['status'] === 'pending'          ? 'bg-yellow-100 text-yellow-800' : '' }}
                                    {{ $draft['action']['status'] === 'approved'         ? 'bg-blue-100 text-blue-800' : '' }}
                                    {{ $draft['action']['status'] === 'content_generated' ? 'bg-green-100 text-green-800' : '' }}">
                                    Action: {{ ucfirst($draft['action']['status'] ?? 'Unknown') }}
                                </span>
                            </div>
                            @if(!empty($draft['action']['target_url']))
                                <div class="mt-1 text-xs text-gray-500">
                                    Target: {{ $draft['action']['target_url'] }}
                                </div>
                            @endif
                        </div>
                    @endif

                    {{-- Draft header with copy buttons --}}
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex-1 min-w-0">
                            {{-- Badges --}}
                            <div class="flex flex-wrap items-center gap-2 mb-3">
                                <span class="px-2 py-1 text-xs rounded-full
                                    {{ $draft['type'] === 'blog'     ? 'bg-green-100 text-green-800' : '' }}
                                    {{ $draft['type'] === 'social'   ? 'bg-purple-100 text-purple-800' : '' }}
                                    {{ $draft['type'] === 'email'    ? 'bg-yellow-100 text-yellow-800' : '' }}
                                    {{ $draft['type'] === 'web_copy' ? 'bg-blue-100 text-blue-800' : '' }}
                                    {{ $draft['type'] === 'seo_meta' ? 'bg-indigo-100 text-indigo-800' : '' }}">
                                    {{ $draft['type_label'] ?? ucfirst($draft['type']) }}
                                </span>

                                <span class="px-2 py-1 text-xs rounded-full {{ $draft['status_badge'] ?? 'bg-gray-100 text-gray-800' }}">
                                    {{ $draft['status_label'] ?? ucfirst($draft['status']) }}
                                </span>

                                @if(!empty($draft['target_keyword']))
                                    <span class="px-2 py-1 text-xs rounded-full bg-gray-100 text-gray-600">
                                        🔑 {{ $draft['target_keyword'] }}
                                    </span>
                                @endif
                            </div>

                            {{-- Title with copy + word count --}}
                            <div class="flex items-center gap-2 flex-wrap">
                                <h3 class="font-medium text-gray-900">{{ $draft['title'] }}</h3>
                                @if(($draft['word_count'] ?? 0) > 0)
                                    <span class="text-xs text-gray-400">
                                        · {{ number_format($draft['word_count']) }} words
                                    </span>
                                @endif
                                <button type="button"
                                    x-data="{ copied: false }"
                                    @click="
                                        navigator.clipboard.writeText(@js($draft['title']));
                                        copied = true;
                                        setTimeout(() => copied = false, 1500);
                                    "
                                    class="px-2 py-0.5 text-xs bg-gray-100 hover:bg-gray-200 rounded transition"
                                    title="Copy title">
                                    <span x-show="!copied">📋</span>
                                    <span x-show="copied" class="text-green-600">✓</span>
                                </button>
                            </div>

                            {{-- SEO meta inline length checks --}}
                            @if($draft['type'] === 'seo_meta')
                                <div class="mt-2 space-y-1">
                                    @if(!empty($draft['meta_title']))
                                        @php
                                            $titleLength = $draft['meta_title_length'];
                                            $titleValid = $titleLength >= 50 && $titleLength <= 60;
                                        @endphp
                                        <div class="text-xs {{ $titleValid ? 'text-green-600' : 'text-red-500' }}">
                                            Title: {{ $draft['meta_title'] }} ({{ $titleLength }}/50-60 chars)
                                            {{ $titleValid ? '✅' : '⚠️' }}
                                        </div>
                                    @endif
                                    @if(!empty($draft['meta_description']))
                                        @php
                                            $descLength = $draft['meta_description_length'];
                                            $descValid = $descLength >= 140 && $descLength <= 160;
                                        @endphp
                                        <div class="text-xs {{ $descValid ? 'text-green-600' : 'text-red-500' }}">
                                            Description: {{ $draft['meta_description'] }} ({{ $descLength }}/140-160 chars)
                                            {{ $descValid ? '✅' : '⚠️' }}
                                        </div>
                                    @endif
                                </div>
                            @endif

                            @if(!empty($draft['excerpt']))
                                <p class="text-sm text-gray-600 mt-2">{{ $draft['excerpt'] }}</p>
                            @endif

                            {{-- Expand toggle --}}
                            <button wire:click="toggleExpand({{ $draft['id'] }})"
                                class="text-sm text-blue-600 hover:text-blue-800 mt-2">
                                {{ $expandedDraftId === $draft['id'] ? '📄 Hide Content' : '📄 View Full Content' }}
                            </button>

                            {{-- Expanded content --}}
                            @if($expandedDraftId === $draft['id'])
                                <div class="mt-3 rounded-lg border border-gray-200 overflow-hidden">

                                    {{-- Copy bar --}}
                                    <div class="flex items-center justify-between px-4 py-2 bg-gray-100 border-b border-gray-200">
                                        <span class="text-xs font-medium text-gray-600">
                                            {{ number_format($draft['word_count'] ?? 0) }} words
                                            @if($draft['type'] === 'seo_meta') · SEO Meta @endif
                                        </span>
                                        <div class="flex gap-2 flex-wrap">
                                            <button type="button"
                                                x-data="{ copied: false }"
                                                @click="
                                                    navigator.clipboard.writeText(@js($draft['content']));
                                                    copied = true;
                                                    setTimeout(() => copied = false, 1500);
                                                "
                                                class="px-3 py-1 text-xs bg-white border border-gray-300 hover:bg-gray-50 rounded transition">
                                                <span x-show="!copied">📋 Copy Content</span>
                                                <span x-show="copied" class="text-green-600">✓ Copied</span>
                                            </button>

                                            @if(!empty($draft['meta_title']))
                                                <button type="button"
                                                    x-data="{ copied: false }"
                                                    @click="
                                                        navigator.clipboard.writeText(@js($draft['meta_title']));
                                                        copied = true;
                                                        setTimeout(() => copied = false, 1500);
                                                    "
                                                    class="px-3 py-1 text-xs bg-white border border-gray-300 hover:bg-gray-50 rounded transition">
                                                    <span x-show="!copied">📋 Meta Title</span>
                                                    <span x-show="copied" class="text-green-600">✓</span>
                                                </button>
                                            @endif

                                            @if(!empty($draft['meta_description']))
                                                <button type="button"
                                                    x-data="{ copied: false }"
                                                    @click="
                                                        navigator.clipboard.writeText(@js($draft['meta_description']));
                                                        copied = true;
                                                        setTimeout(() => copied = false, 1500);
                                                    "
                                                    class="px-3 py-1 text-xs bg-white border border-gray-300 hover:bg-gray-50 rounded transition">
                                                    <span x-show="!copied">📋 Meta Desc</span>
                                                    <span x-show="copied" class="text-green-600">✓</span>
                                                </button>
                                            @endif
                                        </div>
                                    </div>

                                    {{-- Rendered markdown content --}}
                                    <div class="p-4 bg-gray-50 max-h-[32rem] overflow-y-auto">
                                        <div class="prose prose-sm max-w-none text-gray-700">
                                            {!! $draft['content_html'] !!}
                                        </div>
                                    </div>

                                    {{-- SEO block --}}
                                    @if(!empty($draft['meta_title']) || !empty($draft['meta_description']))
                                        <div class="p-3 bg-blue-50 border-t border-blue-200">
                                            <p class="text-xs font-medium text-blue-800 mb-2">🔍 SEO Information</p>
                                            @if(!empty($draft['meta_title']))
                                                <p class="text-sm text-blue-700 mb-1">
                                                    <strong>Title:</strong> {{ $draft['meta_title'] }}
                                                    <span class="text-xs text-gray-500">({{ $draft['meta_title_length'] }}/50-60 chars)</span>
                                                </p>
                                            @endif
                                            @if(!empty($draft['meta_description']))
                                                <p class="text-sm text-blue-700">
                                                    <strong>Description:</strong> {{ $draft['meta_description'] }}
                                                    <span class="text-xs text-gray-500">({{ $draft['meta_description_length'] }}/140-160 chars)</span>
                                                </p>
                                            @endif
                                        </div>
                                    @endif

                                    {{-- SEO data stats --}}
                                    @if(!empty($draft['seo_data']))
                                        <div class="p-3 bg-gray-50 border-t border-gray-200 grid grid-cols-2 gap-2">
                                            @if(isset($draft['seo_data']['word_count']))
                                                <div class="p-2 bg-green-50 rounded">
                                                    <p class="text-xs text-gray-500">Word Count</p>
                                                    <p class="text-sm font-medium">{{ $draft['seo_data']['word_count'] }}</p>
                                                </div>
                                            @endif
                                            @if(isset($draft['seo_data']['readability_score']))
                                                <div class="p-2 bg-blue-50 rounded">
                                                    <p class="text-xs text-gray-500">Readability</p>
                                                    <p class="text-sm font-medium">{{ $draft['seo_data']['readability_score'] }}%</p>
                                                </div>
                                            @endif
                                        </div>
                                    @endif
                                </div>
                            @endif
                        </div>

                        {{-- Action buttons column --}}
                        <div class="flex flex-col space-y-2 ml-4 flex-shrink-0">
                            @if($draft['status'] === 'draft')
                                <button wire:click="submitForReview({{ $draft['id'] }})"
                                    wire:confirm="Are you ready to submit this draft for review?"
                                    class="px-4 py-2 text-sm bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                                    📤 Submit for Review
                                </button>
                                <button wire:click="openRevisionModal({{ $draft['id'] }})"
                                    class="px-4 py-2 text-sm bg-yellow-600 text-white rounded-lg hover:bg-yellow-700 transition">
                                    🔄 Request Revisions
                                </button>
                            @endif

                            @if($draft['status'] === 'review')
                                <button wire:click="approveDraft({{ $draft['id'] }})"
                                    wire:confirm="Approve this draft?"
                                    class="px-4 py-2 text-sm bg-green-600 text-white rounded-lg hover:bg-green-700 transition">
                                    ✅ Approve
                                </button>
                                <button wire:click="openRevisionModal({{ $draft['id'] }})"
                                    class="px-4 py-2 text-sm bg-yellow-600 text-white rounded-lg hover:bg-yellow-700 transition">
                                    🔄 Request Revisions
                                </button>
                            @endif

                            @if($draft['status'] === 'revision')
                                <button wire:click="regenerateDraft({{ $draft['id'] }})"
                                    wire:confirm="Regenerate this draft with your feedback?"
                                    class="px-4 py-2 text-sm bg-orange-600 text-white rounded-lg hover:bg-orange-700 transition">
                                    🔄 Regenerate with Feedback
                                </button>
                            @endif

                            @if($draft['status'] === 'approved' && ($draft['can_publish'] ?? false))
                                <button wire:click="markPublished({{ $draft['id'] }})"
                                    wire:confirm="Have you published this content?"
                                    class="px-4 py-2 text-sm bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition">
                                    🚀 Mark Published
                                </button>
                            @endif

                            @if($draft['status'] === 'published')
                                <span class="px-3 py-2 text-sm bg-gray-100 text-gray-600 rounded-lg text-center">
                                    ✅ Published
                                </span>
                                @if(!empty($draft['published_url']))
                                    <a href="{{ $draft['published_url'] }}" target="_blank" rel="noopener"
                                        class="px-3 py-2 text-sm bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-center">
                                        🔗 View
                                    </a>
                                @endif
                            @endif

                            @if(!empty($draft['action']))
                                <a href="{{ route('actions.queue') }}"
                                    class="px-3 py-1 text-xs text-center bg-gray-100 text-gray-600 rounded hover:bg-gray-200 transition">
                                    View in Action Queue →
                                </a>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    {{-- Revision modal --}}
    @if($showRevisionModal)
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 flex items-center justify-center z-50">
            <div class="bg-white rounded-lg shadow-xl max-w-md w-full p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">🔄 Request Revisions</h3>
                <p class="text-sm text-gray-600 mb-4">
                    Provide feedback on what needs to be improved in this draft.
                </p>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Revision Reason</label>
                    <select wire:model="revisionReason"
                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-yellow-500 focus:ring-yellow-500">
                        <option value="needs_more_detail">Needs More Detail</option>
                        <option value="tone_wrong">Tone is Wrong</option>
                        <option value="factually_incorrect">Factually Incorrect</option>
                        <option value="off_brand">Off Brand Voice</option>
                        <option value="structure_bad">Bad Structure</option>
                        <option value="other">Other</option>
                    </select>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Additional Notes</label>
                    <textarea wire:model="revisionNotes" rows="3"
                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-yellow-500 focus:ring-yellow-500"
                        placeholder="What specifically needs to be improved?"></textarea>
                </div>

                <div class="flex justify-end space-x-3">
                    <button wire:click="$set('showRevisionModal', false)"
                        class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition">
                        Cancel
                    </button>
                    <button wire:click="requestRevision"
                        class="px-4 py-2 bg-yellow-600 text-white rounded-lg hover:bg-yellow-700 transition">
                        Send for Revision
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>