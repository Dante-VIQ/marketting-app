<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\Campaign;
use App\Models\Brand;
use App\Services\Campaign\CampaignManagerService;
use Illuminate\Support\Facades\Auth;

new class extends Component
{
    public $brandId = null;
    public $campaigns = [];
    public $summary = [];
    public $filter = 'active';
    
    // Create/Edit form fields
    public $showModal = false;
    public $editingCampaign = null;
    public $name = '';
    public $type = 'other';
    public $description = '';
    public $budget = '';
    public $start_date = '';
    public $end_date = '';
    public $status = 'draft';
    public $settings = '';

    // Public properties for dropdowns (available in view)
    public $campaignTypes = [];
    public $statusOptions = [];

    protected $listeners = ['brand-switched' => 'loadCampaigns'];

    public function mount()
    {
        $this->brandId = Auth::user()->active_brand_id;
        $this->loadCampaigns();
        $this->setDefaultDates();
        $this->setOptions();
    }

    public function setOptions()
    {
        $this->campaignTypes = [
            'email' => 'Email',
            'social' => 'Social Media',
            'ppc' => 'PPC',
            'seo' => 'SEO',
            'content' => 'Content',
            'affiliate' => 'Affiliate',
            'other' => 'Other',
        ];

        $this->statusOptions = [
            'draft' => 'Draft',
            'active' => 'Active',
            'paused' => 'Paused',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled',
        ];
    }

    public function setDefaultDates()
    {
        $this->start_date = now()->toDateString();
        $this->end_date = now()->addDays(30)->toDateString();
    }

    public function loadCampaigns()
    {
        if (!$this->brandId) {
            $this->campaigns = [];
            $this->summary = [];
            return;
        }

        $brand = Brand::find($this->brandId);
        $query = Campaign::where('brand_id', $this->brandId);

        if ($this->filter === 'active') {
            $query->where('status', 'active');
        } elseif ($this->filter === 'completed') {
            $query->where('status', 'completed');
        } elseif ($this->filter === 'draft') {
            $query->where('status', 'draft');
        }

        $this->campaigns = $query->orderBy('created_at', 'desc')->get()->toArray();

        // Get summary
        $campaignManager = app(CampaignManagerService::class);
        $this->summary = $campaignManager->getPerformanceSummary($brand);
    }

    public function create()
    {
        $this->resetForm();
        $this->editingCampaign = null;
        $this->showModal = true;
        $this->setDefaultDates();
    }

    public function edit($campaignId)
    {
        $campaign = Campaign::findOrFail($campaignId);
        $this->editingCampaign = $campaign;
        $this->name = $campaign->name;
        $this->type = $campaign->type;
        $this->description = $campaign->description;
        $this->budget = $campaign->budget;
        $this->start_date = $campaign->start_date->toDateString();
        $this->end_date = $campaign->end_date ? $campaign->end_date->toDateString() : '';
        $this->status = $campaign->status;
        $this->settings = json_encode($campaign->settings, JSON_PRETTY_PRINT);
        $this->showModal = true;
    }

    public function save()
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:email,social,ppc,seo,content,affiliate,other',
            'description' => 'nullable|string',
            'budget' => 'nullable|numeric|min:0',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'status' => 'required|in:draft,active,paused,completed,cancelled',
            'settings' => 'nullable|json',
        ]);

        $brand = Brand::find($this->brandId);
        $campaignManager = app(CampaignManagerService::class);

        $settingsArray = $this->settings ? json_decode($this->settings, true) : [];
        if ($this->settings && json_last_error() !== JSON_ERROR_NONE) {
            $this->addError('settings', 'Invalid JSON format.');
            return;
        }

        $data = [
            'name' => $this->name,
            'type' => $this->type,
            'description' => $this->description,
            'budget' => $this->budget ?: null,
            'start_date' => $this->start_date,
            'end_date' => $this->end_date ?: null,
            'status' => $this->status,
            'settings' => $settingsArray,
        ];

        if ($this->editingCampaign) {
            $this->editingCampaign->update($data);
            session()->flash('message', 'Campaign updated successfully.');
        } else {
            $campaignManager->createCampaign($data, $brand);
            session()->flash('message', 'Campaign created successfully.');
        }

        $this->showModal = false;
        $this->resetForm();
        $this->loadCampaigns();
    }

    public function deleteCampaign($campaignId)
    {
        $campaign = Campaign::findOrFail($campaignId);
        
        if (!in_array($campaign->status, ['draft', 'cancelled'])) {
            session()->flash('error', 'Only draft or cancelled campaigns can be deleted.');
            return;
        }

        $campaign->delete();
        $this->loadCampaigns();
        session()->flash('message', 'Campaign deleted successfully.');
    }

    public function updateStatus($campaignId, $status)
    {
        $campaign = Campaign::findOrFail($campaignId);
        $campaign->update(['status' => $status]);
        $this->loadCampaigns();
        session()->flash('message', "Campaign status updated to {$status}.");
    }

    public function setFilter($filter)
    {
        $this->filter = $filter;
        $this->loadCampaigns();
    }

    protected function resetForm()
    {
        $this->name = '';
        $this->type = 'other';
        $this->description = '';
        $this->budget = '';
        $this->start_date = '';
        $this->end_date = '';
        $this->status = 'draft';
        $this->settings = '';
        $this->resetErrorBag();
        $this->setDefaultDates();
    }

    // No render() method - Volt API uses the view name automatically
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
    <div class="flex justify-between items-center mb-4">
        <h2 class="text-lg font-semibold text-gray-900">📊 Campaigns</h2>
        <button wire:click="create" 
                class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition">
            + New Campaign
        </button>
    </div>

    <!-- Summary Cards -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-gray-50 p-4 rounded-lg border border-gray-200">
            <p class="text-sm text-gray-500">Active Campaigns</p>
            <p class="text-2xl font-bold text-blue-600">{{ $summary['active_count'] ?? 0 }}</p>
        </div>
        <div class="bg-gray-50 p-4 rounded-lg border border-gray-200">
            <p class="text-sm text-gray-500">Total Spent</p>
            <p class="text-2xl font-bold text-red-600">${{ number_format($summary['total_spent'] ?? 0, 2) }}</p>
        </div>
        <div class="bg-gray-50 p-4 rounded-lg border border-gray-200">
            <p class="text-sm text-gray-500">Total Revenue</p>
            <p class="text-2xl font-bold text-green-600">${{ number_format($summary['total_revenue'] ?? 0, 2) }}</p>
        </div>
        <div class="bg-gray-50 p-4 rounded-lg border border-gray-200">
            <p class="text-sm text-gray-500">Overall ROI</p>
            <p class="text-2xl font-bold {{ ($summary['overall_roi'] ?? 0) >= 0 ? 'text-green-600' : 'text-red-600' }}">
                {{ number_format($summary['overall_roi'] ?? 0, 1) }}%
            </p>
        </div>
    </div>

    <!-- Filter -->
    <div class="flex space-x-1 mb-4">
        <button wire:click="setFilter('active')" 
                class="px-3 py-1 text-sm rounded {{ $filter === 'active' ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700' }}">
            Active
        </button>
        <button wire:click="setFilter('completed')" 
                class="px-3 py-1 text-sm rounded {{ $filter === 'completed' ? 'bg-green-600 text-white' : 'bg-gray-200 text-gray-700' }}">
            Completed
        </button>
        <button wire:click="setFilter('draft')" 
                class="px-3 py-1 text-sm rounded {{ $filter === 'draft' ? 'bg-yellow-600 text-white' : 'bg-gray-200 text-gray-700' }}">
            Drafts
        </button>
        <button wire:click="setFilter('all')" 
                class="px-3 py-1 text-sm rounded {{ $filter === 'all' ? 'bg-gray-600 text-white' : 'bg-gray-200 text-gray-700' }}">
            All
        </button>
    </div>

    <!-- Campaign List -->
    @if(empty($campaigns))
        <div class="bg-gray-50 p-8 rounded-lg border border-gray-200 text-center">
            <p class="text-gray-500">No campaigns found.</p>
            <button wire:click="create" 
                    class="mt-2 text-green-600 hover:text-green-700 font-medium">
                + Create your first campaign
            </button>
        </div>
    @else
        <div class="space-y-3">
            @foreach($campaigns as $campaign)
                <div class="bg-white p-4 rounded-lg border border-gray-200 hover:shadow-md transition">
                    <div class="flex items-start justify-between">
                        <div class="flex-1">
                            <div class="flex items-center space-x-2">
                                <span class="px-2 py-1 text-xs rounded-full 
                                    {{ $campaign['type'] === 'email' ? 'bg-blue-100 text-blue-800' : '' }}
                                    {{ $campaign['type'] === 'social' ? 'bg-purple-100 text-purple-800' : '' }}
                                    {{ $campaign['type'] === 'ppc' ? 'bg-red-100 text-red-800' : '' }}
                                    {{ $campaign['type'] === 'seo' ? 'bg-green-100 text-green-800' : '' }}
                                    {{ $campaign['type'] === 'content' ? 'bg-yellow-100 text-yellow-800' : '' }}
                                    {{ $campaign['type'] === 'affiliate' ? 'bg-orange-100 text-orange-800' : '' }}
                                    {{ $campaign['type'] === 'other' ? 'bg-gray-100 text-gray-800' : '' }}
                                ">
                                    {{ ucfirst($campaign['type']) }}
                                </span>
                                <span class="px-2 py-1 text-xs rounded-full 
                                    {{ $campaign['status'] === 'active' ? 'bg-green-100 text-green-800' : '' }}
                                    {{ $campaign['status'] === 'completed' ? 'bg-gray-100 text-gray-800' : '' }}
                                    {{ $campaign['status'] === 'draft' ? 'bg-yellow-100 text-yellow-800' : '' }}
                                    {{ $campaign['status'] === 'paused' ? 'bg-blue-100 text-blue-800' : '' }}
                                    {{ $campaign['status'] === 'cancelled' ? 'bg-red-100 text-red-800' : '' }}
                                ">
                                    {{ ucfirst($campaign['status']) }}
                                </span>
                            </div>
                            <h3 class="font-medium text-gray-900 mt-1">{{ $campaign['name'] }}</h3>
                            <p class="text-sm text-gray-600">{{ $campaign['description'] ?? 'No description' }}</p>
                            <div class="flex flex-wrap gap-4 mt-2 text-sm">
                                <span class="text-gray-500">Budget: ${{ number_format($campaign['budget'] ?? 0, 2) }}</span>
                                <span class="text-gray-500">Spent: ${{ number_format($campaign['spent'] ?? 0, 2) }}</span>
                                <span class="text-gray-500">Leads: {{ $campaign['leads'] ?? 0 }}</span>
                                <span class="text-gray-500">Revenue: ${{ number_format($campaign['revenue'] ?? 0, 2) }}</span>
                                <span class="text-gray-500">
                                    Start: {{ \Carbon\Carbon::parse($campaign['start_date'])->format('M d, Y') }}
                                    @if($campaign['end_date'])
                                        - {{ \Carbon\Carbon::parse($campaign['end_date'])->format('M d, Y') }}
                                    @endif
                                </span>
                            </div>
                        </div>
                        <div class="flex flex-col items-end space-y-2">
                            <span class="text-sm font-medium {{ ($campaign['revenue'] ?? 0) > ($campaign['spent'] ?? 0) ? 'text-green-600' : 'text-red-600' }}">
                                ROI: {{ $campaign['spent'] > 0 ? round((($campaign['revenue'] - $campaign['spent']) / $campaign['spent']) * 100, 1) : 0 }}%
                            </span>
                            <div class="flex space-x-2">
                                <button wire:click="edit({{ $campaign['id'] }})" 
                                        class="text-sm text-blue-600 hover:text-blue-800">
                                    ✏️ Edit
                                </button>
                                @if($campaign['status'] === 'draft')
                                    <button wire:click="updateStatus({{ $campaign['id'] }}, 'active')" 
                                            class="text-sm text-green-600 hover:text-green-800">
                                        ▶️ Activate
                                    </button>
                                    <button wire:click="deleteCampaign({{ $campaign['id'] }})" 
                                            wire:confirm="Are you sure you want to delete this campaign?"
                                            class="text-sm text-red-600 hover:text-red-800">
                                        🗑️ Delete
                                    </button>
                                @endif
                                @if($campaign['status'] === 'active')
                                    <button wire:click="updateStatus({{ $campaign['id'] }}, 'paused')" 
                                            class="text-sm text-yellow-600 hover:text-yellow-800">
                                        ⏸️ Pause
                                    </button>
                                    <button wire:click="updateStatus({{ $campaign['id'] }}, 'completed')" 
                                            class="text-sm text-green-600 hover:text-green-800">
                                        ✅ Complete
                                    </button>
                                @endif
                                @if($campaign['status'] === 'paused')
                                    <button wire:click="updateStatus({{ $campaign['id'] }}, 'active')" 
                                            class="text-sm text-green-600 hover:text-green-800">
                                        ▶️ Resume
                                    </button>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    <!-- Create/Edit Campaign Modal -->
    @if($showModal)
    <div class="fixed inset-0 bg-gray-500 bg-opacity-75 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg shadow-xl max-w-lg w-full p-6 max-h-[90vh] overflow-y-auto">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold text-gray-900">
                    {{ $editingCampaign ? 'Edit Campaign' : 'Create New Campaign' }}
                </h3>
                <button wire:click="$set('showModal', false)" 
                        class="text-gray-400 hover:text-gray-600">
                    ✕
                </button>
            </div>

            <form wire:submit="save" class="space-y-4">
                <!-- Name -->
                <div>
                    <label class="block text-sm font-medium text-gray-700">Campaign Name *</label>
                    <input wire:model="name" type="text" 
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500">
                    @error('name') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
                </div>

                <!-- Type -->
                <div>
                    <label class="block text-sm font-medium text-gray-700">Type *</label>
                    <select wire:model="type" 
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500">
                        @foreach($campaignTypes as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('type') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
                </div>

                <!-- Description -->
                <div>
                    <label class="block text-sm font-medium text-gray-700">Description</label>
                    <textarea wire:model="description" rows="2"
                              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500"
                              placeholder="What is this campaign about?"></textarea>
                </div>

                <!-- Budget -->
                <div>
                    <label class="block text-sm font-medium text-gray-700">Budget ($)</label>
                    <input wire:model="budget" type="number" step="0.01" min="0"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500"
                           placeholder="0.00">
                    @error('budget') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
                </div>

                <!-- Dates -->
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Start Date *</label>
                        <input wire:model="start_date" type="date"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500">
                        @error('start_date') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">End Date</label>
                        <input wire:model="end_date" type="date"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500">
                        @error('end_date') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
                    </div>
                </div>

                <!-- Status -->
                <div>
                    <label class="block text-sm font-medium text-gray-700">Status</label>
                    <select wire:model="status" 
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500">
                        @foreach($statusOptions as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('status') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
                </div>

                <!-- Settings (JSON) -->
                <div>
                    <label class="block text-sm font-medium text-gray-700">Additional Settings (JSON)</label>
                    <textarea wire:model="settings" rows="3"
                              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500 font-mono text-sm"
                              placeholder='{"target_audience": "travelers", "platform": "facebook"}'></textarea>
                    @error('settings') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
                    <p class="text-xs text-gray-500 mt-1">Optional JSON configuration for advanced settings.</p>
                </div>

                <!-- Actions -->
                <div class="flex justify-end space-x-3 pt-4 border-t border-gray-200">
                    <button type="button" wire:click="$set('showModal', false)" 
                            class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">
                        Cancel
                    </button>
                    <button type="submit" 
                            class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition">
                        {{ $editingCampaign ? 'Update Campaign' : 'Create Campaign' }}
                    </button>
                </div>
            </form>
        </div>
    </div>
    @endif
</div>