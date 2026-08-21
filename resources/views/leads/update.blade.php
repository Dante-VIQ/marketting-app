@extends('layouts.app')

@section('title', 'Complete Your Inquiry')

@section('content')
<div class="py-12 px-4">
    <div class="max-w-2xl mx-auto">
        <div class="bg-white rounded-lg shadow-lg overflow-hidden">
            <div class="bg-green-600 px-6 py-4">
                <h1 class="text-2xl font-bold text-white">Complete Your Inquiry</h1>
                <p class="text-green-100">Help us serve you better</p>
            </div>

            <div class="p-6">
                <p class="text-gray-600 mb-6">
                    Hi {{ $lead->first_name ?? 'there' }}, we noticed a few details were missing from your inquiry.
                    Please fill them in below so we can assist you better.
                </p>

                <form method="POST" action="{{ route('leads.update.post', ['lead' => $lead->id]) }}">
                    @csrf

                    @php
                        $missingFields = $lead->missing_fields ?? [];
                    @endphp

                    @foreach($missingFields as $field)
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700">
                                {{ $field['label'] ?? $field['field'] }}
                            </label>
                            @if($field['field'] === 'project_description' || $field['field'] === 'message')
                                <textarea name="{{ $field['field'] }}" rows="3"
                                          class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500"
                                          placeholder="{{ $field['question'] ?? '' }}"></textarea>
                            @elseif($field['field'] === 'preferred_date')
                                <input type="date" name="{{ $field['field'] }}"
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500">
                            @elseif($field['field'] === 'number_of_people' || $field['field'] === 'duration_days' || $field['field'] === 'team_size')
                                <input type="number" name="{{ $field['field'] }}" min="1"
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500"
                                       placeholder="{{ $field['question'] ?? '' }}">
                            @else
                                <input type="text" name="{{ $field['field'] }}"
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500"
                                       placeholder="{{ $field['question'] ?? '' }}">
                            @endif
                            <p class="mt-1 text-sm text-gray-500">{{ $field['description'] ?? '' }}</p>
                        </div>
                    @endforeach

                    <button type="submit" class="w-full bg-green-600 text-white py-3 rounded-lg hover:bg-green-700 transition">
                        ✅ Submit Updated Information
                    </button>
                </form>

                <div class="mt-4 text-center text-sm text-gray-500">
                    <p>Or simply reply to the email we sent you with the information.</p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection