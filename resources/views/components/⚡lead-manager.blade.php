<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\Lead;
use App\Models\Brand;
use App\Services\Lead\LeadManagerService;
use Illuminate\Support\Facades\Auth;

new class extends Component
{
    public $brandId = null;
    public $leads = [];
    public $stats = [];
    public $filter = 'new';
    public $expandedLeadId = null;
    public $showCreateModal = false;

    // Create form fields
    public $firstName = '';
    public $lastName = '';
    public $email = '';
    public $phone = '';
    public $company = '';
    public $title = '';
    public $message = '';
    public $source = 'website';

    protected $listeners = ['brand-switched' => 'loadLeads'];

    public function mount()
    {
        $this->brandId = Auth::user()->active_brand_id;
        $this->loadLeads();
    }

    public function loadLeads()
    {
        if (!$this->brandId) {
            $this->leads = [];
            $this->stats = [];
            return;
        }

        $brand = Brand::find($this->brandId);
        $query = Lead::where('brand_id', $this->brandId);

        if ($this->filter === 'new') {
            $query->where('status', 'new');
        } elseif ($this->filter === 'hot') {
            $query->where('score', 'hot');
        } elseif ($this->filter === 'won') {
            $query->where('status', 'won');
        } elseif ($this->filter === 'lost') {
            $query->where('status', 'lost');
        }

        $this->leads = $query->orderBy('created_at', 'desc')->get()->toArray();

        // Get stats
        $leadManager = app(LeadManagerService::class);
        $this->stats = $leadManager->getLeadStats($brand);
    }

    public function createLead(LeadManagerService $leadManager)
    {
        $this->validate([
            'email' => 'required|email',
            'firstName' => 'nullable|string',
            'lastName' => 'nullable|string',
            'message' => 'nullable|string',
        ]);

        $brand = Brand::find($this->brandId);

        $lead = $leadManager->createLead([
            'first_name' => $this->firstName,
            'last_name' => $this->lastName,
            'email' => $this->email,
            'phone' => $this->phone,
            'company' => $this->company,
            'title' => $this->title,
            'message' => $this->message,
            'source' => $this->source,
        ], $brand);

        $this->showCreateModal = false;
        $this->resetForm();
        $this->loadLeads();

        session()->flash('message', 'Lead created and processing with AI.');
    }

    public function updateStatus($leadId, $status, LeadManagerService $leadManager)
    {
        $lead = Lead::findOrFail($leadId);
        $leadManager->updateStatus($lead, $status);
        $this->loadLeads();
        session()->flash('message', 'Lead status updated.');
    }

    public function toggleExpand($leadId)
    {
        $this->expandedLeadId = ($this->expandedLeadId === $leadId) ? null : $leadId;
    }

    public function setFilter($filter)
    {
        $this->filter = $filter;
        $this->loadLeads();
    }

    protected function resetForm()
    {
        $this->firstName = '';
        $this->lastName = '';
        $this->email = '';
        $this->phone = '';
        $this->company = '';
        $this->title = '';
        $this->message = '';
        $this->source = 'website';
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
    <div class="flex justify-between items-center mb-4">
        <h2 class="text-lg font-semibold text-gray-900">👤 Lead Manager</h2>
        <button wire:click="$set('showCreateModal', true)" 
                class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition">
            + New Lead
        </button>
    </div>

    <!-- Stats -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-gray-50 p-4 rounded-lg border border-gray-200">
            <p class="text-sm text-gray-500">Total Leads</p>
            <p class="text-2xl font-bold text-blue-600">{{ $stats['total_leads'] ?? 0 }}</p>
        </div>
        <div class="bg-gray-50 p-4 rounded-lg border border-gray-200">
            <p class="text-sm text-gray-500">New</p>
            <p class="text-2xl font-bold text-yellow-600">{{ $stats['new_leads'] ?? 0 }}</p>
        </div>
        <div class="bg-gray-50 p-4 rounded-lg border border-gray-200">
            <p class="text-sm text-gray-500">Hot Leads</p>
            <p class="text-2xl font-bold text-red-600">{{ $stats['hot_leads'] ?? 0 }}</p>
        </div>
        <div class="bg-gray-50 p-4 rounded-lg border border-gray-200">
            <p class="text-sm text-gray-500">Won</p>
            <p class="text-2xl font-bold text-green-600">{{ $stats['won_leads'] ?? 0 }}</p>
        </div>
    </div>

    <!-- Filter -->
    <div class="flex space-x-1 mb-4">
        <button wire:click="setFilter('new')" 
                class="px-3 py-1 text-sm rounded {{ $filter === 'new' ? 'bg-yellow-600 text-white' : 'bg-gray-200 text-gray-700' }}">
            New
        </button>
        <button wire:click="setFilter('hot')" 
                class="px-3 py-1 text-sm rounded {{ $filter === 'hot' ? 'bg-red-600 text-white' : 'bg-gray-200 text-gray-700' }}">
            Hot
        </button>
        <button wire:click="setFilter('won')" 
                class="px-3 py-1 text-sm rounded {{ $filter === 'won' ? 'bg-green-600 text-white' : 'bg-gray-200 text-gray-700' }}">
            Won
        </button>
        <button wire:click="setFilter('lost')" 
                class="px-3 py-1 text-sm rounded {{ $filter === 'lost' ? 'bg-gray-600 text-white' : 'bg-gray-200 text-gray-700' }}">
            Lost
        </button>
        <button wire:click="setFilter('all')" 
                class="px-3 py-1 text-sm rounded {{ $filter === 'all' ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700' }}">
            All
        </button>
    </div>

    <!-- Lead List -->
    @if(empty($leads))
        <div class="bg-gray-50 p-8 rounded-lg border border-gray-200 text-center">
            <p class="text-gray-500">No leads found.</p>
        </div>
    @else
        <div class="space-y-3">
            @foreach($leads as $lead)
                <div class="bg-white p-4 rounded-lg border border-gray-200">
                    <div class="flex items-start justify-between">
                        <div class="flex-1">
                            <!-- Lead Header -->
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="font-medium text-gray-900">
                                    {{ $lead['first_name'] ?? '' }} {{ $lead['last_name'] ?? '' }}
                                    @if(!$lead['first_name'] && !$lead['last_name'])
                                        <span class="text-gray-500">Unknown</span>
                                    @endif
                                </span>
                                <span class="px-2 py-1 text-xs rounded-full 
                                    {{ $lead['status'] === 'new' ? 'bg-yellow-100 text-yellow-800' : '' }}
                                    {{ $lead['status'] === 'contacted' ? 'bg-blue-100 text-blue-800' : '' }}
                                    {{ $lead['status'] === 'qualified' ? 'bg-green-100 text-green-800' : '' }}
                                    {{ $lead['status'] === 'won' ? 'bg-green-600 text-white' : '' }}
                                    {{ $lead['status'] === 'lost' ? 'bg-gray-600 text-white' : '' }}
                                ">
                                    {{ ucfirst($lead['status']) }}
                                </span>
                                @if($lead['score'])
                                    <span class="px-2 py-1 text-xs rounded-full 
                                        {{ $lead['score'] === 'hot' ? 'bg-red-100 text-red-800' : '' }}
                                        {{ $lead['score'] === 'warm' ? 'bg-orange-100 text-orange-800' : '' }}
                                        {{ $lead['score'] === 'cold' ? 'bg-blue-100 text-blue-800' : '' }}
                                    ">
                                        🔥 {{ ucfirst($lead['score']) }}
                                    </span>
                                @endif
                                @if($lead['category'])
                                    <span class="px-2 py-1 text-xs rounded-full bg-gray-100 text-gray-800">
                                        {{ ucfirst($lead['category']) }}
                                    </span>
                                @endif
                                @if($lead['estimated_value'])
                                    <span class="px-2 py-1 text-xs rounded-full bg-green-100 text-green-800">
                                        ${{ number_format($lead['estimated_value'], 2) }}
                                    </span>
                                @endif
                            </div>

                            <!-- Contact Info -->
                            <div class="flex flex-wrap gap-2 mt-1 text-sm text-gray-600">
                                @if($lead['email'])
                                    <span>📧 {{ $lead['email'] }}</span>
                                @endif
                                @if($lead['phone'])
                                    <span>📱 {{ $lead['phone'] }}</span>
                                @endif
                                @if($lead['company'])
                                    <span>🏢 {{ $lead['company'] }}</span>
                                @endif
                            </div>

                            <!-- Expand Button -->
                            <button wire:click="toggleExpand({{ $lead['id'] }})" 
                                    class="text-sm text-blue-600 hover:text-blue-800 mt-2">
                                {{ $expandedLeadId === $lead['id'] ? '📄 Hide Details' : '📄 View AI Analysis' }}
                            </button>

                            @if($expandedLeadId === $lead['id'])
                                <div class="mt-3 space-y-2">
                                    @if($lead['ai_summary'])
                                        <div class="p-3 bg-blue-50 rounded-lg border border-blue-200">
                                            <p class="text-sm font-medium text-blue-800">📋 AI Summary</p>
                                            <p class="text-sm text-blue-700">{{ $lead['ai_summary'] }}</p>
                                        </div>
                                    @endif

                                    @if($lead['ai_suggested_response'])
                                        <div class="p-3 bg-green-50 rounded-lg border border-green-200">
                                            <p class="text-sm font-medium text-green-800">💬 Suggested Response</p>
                                            <p class="text-sm text-green-700">{{ $lead['ai_suggested_response'] }}</p>
                                        </div>
                                    @endif

                                    @if($lead['message'])
                                        <div class="p-3 bg-gray-50 rounded-lg border border-gray-200">
                                            <p class="text-sm font-medium text-gray-800">📝 Original Message</p>
                                            <p class="text-sm text-gray-700">{{ $lead['message'] }}</p>
                                        </div>
                                    @endif

                                    @if($lead['notes'])
                                        <div class="p-3 bg-yellow-50 rounded-lg border border-yellow-200">
                                            <p class="text-sm font-medium text-yellow-800">📌 Notes</p>
                                            <p class="text-sm text-yellow-700">{{ $lead['notes'] }}</p>
                                        </div>
                                    @endif

                                    <div class="flex flex-wrap gap-2 mt-2">
                                        @if($lead['status'] !== 'contacted')
                                            <button wire:click="updateStatus({{ $lead['id'] }}, 'contacted')" 
                                                    class="px-3 py-1 text-sm bg-blue-600 text-white rounded hover:bg-blue-700">
                                                📞 Contacted
                                            </button>
                                        @endif
                                        @if($lead['status'] !== 'qualified')
                                            <button wire:click="updateStatus({{ $lead['id'] }}, 'qualified')" 
                                                    class="px-3 py-1 text-sm bg-green-600 text-white rounded hover:bg-green-700">
                                                ✅ Qualified
                                            </button>
                                        @endif
                                        @if($lead['status'] !== 'won')
                                            <button wire:click="updateStatus({{ $lead['id'] }}, 'won')" 
                                                    class="px-3 py-1 text-sm bg-green-800 text-white rounded hover:bg-green-900">
                                                🏆 Won
                                            </button>
                                        @endif
                                        @if($lead['status'] !== 'lost')
                                            <button wire:click="updateStatus({{ $lead['id'] }}, 'lost')" 
                                                    class="px-3 py-1 text-sm bg-gray-600 text-white rounded hover:bg-gray-700">
                                                ❌ Lost
                                            </button>
                                        @endif
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    <!-- Create Lead Modal -->
    @if($showCreateModal)
    <div class="fixed inset-0 bg-gray-500 bg-opacity-75 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg shadow-xl max-w-lg w-full p-6 max-h-[90vh] overflow-y-auto">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Create New Lead</h3>

            <div class="space-y-4">
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">First Name</label>
                        <input wire:model="firstName" type="text" 
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Last Name</label>
                        <input wire:model="lastName" type="text" 
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Email *</label>
                    <input wire:model="email" type="email" 
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500">
                    @error('email') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Phone</label>
                    <input wire:model="phone" type="text" 
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Company</label>
                    <input wire:model="company" type="text" 
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Title</label>
                    <input wire:model="title" type="text" 
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Source</label>
                    <select wire:model="source" 
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500">
                        <option value="website">Website</option>
                        <option value="social">Social Media</option>
                        <option value="email">Email</option>
                        <option value="referral">Referral</option>
                        <option value="event">Event</option>
                        <option value="other">Other</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Message</label>
                    <textarea wire:model="message" rows="3"
                              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500"
                              placeholder="What does this lead want?"></textarea>
                </div>
            </div>

            <div class="mt-6 flex justify-end space-x-3">
                <button wire:click="$set('showCreateModal', false)" 
                        class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">
                    Cancel
                </button>
                <button wire:click="createLead" 
                        class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition">
                    Create Lead
                </button>
            </div>
        </div>
    </div>
    @endif
</div>