<form method="POST" action="{{ $action }}" class="mt-3 grid md:grid-cols-2 gap-3 text-sm">
    @csrf
    @if($method !== 'POST') @method($method) @endif
    <input name="name" required maxlength="80" value="{{ old('name', $p?->name) }}" placeholder="Name, e.g. First-time safari planner" class="border rounded px-2 py-1">
    <input name="role_title" maxlength="120" value="{{ old('role_title', $p?->role_title) }}" placeholder="Role or situation" class="border rounded px-2 py-1">
    <textarea name="summary" rows="2" maxlength="1000" placeholder="One-paragraph summary" class="border rounded px-2 py-1 md:col-span-2">{{ old('summary', $p?->summary) }}</textarea>
    @foreach(['goals' => 'Goals / jobs to be done', 'triggers' => 'Triggers', 'pains' => 'Pains, most painful first', 'objections' => 'Objections', 'motivations' => 'Motivations & values', 'channels' => 'Where they look for information'] as $f => $label)
        <label class="block"><span class="text-xs text-gray-500">{{ $label }} (one per line)</span>
            <textarea name="{{ $f }}" rows="3" maxlength="2000" class="border rounded px-2 py-1 w-full">{{ old($f, implode("\n", $p?->$f ?? [])) }}</textarea></label>
    @endforeach
    <select name="buying_role" class="border rounded px-2 py-1">
        <option value="">Buying role (optional)</option>
        @foreach(['decision_maker' => 'Decision maker', 'influencer' => 'Influencer', 'user' => 'End user', 'gatekeeper' => 'Gatekeeper'] as $k => $l)
            <option value="{{ $k }}" @selected(old('buying_role', $p?->buying_role) === $k)>{{ $l }}</option>
        @endforeach
    </select>
    <input name="quote" maxlength="300" value="{{ old('quote', $p?->quote) }}" placeholder="A real quote in their words (optional)" class="border rounded px-2 py-1">
    <label class="flex items-center gap-2"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $p?->is_active ?? true))> Active (shown to the AI agents)</label>
    <div class="md:col-span-2"><button class="px-4 py-2 bg-blue-600 text-white rounded-lg">{{ $p ? 'Save changes' : 'Add persona' }}</button></div>
</form>
