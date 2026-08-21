<div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden mb-6">
    <div class="px-6 py-4 border-b border-gray-200">
        <h2 class="text-lg font-semibold text-gray-900">🔍 Filter Analytics</h2>
    </div>
    <div class="p-6">
        <form method="GET" action="{{ route('analytics.index') }}" class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <!-- Period Selector -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Time Period</label>
                <select name="period" id="period" class="w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500">
                    <option value="today" {{ $period === 'today' ? 'selected' : '' }}>Today</option>
                    <option value="yesterday" {{ $period === 'yesterday' ? 'selected' : '' }}>Yesterday</option>
                    <option value="7d" {{ $period === '7d' ? 'selected' : '' }}>Last 7 Days</option>
                    <option value="14d" {{ $period === '14d' ? 'selected' : '' }}>Last 14 Days</option>
                    <option value="30d" {{ $period === '30d' ? 'selected' : '' }}>Last 30 Days</option>
                    <option value="60d" {{ $period === '60d' ? 'selected' : '' }}>Last 60 Days</option>
                    <option value="90d" {{ $period === '90d' ? 'selected' : '' }}>Last 90 Days</option>
                    <option value="this_month" {{ $period === 'this_month' ? 'selected' : '' }}>This Month</option>
                    <option value="last_month" {{ $period === 'last_month' ? 'selected' : '' }}>Last Month</option>
                    <option value="this_year" {{ $period === 'this_year' ? 'selected' : '' }}>This Year</option>
                    <option value="custom" {{ $period === 'custom' ? 'selected' : '' }}>Custom Range</option>
                </select>
            </div>

            <!-- Custom Date Range -->
            <div class="md:col-span-2">
                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Start Date</label>
                        <input type="date" name="start_date" id="start_date" 
                               value="{{ $startDate ? $startDate->format('Y-m-d') : '' }}"
                               class="w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500"
                               {{ $period !== 'custom' ? 'disabled' : '' }}>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">End Date</label>
                        <input type="date" name="end_date" id="end_date" 
                               value="{{ $endDate ? $endDate->format('Y-m-d') : '' }}"
                               class="w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500"
                               {{ $period !== 'custom' ? 'disabled' : '' }}>
                    </div>
                </div>
            </div>

            <!-- Metric Selector -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Metric</label>
                <select name="metric" class="w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500">
                    <option value="all" {{ $metric === 'all' ? 'selected' : '' }}>All Metrics</option>
                    <option value="visitors" {{ $metric === 'visitors' ? 'selected' : '' }}>Visitors</option>
                    <option value="page_views" {{ $metric === 'page_views' ? 'selected' : '' }}>Page Views</option>
                    <option value="sessions" {{ $metric === 'sessions' ? 'selected' : '' }}>Sessions</option>
                    <option value="leads" {{ $metric === 'leads' ? 'selected' : '' }}>Leads</option>
                    <option value="conversions" {{ $metric === 'conversions' ? 'selected' : '' }}>Conversions</option>
                    <option value="revenue" {{ $metric === 'revenue' ? 'selected' : '' }}>Revenue</option>
                </select>
            </div>

            <!-- Submit Button -->
            <div class="md:col-span-4 flex justify-end space-x-2">
                <a href="{{ route('analytics.index') }}" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition">
                    Reset
                </a>
                <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition">
                    Apply Filters
                </button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const periodSelect = document.getElementById('period');
    const startDate = document.getElementById('start_date');
    const endDate = document.getElementById('end_date');

    periodSelect.addEventListener('change', function() {
        const isCustom = this.value === 'custom';
        startDate.disabled = !isCustom;
        endDate.disabled = !isCustom;
        
        if (isCustom) {
            startDate.required = true;
            endDate.required = true;
        } else {
            startDate.required = false;
            endDate.required = false;
        }
    });
});
</script>