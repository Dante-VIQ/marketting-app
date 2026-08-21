@extends('layouts.app')

@section('title', 'Brand Management')

@section('content')
<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6">
                @livewire('brand-manager')
            </div>
        </div>
    </div>
</div>
@endsection