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
        $this->websiteUrl = $brand->website_url; // Add this
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
                'website_url' => $this->websiteUrl, // Add this
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
                'website_url' => $this->websiteUrl, // Add this
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
        $this->domainType = 'general';
        $this->config = '';
        $this->brandVoice = config('brand.defaults.brand_voice');
        $this->timezone = config('brand.defaults.timezone');
        $this->isActive = true;
        $this->resetErrorBag();
    }
};
?>

<div>
    <!-- Header -->
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Brand Management</h1>
        <button wire:click="create" 
                class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition">
            + New Brand
        </button>
    </div>

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

    <!-- Brand List -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Brand</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Domain</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Users</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @forelse($brands as $brand)
                <tr>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="text-sm font-medium text-gray-900">{{ $brand->name }}</div>
                        <div class="text-sm text-gray-500">{{ $brand->slug }}</div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="px-2 py-1 text-xs rounded-full bg-blue-100 text-blue-800">
                            {{ ucfirst($brand->domain_type) }}
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="px-2 py-1 text-xs rounded-full {{ $brand->is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                            {{ $brand->is_active ? 'Active' : 'Inactive' }}
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        {{ $brand->users_count }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                        <button wire:click="edit({{ $brand->id }})" 
                                class="text-indigo-600 hover:text-indigo-900 mr-3">
                            Edit
                        </button>
                        <button wire:click="toggleActive({{ $brand->id }})" 
                                class="text-{{ $brand->is_active ? 'yellow' : 'green' }}-600 hover:text-{{ $brand->is_active ? 'yellow' : 'green' }}-900 mr-3">
                            {{ $brand->is_active ? 'Deactivate' : 'Activate' }}
                        </button>
                        <button wire:click="deleteBrand({{ $brand->id }})" 
                                wire:confirm="Are you sure you want to delete {{ $brand->name }}?"
                                class="text-red-600 hover:text-red-900">
                            Delete
                        </button>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" class="px-6 py-4 text-center text-gray-500">
                        No brands found. Create your first brand!
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- Modal -->
    @if($showModal)
    <div class="fixed inset-0 bg-gray-500 bg-opacity-75 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
            <div class="px-6 py-4 border-b border-gray-200 sticky top-0 bg-white z-10">
                <h2 class="text-xl font-semibold text-gray-900">
                    {{ $editingBrand ? 'Edit Brand' : 'Create New Brand' }}
                </h2>
            </div>

            <form wire:submit="save" class="px-6 py-4">
                <div class="space-y-4">
                    <!-- Name -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Brand Name</label>
                        <input type="text" wire:model="name" 
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500">
                        @error('name') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
                    </div>

                    <!-- Website URL -->
                    <div>
                    <label class="block text-sm font-medium text-gray-700">Website URL</label>
                    <input type="url" wire:model="websiteUrl"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500"
                    placeholder="https://vumbiventures.com">
                    <p class="mt-1 text-sm text-gray-500">The website domain to track in Ahrefs</p>
                    @error('websiteUrl') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
                    </div>

                    <!-- Domain Type -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Domain Type</label>
                        <select wire:model="domainType" 
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500">
                            @foreach($domainTypes as $key => $domain)
                                <option value="{{ $key }}">{{ $domain['label'] }} - {{ $domain['description'] }}</option>
                            @endforeach
                        </select>
                        @error('domainType') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
                    </div>

                    <!-- Config -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Configuration (JSON)</label>
                        <textarea wire:model="config" rows="6"
                                  class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500 font-mono text-sm"
                                  placeholder='{"ga4_property_id": "123456789", "ga4_measurement_id": "G-XXXXXXXX"}'>
                        </textarea>
                        @error('config') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
                        <p class="mt-1 text-sm text-gray-500">Required keys vary by domain type.</p>
                    </div>

                    <!-- Brand Voice -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Brand Voice</label>
                        <textarea wire:model="brandVoice" rows="4"
                                  class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500"
                                  placeholder="Describe the tone, style, and personality of this brand...">
                        </textarea>
                        @error('brandVoice') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
                    </div>

                    <!-- Timezone -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Timezone</label>
                        <select wire:model="timezone" 
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500">
                            <option value="Africa/Nairobi">Africa/Nairobi (EAT)</option>
                            <option value="Africa/Lagos">Africa/Lagos (WAT)</option>
                            <option value="Africa/Johannesburg">Africa/Johannesburg (SAST)</option>
                            <option value="Africa/Cairo">Africa/Cairo (EET)</option>
                            <option value="UTC">UTC</option>
                        </select>
                        @error('timezone') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
                    </div>

                    <!-- Active Status -->
                    <div class="flex items-center">
                        <input type="checkbox" wire:model="isActive" 
                               class="rounded border-gray-300 text-green-600 shadow-sm focus:ring-green-500">
                        <label class="ml-2 block text-sm text-gray-700">Active</label>
                    </div>
                </div>

                <div class="mt-6 flex justify-end space-x-3 border-t border-gray-200 pt-4">
                    <button type="button" wire:click="$set('showModal', false)" 
                            class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">
                        Cancel
                    </button>
                    <button type="submit" 
                            class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition">
                        {{ $editingBrand ? 'Update' : 'Create' }}
                    </button>
                </div>
            </form>
        </div>
    </div>
    @endif
</div>
