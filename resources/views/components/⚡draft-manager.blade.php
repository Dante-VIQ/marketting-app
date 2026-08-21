<?php

use Livewire\Component;
use App\Models\ContentDraft;
use App\Models\Brand;
use App\Services\Content\ContentDraftManagerService;
use Illuminate\Support\Facades\Auth;

new class extends Component
{
     public $brandId = null;
    public $drafts = [];
    public $filter = 'draft';
    public $expandedDraftId = null;

    public $statusLabels = [];

    protected $listeners = ['brand-switched' => 'loadDrafts'];

    public function mount()
    {
        $this->brandId = Auth::user()->active_brand_id;
        $this->statusLabels = [
            'draft' => '📄 Draft',
            'review' => '👀 In Review',
            'approved' => '✅ Approved',
            'published' => '🚀 Published',
        ];
        $this->loadDrafts();
    }

    public function loadDrafts()
    {
        if (!$this->brandId) {
            $this->drafts = [];
            return;
        }

        $query = ContentDraft::where('brand_id', $this->brandId)
            ->with('action'); // Eager load the action relationship

        if ($this->filter !== 'all') {
            $query->where('status', $this->filter);
        }

        $drafts = $query->orderBy('created_at', 'desc')->get();

        // Transform drafts to include action data
        $this->drafts = $drafts->map(function ($draft) {
            $data = $draft->toArray();
            
            // Add action data if it exists
            if ($draft->action) {
                $data['action'] = [
                    'id' => $draft->action->id,
                    'title' => $draft->action->title,
                    'category' => $draft->action->category,
                    'priority' => $draft->action->priority,
                    'status' => $draft->action->status,
                    'estimated_impact' => $draft->action->estimated_impact,
                    'target_url' => $draft->action->target_url,
                    'description' => $draft->action->description,
                    'created_at' => $draft->action->created_at->toDateTimeString(),
                ];
            }
            
            // Add computed fields
            $data['type_label'] = $draft->type_label;
            $data['source_category'] = $draft->source_category;
            $data['source_description'] = $draft->source_description;
            $data['word_count'] = $draft->word_count;
            $data['meta_title_length'] = strlen($draft->meta_title ?? '');
            $data['meta_description_length'] = strlen($draft->meta_description ?? '');
            
            return $data;
        })->toArray();
    }

    public function approveDraft($draftId, ContentDraftManagerService $draftManager)
    {
        $draft = ContentDraft::findOrFail($draftId);
        $draftManager->approveDraft($draft);
        $this->loadDrafts();
        
        // Update the associated action status
        if ($draft->action) {
            $draft->action->status = 'published';
            $draft->action->executed_at = now();
            $draft->action->save();
        }
        
        session()->flash('message', 'Draft approved successfully.');
    }

    public function rejectDraft($draftId, ContentDraftManagerService $draftManager)
    {
        $draft = ContentDraft::findOrFail($draftId);
        $draftManager->rejectDraft($draft, 'needs_revision', 'Please revise and improve');
        $this->loadDrafts();
        session()->flash('message', 'Draft sent back for revision.');
    }

    public function markPublished($draftId, ContentDraftManagerService $draftManager)
    {
        $draft = ContentDraft::findOrFail($draftId);
        $draftManager->markAsPublished($draft);
        $this->loadDrafts();
        session()->flash('message', 'Draft marked as published.');
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
    <!-- Flash Messages -->
    @if(session()->has('message'))
        <div class="mb-4 p-4 bg-green-100 border border-green-400 text-green-700 rounded-lg">
            {{ session('message') }}
        </div>
    @endif

    <!-- Header -->
    <div class="flex flex-wrap items-center justify-between gap-4 mb-4">
        <div class="flex items-center space-x-2">
            <h2 class="text-lg font-semibold text-gray-900">Content Drafts</h2>
            <span class="px-2 py-1 text-xs rounded-full bg-blue-100 text-blue-800">
                {{ count($drafts) }} items
            </span>
        </div>

        <div class="flex space-x-1">
            <button wire:click="setFilter('all')" 
                    class="px-3 py-1 text-sm rounded {{ $filter === 'all' ? 'bg-gray-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' }}">
                All
            </button>
            <button wire:click="setFilter('draft')" 
                    class="px-3 py-1 text-sm rounded {{ $filter === 'draft' ? 'bg-yellow-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' }}">
                Drafts
            </button>
            <button wire:click="setFilter('review')" 
                    class="px-3 py-1 text-sm rounded {{ $filter === 'review' ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' }}">
                In Review
            </button>
            <button wire:click="setFilter('approved')" 
                    class="px-3 py-1 text-sm rounded {{ $filter === 'approved' ? 'bg-green-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' }}">
                Approved
            </button>
            <button wire:click="setFilter('published')" 
                    class="px-3 py-1 text-sm rounded {{ $filter === 'published' ? 'bg-purple-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' }}">
                Published
            </button>
        </div>
    </div>

    @if(empty($drafts))
        <div class="bg-gray-50 p-8 rounded-lg border border-gray-200 text-center">
            <p class="text-gray-500">No drafts found.</p>
            <p class="text-sm text-gray-400 mt-1">Content will appear here once approved actions are processed.</p>
        </div>
    @else
        <div class="space-y-4">
            @foreach($drafts as $draft)
                <div class="bg-white p-4 rounded-lg border border-gray-200 hover:shadow-md transition">
                    <div class="flex items-start justify-between">
                        <div class="flex-1">
                            <!-- Source Action Header -->
                            @if(isset($draft['action']) && $draft['action'])
                                <div class="mb-3 p-3 bg-gray-50 rounded-lg border border-gray-200">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="text-xs font-medium text-gray-500">📌 From Action:</span>
                                        <span class="text-sm font-medium text-gray-900">{{ $draft['action']['title'] }}</span>
                                    </div>
                                    <div class="flex flex-wrap items-center gap-2 mt-1">
                                        <span class="px-2 py-0.5 text-xs rounded-full 
                                            {{ $draft['action']['category'] === 'seo' ? 'bg-blue-100 text-blue-800' : '' }}
                                            {{ $draft['action']['category'] === 'content' ? 'bg-green-100 text-green-800' : '' }}
                                            {{ $draft['action']['category'] === 'social' ? 'bg-purple-100 text-purple-800' : '' }}
                                            {{ $draft['action']['category'] === 'email' ? 'bg-yellow-100 text-yellow-800' : '' }}
                                            {{ $draft['action']['category'] === 'web_copy' ? 'bg-teal-100 text-teal-800' : '' }}
                                        ">
                                            {{ $draft['action']['category'] ?? 'Unknown' }}
                                        </span>
                                        <span class="px-2 py-0.5 text-xs rounded-full bg-gray-100 text-gray-600">
                                            Priority: {{ $draft['action']['priority'] ?? 0 }}/5
                                        </span>
                                        @if(isset($draft['action']['estimated_impact']))
                                            <span class="px-2 py-0.5 text-xs rounded-full bg-green-100 text-green-800">
                                                Impact: ${{ number_format($draft['action']['estimated_impact'], 2) }}
                                            </span>
                                        @endif
                                        <span class="px-2 py-0.5 text-xs rounded-full 
                                            {{ $draft['action']['status'] === 'pending' ? 'bg-yellow-100 text-yellow-800' : '' }}
                                            {{ $draft['action']['status'] === 'approved' ? 'bg-blue-100 text-blue-800' : '' }}
                                            {{ $draft['action']['status'] === 'content_generated' ? 'bg-green-100 text-green-800' : '' }}
                                        ">
                                            Action: {{ ucfirst($draft['action']['status'] ?? 'Unknown') }}
                                        </span>
                                    </div>
                                    @if(isset($draft['action']['target_url']))
                                        <div class="mt-1 text-xs text-gray-500">
                                            Target: {{ $draft['action']['target_url'] }}
                                        </div>
                                    @endif
                                    @if(isset($draft['action']['description']))
                                        <div class="mt-1 text-xs text-gray-600">
                                            {{ $draft['action']['description'] }}
                                        </div>
                                    @endif
                                </div>
                            @endif

                            <!-- Content Draft Details -->
                            <div class="flex flex-wrap items-center gap-2 mb-2">
                                <span class="px-2 py-1 text-xs rounded-full 
                                    {{ $draft['type'] === 'blog' ? 'bg-green-100 text-green-800' : '' }}
                                    {{ $draft['type'] === 'social' ? 'bg-purple-100 text-purple-800' : '' }}
                                    {{ $draft['type'] === 'email' ? 'bg-yellow-100 text-yellow-800' : '' }}
                                    {{ $draft['type'] === 'web_copy' ? 'bg-blue-100 text-blue-800' : '' }}
                                    {{ $draft['type'] === 'seo_meta' ? 'bg-indigo-100 text-indigo-800' : '' }}
                                ">
                                    {{ $draft['type_label'] ?? ucfirst($draft['type']) }}
                                </span>
                                
                                <span class="px-2 py-1 text-xs rounded-full 
                                    {{ $draft['status'] === 'draft' ? 'bg-yellow-100 text-yellow-800' : '' }}
                                    {{ $draft['status'] === 'review' ? 'bg-blue-100 text-blue-800' : '' }}
                                    {{ $draft['status'] === 'approved' ? 'bg-green-100 text-green-800' : '' }}
                                    {{ $draft['status'] === 'published' ? 'bg-purple-100 text-purple-800' : '' }}
                                ">
                                    {{ $statusLabels[$draft['status']] ?? ucfirst($draft['status']) }}
                                </span>

                                @if($draft['target_keyword'])
                                    <span class="px-2 py-1 text-xs rounded-full bg-gray-100 text-gray-600">
                                        🔑 {{ $draft['target_keyword'] }}
                                    </span>
                                @endif
                            </div>

                            <h3 class="font-medium text-gray-900">{{ $draft['title'] }}</h3>
                            
                            <!-- Meta Length Checks (for SEO content) -->
                            @if($draft['type'] === 'seo_meta')
                                <div class="mt-2 space-y-1">
                                    @if($draft['meta_title'])
                                        @php
                                            $titleLength = $draft['meta_title_length'] ?? strlen($draft['meta_title']);
                                            $titleValid = $titleLength >= 50 && $titleLength <= 60;
                                        @endphp
                                        <div class="text-xs {{ $titleValid ? 'text-green-600' : 'text-red-500' }}">
                                            Title: {{ $draft['meta_title'] }} ({{ $titleLength }}/50-60 chars)
                                            {{ $titleValid ? '✅' : '⚠️' }}
                                        </div>
                                    @endif
                                    @if($draft['meta_description'])
                                        @php
                                            $descLength = $draft['meta_description_length'] ?? strlen($draft['meta_description']);
                                            $descValid = $descLength >= 140 && $descLength <= 160;
                                        @endphp
                                        <div class="text-xs {{ $descValid ? 'text-green-600' : 'text-red-500' }}">
                                            Description: {{ $draft['meta_description'] }} ({{ $descLength }}/140-160 chars)
                                            {{ $descValid ? '✅' : '⚠️' }}
                                        </div>
                                    @endif
                                </div>
                            @endif

                            @if($draft['excerpt'])
                                <p class="text-sm text-gray-600 mt-1">{{ $draft['excerpt'] }}</p>
                            @endif

                            @if(isset($draft['word_count']) && $draft['word_count'] > 0)
                                <div class="text-xs text-gray-400 mt-1">
                                    📝 {{ number_format($draft['word_count']) }} words
                                </div>
                            @endif

                            <!-- Expand/Collapse -->
                            <button wire:click="toggleExpand({{ $draft['id'] }})" 
                                    class="text-sm text-blue-600 hover:text-blue-800 mt-2">
                                {{ $expandedDraftId === $draft['id'] ? '📄 Hide Content' : '📄 View Full Content' }}
                            </button>

                            @if($expandedDraftId === $draft['id'])
                                <div class="mt-3 p-4 bg-gray-50 rounded-lg border border-gray-200">
                                    <div class="prose max-w-none text-sm text-gray-700 whitespace-pre-wrap">
                                        {{ $draft['content'] }}
                                    </div>

                                    @if($draft['meta_title'] || $draft['meta_description'])
                                        <div class="mt-3 p-3 bg-blue-50 rounded-lg border border-blue-200">
                                            <p class="text-sm font-medium text-blue-800">🔍 SEO Information</p>
                                            @if($draft['meta_title'])
                                                <p class="text-sm text-blue-700">
                                                    <strong>Title:</strong> {{ $draft['meta_title'] }}
                                                    <span class="text-xs text-gray-500">({{ strlen($draft['meta_title']) }}/50-60 chars)</span>
                                                </p>
                                            @endif
                                            @if($draft['meta_description'])
                                                <p class="text-sm text-blue-700">
                                                    <strong>Description:</strong> {{ $draft['meta_description'] }}
                                                    <span class="text-xs text-gray-500">({{ strlen($draft['meta_description']) }}/140-160 chars)</span>
                                                </p>
                                            @endif
                                        </div>
                                    @endif

                                    @if($draft['seo_data'])
                                        <div class="mt-3 grid grid-cols-2 gap-2">
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
                                            @if(isset($draft['seo_data']['keyword_density']))
                                                <div class="p-2 bg-purple-50 rounded">
                                                    <p class="text-xs text-gray-500">Keyword Density</p>
                                                    <p class="text-sm font-medium">{{ $draft['seo_data']['keyword_density'] }}%</p>
                                                </div>
                                            @endif
                                            @if(isset($draft['seo_data']['suggested_tags']))
                                                <div class="p-2 bg-gray-50 rounded">
                                                    <p class="text-xs text-gray-500">Suggested Tags</p>
                                                    <p class="text-sm font-medium">{{ implode(', ', $draft['seo_data']['suggested_tags']) }}</p>
                                                </div>
                                            @endif
                                        </div>
                                    @endif
                                </div>
                            @endif
                        </div>

                        <!-- Action Buttons -->
                        <div class="flex flex-col space-y-2 ml-4">
                            @if($draft['status'] === 'draft')
                                <button wire:click="approveDraft({{ $draft['id'] }})" 
                                        wire:confirm="Are you sure you want to approve this draft?"
                                        class="px-4 py-2 text-sm bg-green-600 text-white rounded-lg hover:bg-green-700 transition">
                                    ✅ Approve
                                </button>
                                <button wire:click="rejectDraft({{ $draft['id'] }})" 
                                        class="px-4 py-2 text-sm bg-yellow-600 text-white rounded-lg hover:bg-yellow-700 transition">
                                    🔄 Request Revisions
                                </button>
                            @endif

                            @if($draft['status'] === 'approved')
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
                                @if($draft['published_url'])
                                    <a href="{{ $draft['published_url'] }}" target="_blank" 
                                       class="px-3 py-2 text-sm bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-center">
                                        🔗 View
                                    </a>
                                @endif
                            @endif

                            <!-- Link back to action -->
                            @if(isset($draft['action']) && $draft['action'])
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
</div>