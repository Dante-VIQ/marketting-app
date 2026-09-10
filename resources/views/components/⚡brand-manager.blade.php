<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\Brand;
use App\Services\BrandManagementService;
use Illuminate\Support\Facades\Auth;

new class extends Component
{
    public $showModal = false;
    public $editingBrand = null;

    // Form fields
    public $name = '';
    public $websiteUrl = '';
    public $domainType = 'general';
    public $config = '';
    public $brandVoice = '';
    public $timezone = 'Africa/Nairobi';
    public $isActive = true;

    public $domainTypes = [];
    public $brands = [];

    protected BrandManagementService $brandService;

    public function boot(BrandManagementService $brandService)
    {
        $this->brandService = $brandService;
        $this->domainTypes = config('brand.domain_types', []);
    }

    protected function rules()
    {
        return [
            'name' => 'required|string|max:255|unique:brands,name,' . ($this->editingBrand ? $this->editingBrand->id : ''),
            'websiteUrl' => 'nullable|url|max:255',
            'domainType' => 'required|in:' . implode(',', array_keys($this->domainTypes)),
            'config' => 'nullable|json',
            'brandVoice' => 'required|string',
            'timezone' => 'required|string',
            'isActive' => 'boolean',
        ];
    }

    public function mount()
    {
        $this->loadBrands();
    }

    public function loadBrands()
    {
        $this->brands = Auth::user()->brands()->withCount('users')->get();
    }

    public function create()
    {
        $this->resetForm();
        $this->editingBrand = null;
        $this->showModal = true;
    }

    public function edit(Brand $brand)
    {
        $this->editingBrand = $brand;
        $this->name = $brand->name;
        $this->websiteUrl = $brand->website_url;
        $this->domainType = $brand->domain_type;
        $this->config = json_encode($brand->config, JSON_PRETTY_PRINT);
        $this->brandVoice = $brand->brand_voice;
        $this->timezone = $brand->timezone;
        $this->isActive = $brand->is_active;
        $this->showModal = true;
    }

    public function save()
    {
        $this->validate();

        $configArray = $this->config ? json_decode($this->config, true) : [];
        if ($this->config && json_last_error() !== JSON_ERROR_NONE) {
            $this->addError('config', 'Invalid JSON format.');
            return;
        }

        $user = Auth::user();

        if ($this->editingBrand) {
            $this->brandService->updateBrand($this->editingBrand, [
                'name' => $this->name,
                'website_url' => $this->websiteUrl,
                'domain_type' => $this->domainType,
                'config' => $configArray,
                'brand_voice' => $this->brandVoice,
                'timezone' => $this->timezone,
                'is_active' => $this->isActive,
            ], $user);

            session()->flash('message', 'Brand updated successfully.');
        } else {
            $this->brandService->createBrand([
                'name' => $this->name,
                'website_url' => $this->websiteUrl,
                'domain_type' => $this->domainType,
                'config' => $configArray,
                'brand_voice' => $this->brandVoice,
                'timezone' => $this->timezone,
                'is_active' => $this->isActive,
            ], $user);

            session()->flash('message', 'Brand created successfully.');
        }

        $this->showModal = false;
        $this->resetForm();
        $this->loadBrands();
        $this->dispatch('brands-updated');
    }

    public function toggleActive(Brand $brand)
    {
        $this->brandService->toggleActive($brand);
        $this->loadBrands();
        session()->flash('message', 'Brand status toggled.');
    }

    public function deleteBrand(Brand $brand)
    {
        $user = Auth::user();

        if (!$brand->isOwner($user) && !$user->hasRole('super-admin')) {
            session()->flash('error', 'Only the brand owner can delete this brand.');
            return;
        }

        $this->brandService->deleteBrand($brand, $user);
        $this->loadBrands();
        session()->flash('message', 'Brand deleted.');
    }

    protected function resetForm()
    {
        $this->name = '';
        $this->websiteUrl = ''; // ✅ FIX: was missing
        $this->domainType = 'general';
        $this->config = '';
        $this->brandVoice = config('brand.defaults.brand_voice');
        $this->timezone = config('brand.defaults.timezone');
        $this->isActive = true;
        $this->resetErrorBag();
    }
};
?>

