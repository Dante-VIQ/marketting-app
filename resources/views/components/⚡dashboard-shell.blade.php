<?php

namespace App\Livewire;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;

new class extends Component
{
    public $brandId = null;
    public $brandName = null;
    public $dashboardData = [];
    public $topPages = [];

    protected $listeners = ['brand-switched' => 'updateBrand'];

    public function mount(DashboardDataService $dashboardDataService)
    {
        $this->updateBrand(Auth::user()->active_brand_id, $dashboardDataService);
    }

    public function updateBrand($brandId, DashboardDataService $dashboardDataService = null)
    {
        $user = Auth::user();
        $brand = $user->brands()->find($brandId);

        if ($brand) {
            $this->brandId = $brand->id;
            $this->brandName = $brand->name;
            
            // Load dashboard data
            if ($dashboardDataService) {
                $this->dashboardData = $dashboardDataService->getDashboardData($brand);
                $this->topPages = $dashboardDataService->getTopPages($brand);
            }
        } else {
            $this->brandId = null;
            $this->brandName = null;
            $this->dashboardData = [];
            $this->topPages = [];
        }
    }
};
?>

<div>
    <!-- Dashboard Shell Content -->
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <!-- Brand Header -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="text-2xl font-bold text-gray-900">
                                {{ $brandName ?? 'No Brand Selected' }}
                            </h2>
                            <p class="text-sm text-gray-500 mt-1">
                                @if($brandId)
                                    Last updated: {{ $dashboardData['last_updated'] ?? 'N/A' }}
                                @else
                                    Select a brand to get started
                                @endif
                            </p>
                        </div>
                        <div class="flex items-center space-x-2">
                            <span class="px-3 py-1 text-sm rounded-full bg-green-100 text-green-800">
                                {{ $brandId ? 'Active' : 'Inactive' }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Dashboard Content -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    @if($brandId)
                        <!-- KPI Cards -->
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                            <div class="bg-gray-50 p-4 rounded-lg border border-gray-200">
                                <p class="text-sm text-gray-500">Visitors (7 days)</p>
                                <p class="text-2xl font-bold text-blue-600">
                                    {{ number_format($dashboardData['visitors'] ?? 0) }}
                                </p>
                            </div>
                            <div class="bg-gray-50 p-4 rounded-lg border border-gray-200">
                                <p class="text-sm text-gray-500">Leads (7 days)</p>
                                <p class="text-2xl font-bold text-green-600">
                                    {{ number_format($dashboardData['leads'] ?? 0) }}
                                </p>
                            </div>
                            <div class="bg-gray-50 p-4 rounded-lg border border-gray-200">
                                <p class="text-sm text-gray-500">Social Reach (7 days)</p>
                                <p class="text-2xl font-bold text-purple-600">
                                    {{ number_format($dashboardData['social_reach'] ?? 0) }}
                                </p>
                            </div>
                            <div class="bg-gray-50 p-4 rounded-lg border border-gray-200">
                                <p class="text-sm text-gray-500">SEO Impressions (7 days)</p>
                                <p class="text-2xl font-bold text-orange-600">
                                    {{ number_format($dashboardData['seo_impressions'] ?? 0) }}
                                </p>
                            </div>
                        </div>

                        <!-- Top Pages -->
                        @if(!empty($topPages))
                        <div class="mb-6">
                            <h3 class="text-lg font-semibold text-gray-700 mb-3">Top Pages</h3>
                            <div class="bg-gray-50 rounded-lg border border-gray-200 p-4">
                                <table class="min-w-full">
                                    <thead>
                                        <tr class="text-left text-xs text-gray-500 uppercase">
                                            <th class="pb-2">Page</th>
                                            <th class="pb-2 text-right">Visitors</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($topPages as $page)
                                        <tr>
                                            <td class="py-1 text-sm text-gray-700">{{ $page['dimension'] }}</td>
                                            <td class="py-1 text-sm text-gray-700 text-right">{{ number_format($page['total_visitors']) }}</td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        @endif

                        <!-- Status Message -->
                        <div class="bg-blue-50 p-4 rounded-lg border border-blue-200">
                            <p class="text-blue-700">✅ Analytics collection is active. Data is being collected daily at 5:30 AM.</p>
                            <p class="text-sm text-blue-600 mt-1">Next: Phase 2 - The Analyst (AI Strategic Advisor)</p>
                        </div>
                    @else
                        <div class="bg-yellow-50 p-4 rounded-lg border border-yellow-200">
                            <p class="text-yellow-700">⚠️ No brand selected. Please select a brand from the dropdown.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>