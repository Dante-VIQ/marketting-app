@extends('layouts.app')

@section('title', 'Security')

@section('content')
<div class="max-w-2xl mx-auto py-8 px-4 space-y-6" x-data="passkeyPage()">
    <div>
        <h1 class="text-2xl font-semibold text-gray-900 dark:text-slate-100">Security</h1>
        <p class="text-sm text-gray-600 dark:text-slate-400 mt-1">A passkey lets you sign in with your fingerprint, face or device PIN instead of typing a password. Your password keeps working.</p>
    </div>

    <div class="bg-white dark:bg-slate-900 border border-gray-200 dark:border-slate-700 rounded-lg p-4 space-y-3">
        <h2 class="font-semibold text-gray-900 dark:text-slate-100">Your passkeys</h2>
        @forelse($passkeys as $pk)
            <div class="flex items-center justify-between gap-3 text-sm border-b border-gray-100 dark:border-slate-800 pb-2" data-passkey="{{ $pk->id }}">
                <div>
                    <p class="font-medium text-gray-900 dark:text-slate-100">{{ $pk->name }}</p>
                    <p class="text-xs text-gray-500">Added {{ $pk->created_at?->format('Y-m-d') }} · {{ $pk->last_used_at ? 'last used ' . $pk->last_used_at->diffForHumans() : 'never used' }}</p>
                </div>
                <button type="button" @click="remove({{ $pk->id }}, $el)" class="text-xs px-2 py-1 rounded border border-red-300 text-red-700">Remove</button>
            </div>
        @empty
            <p class="text-sm text-gray-500">You have no passkeys yet.</p>
        @endforelse
    </div>

    <div class="bg-white dark:bg-slate-900 border border-gray-200 dark:border-slate-700 rounded-lg p-4 space-y-3" x-show="supported" x-cloak>
        <h2 class="font-semibold text-gray-900 dark:text-slate-100">Add a passkey</h2>
        <div class="flex flex-wrap gap-2">
            <input x-model="name" maxlength="60" placeholder="Name it, e.g. My phone" class="border border-gray-300 dark:border-slate-600 rounded px-2 py-1 text-sm bg-white dark:bg-slate-800 text-gray-900 dark:text-slate-100 flex-1 min-w-[12rem]">
            <button type="button" :disabled="busy || !name.trim()" @click="add()" class="px-3 py-1 text-sm bg-emerald-600 text-white rounded disabled:opacity-40" x-text="busy ? 'Waiting for your device…' : 'Add passkey'"></button>
        </div>
        <p x-show="message" x-text="message" class="text-sm" :class="ok ? 'text-emerald-700' : 'text-red-600'"></p>
    </div>
    <p class="text-sm text-amber-700" x-show="!supported" x-cloak>This browser or device does not support passkeys. Your password still works.</p>
</div>

<script>
function passkeyPage() {
    return {
        supported: !!(window.Passkeys && window.Passkeys.isSupported()), name: '', busy: false, message: '', ok: false,
        async add() {
            this.busy = true; this.message = '';
            try { await window.Passkeys.register({ name: this.name.trim() }); window.location.reload(); }
            catch (e) { this.ok = false; this.message = (e && e.message) || 'The passkey could not be added.'; }
            this.busy = false;
        },
        async remove(id, el) {
            if (!confirm('Remove this passkey? You can still sign in with your password.')) return;
            const res = await fetch('/user/passkeys/' + id, { method: 'DELETE', headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'application/json' }, credentials: 'same-origin' });
            if (res.ok) { el.closest('[data-passkey]').remove(); } else { this.ok = false; this.message = 'Could not remove it. Reload the page and try again.'; }
        }
    };
}
</script>
@endsection
