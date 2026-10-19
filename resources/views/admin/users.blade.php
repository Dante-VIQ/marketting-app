@extends('layouts.app')

@section('title', 'User Approvals')

@php
    $btn = 'px-3 py-1 text-xs rounded text-white';
@endphp

@section('content')
<div class="py-6 px-4 sm:px-6 lg:px-8 space-y-6 max-w-5xl">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">👥 User Approvals</h1>
        <p class="text-gray-600">New sign-ups wait here until an approver lets them in.
            @if($isSuperAdmin) As platform administrator you can also appoint approvers from people you trust. @endif</p>
    </div>

    @if(session('message'))<div class="p-3 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm">{{ session('message') }}</div>@endif
    @if($errors->any())<div class="p-3 rounded-lg bg-red-50 border border-red-200 text-red-800 text-sm">@foreach($errors->all() as $e)<p>{{ $e }}</p>@endforeach</div>@endif

    <div class="bg-white border rounded-lg p-4">
        <h2 class="font-semibold text-gray-900">Waiting for approval ({{ $pending->count() }})</h2>
        @forelse($pending as $u)
            <div class="py-2 border-t flex flex-wrap items-center justify-between gap-2 text-sm">
                <div><p class="text-gray-900">{{ $u->name }}</p><p class="text-xs text-gray-500">{{ $u->email }} · registered {{ $u->created_at->diffForHumans() }}</p></div>
                <div class="flex gap-2">
                    <form method="POST" action="{{ route('admin.users.approve', $u->id) }}">@csrf<button class="{{ $btn }} bg-emerald-600">Approve</button></form>
                    <form method="POST" action="{{ route('admin.users.reject', $u->id) }}">@csrf<button class="{{ $btn }} bg-red-600">Reject</button></form>
                </div>
            </div>
        @empty
            <p class="text-sm text-gray-600 mt-1">No one is waiting.</p>
        @endforelse
    </div>

    <div class="bg-white border rounded-lg p-4">
        <h2 class="font-semibold text-gray-900">Approved ({{ $approved->count() }})</h2>
        @foreach($approved as $u)
            @php $r = $roles($u); @endphp
            <div class="py-2 border-t flex flex-wrap items-center justify-between gap-2 text-sm">
                <div><p class="text-gray-900">{{ $u->name }}
                        @if($r['super_admin'])<span class="ml-1 px-2 py-0.5 rounded-full text-xs bg-purple-100 text-purple-800">Platform admin</span>@endif
                        @if($r['approver'])<span class="ml-1 px-2 py-0.5 rounded-full text-xs bg-sky-100 text-sky-800">Approver</span>@endif</p>
                    <p class="text-xs text-gray-500">{{ $u->email }}</p></div>
                <div class="flex gap-2">
                    @if($isSuperAdmin && !$r['super_admin'])
                        @if($r['approver'])
                            <form method="POST" action="{{ route('admin.users.remove-approver', $u->id) }}">@csrf<button class="{{ $btn }} bg-gray-600">Remove approver</button></form>
                        @else
                            <form method="POST" action="{{ route('admin.users.make-approver', $u->id) }}">@csrf<button class="{{ $btn }} bg-sky-600">Make approver</button></form>
                        @endif
                    @endif
                    @if(!$r['super_admin'] && $u->id !== $me)
                        <form method="POST" action="{{ route('admin.users.reject', $u->id) }}" onsubmit="return confirm('Revoke access for {{ addslashes($u->name) }}?')">@csrf<button class="{{ $btn }} bg-red-600">Revoke</button></form>
                    @endif
                </div>
            </div>
        @endforeach
    </div>

    @if($rejected->isNotEmpty())
        <div class="bg-white border rounded-lg p-4">
            <h2 class="font-semibold text-gray-900">Rejected ({{ $rejected->count() }})</h2>
            @foreach($rejected as $u)
                <div class="py-2 border-t flex flex-wrap items-center justify-between gap-2 text-sm">
                    <div><p class="text-gray-900">{{ $u->name }}</p><p class="text-xs text-gray-500">{{ $u->email }}</p></div>
                    <form method="POST" action="{{ route('admin.users.approve', $u->id) }}">@csrf<button class="{{ $btn }} bg-emerald-600">Approve instead</button></form>
                </div>
            @endforeach
        </div>
    @endif

    <p class="text-xs text-gray-500">Approving lets someone sign in. It does not give them access to any brand; add them to a brand from Brand Management.</p>
</div>
@endsection
