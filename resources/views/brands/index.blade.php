@extends('layouts.app')

@section('title', 'Brand Management')

@section('content')

@if($brands->isEmpty())

    {{-- ── First-time user: show the onboarding wizard ────────────── --}}
    <div class="min-h-[80vh] flex flex-col justify-center">

        <div class="text-center mb-2">
            <h1 class="text-3xl font-extrabold text-white tracking-tight">Welcome to Vumbi AI</h1>
            <p class="mt-2 text-slate-400 text-sm">Let's set up your first brand in 3 quick steps.</p>
        </div>

        @livewire('brand-onboarding')

    </div>

@else

    {{-- ── Returning user: standard brand manager ─────────────────── --}}
    @if(session('onboarding_done'))
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-6">
            <div class="flex items-center gap-3 bg-emerald-500/10 border border-emerald-500/20 rounded-xl px-5 py-3 text-sm text-emerald-400 mb-4">
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <span><strong>{{ session('onboarding_done') }}</strong> is ready. Your AI brief will arrive at 6 AM tomorrow.</span>
            </div>
        </div>
    @endif

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-8">

            {{-- Brand Manager --}}
            <div class="bg-slate-900/60 border border-slate-800/60 rounded-2xl shadow-xl overflow-hidden">
                <div class="p-6">
                    @livewire('brand-manager')
                </div>
            </div>

            {{-- Team Members ─────────────────────────────────────────── --}}
            <div>
                <div class="mb-4">
                    <h2 class="text-base font-semibold text-white">Team Members</h2>
                    <p class="text-xs text-slate-400 mt-0.5">Invite collaborators to access and manage each brand.</p>
                </div>

                @if(session('message'))
                    <div class="flex items-center gap-3 bg-emerald-500/10 border border-emerald-500/20 rounded-xl px-5 py-3 text-sm text-emerald-400 mb-4">
                        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        {{ session('message') }}
                    </div>
                @endif

                @if($errors->any())
                    <div class="flex items-center gap-3 bg-red-500/10 border border-red-500/20 rounded-xl px-5 py-3 text-sm text-red-400 mb-4">
                        {{ $errors->first() }}
                    </div>
                @endif

                <div class="space-y-6">
                @foreach($brands as $brand)
                    @php $isAdmin = Auth::user()->isSuperAdmin() || $brand->isAdmin(Auth::user()); @endphp

                    <div class="bg-slate-900/60 border border-slate-800/60 rounded-2xl p-6">
                        <div class="flex items-center justify-between mb-5">
                            <h3 class="text-sm font-semibold text-white">{{ $brand->name }}</h3>
                            <span class="text-xs text-slate-500">{{ $brand->users_count }} {{ Str::plural('member', $brand->users_count) }}</span>
                        </div>

                        {{-- Current members --}}
                        <div class="space-y-2 mb-5">
                            @foreach($brand->users as $member)
                                <div class="flex items-center justify-between py-2 px-3 rounded-lg bg-slate-800/40">
                                    <div class="flex items-center gap-3 min-w-0">
                                        <div class="w-7 h-7 rounded-full bg-emerald-500/20 flex items-center justify-center shrink-0">
                                            <span class="text-xs font-bold text-emerald-400">{{ strtoupper(substr($member->name, 0, 1)) }}</span>
                                        </div>
                                        <div class="min-w-0">
                                            <p class="text-sm text-white truncate">{{ $member->name }}
                                                @if($member->id === Auth::id())
                                                    <span class="text-xs text-slate-500">(you)</span>
                                                @endif
                                            </p>
                                            <p class="text-xs text-slate-500 truncate">{{ $member->email }}</p>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-2 shrink-0">
                                        @php
                                            $memberRole = $member->roles()
                                                ->where('team_id', $brand->id)
                                                ->first()?->name ?? 'member';
                                        @endphp
                                        <span class="text-xs px-2 py-0.5 rounded-full bg-slate-700 text-slate-300">{{ $memberRole }}</span>
                                        @if($isAdmin && $member->id !== Auth::id())
                                            <form method="POST" action="{{ route('brands.members.remove', $brand) }}"
                                                  onsubmit="return confirm('Remove {{ $member->name }} from {{ $brand->name }}?')">
                                                @csrf
                                                <input type="hidden" name="user_id" value="{{ $member->id }}">
                                                <button type="submit"
                                                        class="text-xs text-red-400 hover:text-red-300 px-2 py-0.5 rounded hover:bg-red-500/10 transition-colors">
                                                    Remove
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        {{-- Pending invitations --}}
                        @php $invites = $pendingInvitations->get($brand->id, collect()); @endphp
                        @if($invites->isNotEmpty())
                            <div class="mb-5">
                                <p class="text-xs font-medium text-slate-500 uppercase tracking-wider mb-2">Pending Invitations</p>
                                <div class="space-y-1.5">
                                    @foreach($invites as $inv)
                                        <div class="flex items-center justify-between py-1.5 px-3 rounded-lg bg-amber-500/5 border border-amber-500/20">
                                            <div>
                                                <span class="text-sm text-slate-300">{{ $inv->email }}</span>
                                                <span class="ml-2 text-xs px-2 py-0.5 rounded-full bg-slate-700 text-slate-400">{{ $inv->role }}</span>
                                                <span class="ml-2 text-xs text-slate-500">expires {{ $inv->expires_at->diffForHumans() }}</span>
                                            </div>
                                            @if($isAdmin)
                                                <form method="POST" action="{{ route('brands.invitations.revoke', $inv) }}">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="text-xs text-slate-500 hover:text-red-400 transition-colors">Revoke</button>
                                                </form>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        {{-- Invite form --}}
                        @if($isAdmin)
                            <form method="POST" action="{{ route('brands.invite', $brand) }}" class="flex flex-col sm:flex-row gap-2">
                                @csrf
                                <input type="email" name="email" placeholder="colleague@email.com" required
                                       class="flex-1 bg-slate-800 border border-slate-700 rounded-lg px-3 py-2 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500/40">
                                <select name="role"
                                        class="bg-slate-800 border border-slate-700 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-emerald-500">
                                    <option value="editor">Editor</option>
                                    <option value="admin">Admin</option>
                                    <option value="viewer">Viewer</option>
                                </select>
                                <button type="submit"
                                        class="bg-emerald-500 hover:bg-emerald-400 text-slate-900 font-semibold text-sm px-4 py-2 rounded-lg transition-colors whitespace-nowrap">
                                    Send Invite
                                </button>
                            </form>
                        @else
                            <p class="text-xs text-slate-500">Only brand admins can invite members.</p>
                        @endif
                    </div>
                @endforeach
                </div>
            </div>

            {{-- Integrations ──────────────────────────────────────────── --}}
            <div>
                <div class="mb-4">
                    <h2 class="text-base font-semibold text-white">Integrations</h2>
                    <p class="text-xs text-slate-400 mt-0.5">Connect third-party data sources to enrich AI briefs and content planning.</p>
                </div>

                <div class="space-y-6">
                    @foreach($brands as $brand)
                        <div>
                            <p class="text-xs font-medium text-slate-400 uppercase tracking-wider mb-2">{{ $brand->name }}</p>
                            <x-bing-webmaster-panel :brand="$brand" />
                        </div>
                    @endforeach
                </div>
            </div>

        </div>
    </div>

@endif

@endsection
