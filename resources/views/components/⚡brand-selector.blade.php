<?php

namespace App\Livewire;

use App\Models\Brand;
use Livewire\Component;
use Illuminate\Support\Facades\Auth;

new class extends Component
{
    public $brands = [];
    public $activeBrandId = null;

    public function mount()
    {
        $this->loadBrands();
    }

    public function loadBrands()
    {
        $user = Auth::user();
        $this->brands = $user->brands()->get();
        $this->activeBrandId = $user->active_brand_id;
    }

    public function switchBrand($brandId)
    {
        $user = Auth::user();
        $brand = Brand::findOrFail($brandId);

        if (!$user->hasAccessTo($brand)) {
            session()->flash('error', 'You do not have access to this brand.');
            return;
        }

        $user->switchBrand($brand);
        $this->activeBrandId = $brandId;
        $this->loadBrands();

        $this->dispatch('brand-switched', brandId: $brandId);

        return redirect()->route('dashboard', ['brand' => $brand->slug]);
    }
};
?>

<div x-data="{ open: false }" class="relative">
    <button @click="open = !open" 
            class="flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-green-500">
        <span>{{ $brands->firstWhere('id', $activeBrandId)?->name ?? 'Select Brand' }}</span>
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
        </svg>
    </button>

    <div x-show="open" 
         @click.away="open = false"
         class="absolute right-0 mt-2 w-64 bg-white rounded-lg shadow-lg border border-gray-200 z-50">
        <div class="py-1">
            @foreach($brands as $brand)
                <a href="#" 
                   wire:click.prevent="switchBrand({{ $brand->id }})"
                   class="block px-4 py-2 text-sm hover:bg-gray-100 {{ $activeBrandId == $brand->id ? 'bg-green-50 text-green-700' : 'text-gray-700' }}">
                    <div class="flex items-center justify-between">
                        <span>{{ $brand->name }}</span>
                        @if($activeBrandId == $brand->id)
                            <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                        @endif
                    </div>
                    <span class="text-xs text-gray-500">{{ ucfirst($brand->domain_type) }}</span>
                </a>
            @endforeach

            @if(auth()->user()->can('manage-brand', auth()->user()->activeBrand))
                <div class="border-t border-gray-200"></div>
                <a href="{{ route('brands.index') }}" 
                   class="block px-4 py-2 text-sm text-green-600 hover:bg-gray-100">
                    + Manage Brands
                </a>
            @endif
        </div>
    </div>
</div>