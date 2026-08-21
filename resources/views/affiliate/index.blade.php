@extends('layouts.app')

@section('title', 'Affiliate Dashboard')

@section('content')
<div class="py-6 px-4 sm:px-6 lg:px-8">
    <div class="mb-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">🤝 Affiliate Revenue</h1>
                <p class="text-gray-600">Track your affiliate partner performance</p>
            </div>
            <div class="flex space-x-2">
                <form action="{{ route('affiliate.collect') }}" method="POST">
                    @csrf
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                        🔄 Collect Data
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Totals Cards -->
    <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-6">
        <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200">
            <p class="text-sm text-gray-500">Total Clicks</p>
            <p class="text-2xl font-bold text-blue-600">{{ number_format($totals['clicks'] ?? 0) }}</p>
        </div>
        <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200">
            <p class="text-sm text-gray-500">Total Bookings</p>
            <p class="text-2xl font-bold text-green-600">{{ number_format($totals['bookings'] ?? 0) }}</p>
        </div>
        <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200">
            <p class="text-sm text-gray-500">Commission Earned</p>
            <p class="text-2xl font-bold text-yellow-600">${{ number_format($totals['commission'] ?? 0, 2) }}</p>
        </div>
        <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200">
            <p class="text-sm text-gray-500">Revenue Generated</p>
            <p class="text-2xl font-bold text-purple-600">${{ number_format($totals['revenue'] ?? 0, 2) }}</p>
        </div>
        <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200">
            <p class="text-sm text-gray-500">Conversion Rate</p>
            <p class="text-2xl font-bold text-orange-600">{{ number_format($totals['conversion_rate'] ?? 0, 1) }}%</p>
        </div>
    </div>

    <!-- Network Summary -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden mb-6">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-900">📊 Performance by Network</h2>
        </div>
        <div class="p-6">
            @if(!empty($summary))
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    @foreach($summary as $network => $data)
                        <div class="p-4 bg-gray-50 rounded-lg border border-gray-200">
                            <p class="text-sm font-medium text-gray-700">{{ ucfirst(str_replace('_', ' ', $network)) }}</p>
                            <div class="mt-2 space-y-1 text-sm">
                                <div class="flex justify-between">
                                    <span class="text-gray-500">Clicks</span>
                                    <span class="font-medium">{{ number_format($data['clicks'] ?? 0) }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-500">Bookings</span>
                                    <span class="font-medium">{{ number_format($data['bookings'] ?? 0) }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-500">Commission</span>
                                    <span class="font-medium text-green-600">${{ number_format($data['commission_earned'] ?? 0, 2) }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-500">Revenue</span>
                                    <span class="font-medium text-blue-600">${{ number_format($data['revenue_generated'] ?? 0, 2) }}</span>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="text-gray-500 text-center py-4">No affiliate data available yet.</p>
            @endif
        </div>
    </div>

    <!-- Daily Data Table -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-900">📅 Daily Performance</h2>
        </div>
        <div class="p-6">
            @if($dailyData->count() > 0)
                <div class="overflow-x-auto">
                    <table class="min-w-full">
                        <thead>
                            <tr class="text-left text-xs text-gray-500 uppercase">
                                <th class="pb-2 font-semibold">Date</th>
                                <th class="pb-2 font-semibold">Network</th>
                                <th class="pb-2 text-right font-semibold">Clicks</th>
                                <th class="pb-2 text-right font-semibold">Bookings</th>
                                <th class="pb-2 text-right font-semibold">Commission</th>
                                <th class="pb-2 text-right font-semibold">Revenue</th>
                                <th class="pb-2 text-right font-semibold">Conversion Rate</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach($dailyData as $row)
                                <tr>
                                    <td class="py-2 text-sm text-gray-700">{{ $row->date->format('M d, Y') }}</td>
                                    <td class="py-2 text-sm text-gray-700">{{ ucfirst(str_replace('_', ' ', $row->network)) }}</td>
                                    <td class="py-2 text-sm text-gray-700 text-right">{{ number_format($row->clicks) }}</td>
                                    <td class="py-2 text-sm text-gray-700 text-right">{{ number_format($row->bookings) }}</td>
                                    <td class="py-2 text-sm text-green-600 text-right">${{ number_format($row->commission_earned, 2) }}</td>
                                    <td class="py-2 text-sm text-blue-600 text-right">${{ number_format($row->revenue_generated, 2) }}</td>
                                    <td class="py-2 text-sm text-gray-700 text-right">{{ number_format($row->conversion_rate, 1) }}%</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <p class="text-gray-500 text-center py-4">No daily data available yet. Click "Collect Data" to fetch affiliate data.</p>
            @endif
        </div>
    </div>
</div>
@endsection