<div class="min-h-screen text-slate-100">
    <!-- Header -->
    <div class="flex justify-between items-center mb-8">
        <div>
            <h1 class="text-3xl font-bold tracking-tight text-white">Brand Management</h1>
            <p class="text-sm text-slate-400 mt-1">Manage the brands this agent operates on.</p>
        </div>
        <button wire:click="create"
                class="inline-flex items-center gap-2 px-5 py-2.5 bg-emerald-500 text-white text-sm font-semibold rounded-xl shadow-lg shadow-emerald-500/20 hover:bg-emerald-400 transition-all duration-200">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            </svg>
            New Brand
        </button>
    </div>

    <!-- Flash Messages -->
    @if(session()->has('message'))
        <div class="mb-6 flex items-center gap-3 p-4 bg-emerald-950/40 border border-emerald-800/60 text-emerald-300 rounded-xl backdrop-blur-md">
            <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <span class="text-sm font-medium">{{ session('message') }}</span>
        </div>
    @endif

    @if(session()->has('error'))
        <div class="mb-6 flex items-center gap-3 p-4 bg-rose-950/40 border border-rose-800/60 text-rose-300 rounded-xl backdrop-blur-md">
            <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
            </svg>
            <span class="text-sm font-medium">{{ session('error') }}</span>
        </div>
    @endif

    <!-- Brand List -->
    <div class="bg-slate-900/60 backdrop-blur-md rounded-2xl border border-slate-800/80 shadow-xl overflow-hidden">
        <table class="min-w-full divide-y divide-slate-800/80">
            <thead class="bg-slate-900/80">
                <tr>
                    <th class="px-6 py-4 text-left text-[11px] font-bold text-slate-400 uppercase tracking-wider">Brand</th>
                    <th class="px-6 py-4 text-left text-[11px] font-bold text-slate-400 uppercase tracking-wider">Domain</th>
                    <th class="px-6 py-4 text-left text-[11px] font-bold text-slate-400 uppercase tracking-wider">Status</th>
                    <th class="px-6 py-4 text-left text-[11px] font-bold text-slate-400 uppercase tracking-wider">Users</th>
                    <th class="px-6 py-4 text-right text-[11px] font-bold text-slate-400 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-800/60">
                @forelse($brands as $brand)
                <tr class="hover:bg-slate-800/30 transition-colors">
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="text-sm font-semibold text-white">{{ $brand->name }}</div>
                        <div class="text-xs text-slate-500 font-mono">{{ $brand->slug }}</div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="inline-flex items-center px-2.5 py-0.5 text-xs font-medium rounded-full bg-sky-950/60 text-sky-300 border border-sky-800/50">
                            {{ ucfirst($brand->domain_type) }}
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="inline-flex items-center px-2.5 py-0.5 text-xs font-medium rounded-full {{ $brand->is_active ? 'bg-emerald-950/60 text-emerald-300 border border-emerald-800/50' : 'bg-slate-800/60 text-slate-400 border border-slate-700/50' }}">
                            <span class="w-1.5 h-1.5 rounded-full mr-1.5 {{ $brand->is_active ? 'bg-emerald-500 animate-pulse' : 'bg-slate-500' }}"></span>
                            {{ $brand->is_active ? 'Active' : 'Inactive' }}
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-400">
                        {{ $brand->users_count }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-right">
                        <div class="inline-flex items-center gap-3">
                            <button wire:click="edit({{ $brand->id }})"
                                    class="text-sky-400 hover:text-sky-300 transition-colors">
                                Edit
                            </button>
                            <button wire:click="toggleActive({{ $brand->id }})"
                                    class="{{ $brand->is_active ? 'text-amber-400 hover:text-amber-300' : 'text-emerald-400 hover:text-emerald-300' }} transition-colors">
                                {{ $brand->is_active ? 'Deactivate' : 'Activate' }}
                            </button>
                            <button wire:click="deleteBrand({{ $brand->id }})"
                                    wire:confirm="Are you sure you want to delete {{ $brand->name }}?"
                                    class="text-rose-400 hover:text-rose-300 transition-colors">
                                Delete
                            </button>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" class="px-6 py-16 text-center">
                        <div class="flex flex-col items-center gap-3">
                            <div class="text-4xl">🏢</div>
                            <div class="text-slate-400">No brands found. Create your first brand to get started.</div>
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- ==================== MODAL ==================== -->
    @if($showModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-sm" wire:key="brand-modal">
        <div class="bg-slate-900/95 backdrop-blur-xl rounded-2xl shadow-2xl border border-slate-800 max-w-2xl w-full max-h-[90vh] overflow-hidden flex flex-col">

            <!-- Modal Header -->
            <div class="px-6 py-5 border-b border-slate-800 flex items-center justify-between">
                <div>
                    <h2 class="text-lg font-bold text-white">
                        {{ $editingBrand ? 'Edit Brand' : 'Create New Brand' }}
                    </h2>
                    <p class="text-xs text-slate-400 mt-0.5">
                        {{ $editingBrand ? 'Update brand settings below.' : 'Configure a new brand for the agent.' }}
                    </p>
                </div>
                <button type="button" wire:click="$set('showModal', false)"
                        class="text-slate-500 hover:text-slate-300 transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <!-- Modal Body -->
            <form wire:submit="save" class="px-6 py-5 overflow-y-auto flex-1">
                <div class="space-y-5">

                    <!-- Name -->
                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-1.5">Brand Name</label>
                        <input type="text" wire:model="name"
                               class="block w-full rounded-xl bg-slate-800/60 border border-slate-700 text-slate-100 placeholder-slate-500 px-4 py-2.5 text-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 focus:outline-none transition-all"
                               placeholder="Vumbi Ventures">
                        @error('name') <span class="text-xs text-rose-400 mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <!-- Website URL -->
                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-1.5">Website URL</label>
                        <input type="url" wire:model="websiteUrl"
                               class="block w-full rounded-xl bg-slate-800/60 border border-slate-700 text-slate-100 placeholder-slate-500 px-4 py-2.5 text-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 focus:outline-none transition-all"
                               placeholder="https://vumbiventures.com">
                        <p class="mt-1.5 text-xs text-slate-500">The website domain the agent will scan and track.</p>
                        @error('websiteUrl') <span class="text-xs text-rose-400 mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <!-- Domain Type -->
                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-1.5">Domain Type</label>
                        <select wire:model="domainType"
                                class="block w-full rounded-xl bg-slate-800/60 border border-slate-700 text-slate-100 px-4 py-2.5 text-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 focus:outline-none transition-all">
                            @foreach($domainTypes as $key => $domain)
                                <option value="{{ $key }}" class="bg-slate-900">{{ $domain['label'] }} – {{ $domain['description'] }}</option>
                            @endforeach
                        </select>
                        @error('domainType') <span class="text-xs text-rose-400 mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <!-- Config -->
                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-1.5">Configuration (JSON)</label>
                        <textarea wire:model="config" rows="6"
                                  class="block w-full rounded-xl bg-slate-800/60 border border-slate-700 text-slate-100 placeholder-slate-500 px-4 py-3 text-sm font-mono focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 focus:outline-none transition-all"
                                  placeholder='{"ga4_property_id": "123456789", "ga4_measurement_id": "G-XXXXXXXX"}'></textarea>
                        <p class="mt-1.5 text-xs text-slate-500">Required keys vary by domain type. Use valid JSON.</p>
                        @error('config') <span class="text-xs text-rose-400 mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <!-- Brand Voice -->
                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-1.5">Brand Voice</label>
                        <textarea wire:model="brandVoice" rows="4"
                                  class="block w-full rounded-xl bg-slate-800/60 border border-slate-700 text-slate-100 placeholder-slate-500 px-4 py-3 text-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 focus:outline-none transition-all"
                                  placeholder="Describe the tone, style, and personality of this brand..."></textarea>
                        @error('brandVoice') <span class="text-xs text-rose-400 mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <!-- Timezone -->
                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-1.5">Timezone</label>
                        <select wire:model="timezone"
                                class="block w-full rounded-xl bg-slate-800/60 border border-slate-700 text-slate-100 px-4 py-2.5 text-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 focus:outline-none transition-all">
                            <option value="Africa/Nairobi" class="bg-slate-900">Africa/Nairobi (EAT)</option>
                            <option value="Africa/Lagos" class="bg-slate-900">Africa/Lagos (WAT)</option>
                            <option value="Africa/Johannesburg" class="bg-slate-900">Africa/Johannesburg (SAST)</option>
                            <option value="Africa/Cairo" class="bg-slate-900">Africa/Cairo (EET)</option>
                            <option value="UTC" class="bg-slate-900">UTC</option>
                        </select>
                        @error('timezone') <span class="text-xs text-rose-400 mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <!-- Active Status -->
                    <div class="flex items-center gap-3 pt-2">
                        <input type="checkbox" wire:model="isActive" id="isActive"
                               class="w-4 h-4 rounded border-slate-600 bg-slate-800 text-emerald-500 focus:ring-emerald-500/30 focus:ring-offset-0">
                        <label for="isActive" class="text-sm text-slate-300 cursor-pointer">
                            Brand is active and visible to the agent
                        </label>
                    </div>
                </div>

                <!-- Modal Footer -->
                <div class="mt-8 flex justify-end gap-3 pt-5 border-t border-slate-800">
                    <button type="button" wire:click="$set('showModal', false)"
                            class="px-5 py-2.5 text-sm font-medium text-slate-300 bg-slate-800/60 hover:bg-slate-800 rounded-xl border border-slate-700 transition-all">
                        Cancel
                    </button>
                    <button type="submit"
                            class="px-5 py-2.5 text-sm font-semibold text-white bg-emerald-500 hover:bg-emerald-400 rounded-xl shadow-lg shadow-emerald-500/20 transition-all">
                        {{ $editingBrand ? 'Update Brand' : 'Create Brand' }}
                    </button>
                </div>
            </form>
        </div>
    </div>
    @endif
</div>