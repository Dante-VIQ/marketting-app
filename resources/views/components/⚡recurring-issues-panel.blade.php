<?php

use App\Models\AiAction;
use Livewire\Component;
use Illuminate\Support\Facades\Auth;

new class extends Component
{
    public $brandId;
    public $escalations = [];
    public $selectedEscalation = null;
    public $responseNotes = '';
    public $snoozeDays = 7;

    protected $listeners = ['brand-switched' => 'loadEscalations'];

    public function mount()
    {
        $this->brandId = Auth::user()->active_brand_id;
        $this->loadEscalations();
    }

    public function loadEscalations()
    {
        if (!$this->brandId) {
            $this->escalations = [];
            return;
        }

        $this->escalations = AiAction::where('brand_id', $this->brandId)
            ->where('category', 'escalation')
            ->whereNull('human_response')
            ->orderBy('priority', 'desc')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($action) {
                $payload = json_decode($action->suggested_content, true) ?? [];
                return [
                    'id'               => $action->id,
                    'title'            => $action->title,
                    'description'      => $action->description,
                    'priority'         => $action->priority,
                    'stable_key'       => $payload['stable_key'] ?? null,
                    'recurrence_count' => $payload['recurrence_count'] ?? 0,
                    'first_seen'       => $payload['first_seen'] ?? 'unknown',
                    'prior_attempts'   => $payload['prior_attempts'] ?? [],
                    'rejection_reasons'=> $payload['rejection_reasons'] ?? [],
                    'created_at'       => $action->created_at->diffForHumans(),
                ];
            })
            ->toArray();
    }

    public function openResponse($actionId)
    {
        $this->selectedEscalation = $actionId;
        $this->responseNotes = '';
        $this->snoozeDays = 7;
    }

    public function respond($actionId, $response)
    {
        $this->validate([
            'responseNotes' => 'nullable|string|max:2000',
            'snoozeDays'    => 'integer|min:1|max:30',
        ]);

        try {
            $action = AiAction::findOrFail($actionId);

            $snoozeUntil = null;
            if ($response === 'snooze') {
                $snoozeUntil = now()->addDays($this->snoozeDays)->toDateString();
            }

            $action->update([
                'human_response'       => $response,
                'human_response_notes' => $this->responseNotes,
                'human_response_at'    => now(),
                'snooze_until'         => $snoozeUntil,
                'agent_notified_at'    => null,
            ]);

            session()->flash('message', "Escalation marked as: {$response}.");
            $this->selectedEscalation = null;
            $this->responseNotes = '';
            $this->loadEscalations();

        } catch (\Exception $e) {
            session()->flash('error', 'Failed to record response: ' . $e->getMessage());
        }
    }
};
?>

<div class="space-y-4">
    {{-- Flash --}}
    @if(session()->has('message'))
        <div class="p-4 rounded-xl bg-emerald-950/40 border border-emerald-800/60 text-emerald-300 text-sm">
            {{ session('message') }}
        </div>
    @endif
    @if(session()->has('error'))
        <div class="p-4 rounded-xl bg-rose-950/40 border border-rose-800/60 text-rose-300 text-sm">
            {{ session('error') }}
        </div>
    @endif

    {{-- Empty state --}}
    @if(empty($escalations))
        <div class="rounded-2xl border border-slate-800/80 bg-slate-900/40 backdrop-blur-xl p-12 text-center">
            <div class="text-4xl mb-3">✨</div>
            <p class="text-slate-400">No recurring issues awaiting response.</p>
            <p class="text-sm text-slate-500 mt-1">Escalations appear here when the agent detects a pattern.</p>
        </div>
    @else
        @foreach($escalations as $esc)
            <div class="rounded-2xl border border-amber-500/30 bg-gradient-to-br from-amber-950/20 via-slate-900/60 to-slate-950/80 backdrop-blur-xl p-6 relative overflow-hidden">
                <div class="absolute -right-16 -top-16 w-48 h-48 bg-amber-500/10 rounded-full blur-3xl pointer-events-none"></div>

                <div class="relative">
                    {{-- Header --}}
                    <div class="flex items-start justify-between gap-4 flex-wrap">
                        <div class="flex items-center gap-3">
                            <div class="w-11 h-11 rounded-xl bg-amber-500/20 border border-amber-500/40 flex items-center justify-center text-xl">
                                🚨
                            </div>
                            <div>
                                <h3 class="font-bold text-white text-lg">{{ $esc['title'] }}</h3>
                                <p class="text-xs font-mono text-amber-400/80 tracking-wider mt-0.5">
                                    RECURRENCE #{{ $esc['recurrence_count'] }} · FIRST SEEN {{ strtoupper($esc['first_seen']) }}
                                </p>
                            </div>
                        </div>
                        <span class="inline-flex items-center rounded-full bg-amber-500/10 border border-amber-500/30 px-3 py-1 text-xs font-semibold text-amber-300">
                            Priority {{ $esc['priority'] }}/5
                        </span>
                    </div>

                    {{-- Description --}}
                    <p class="mt-5 text-slate-300 leading-relaxed text-sm">{{ $esc['description'] }}</p>

                    {{-- Prior attempts --}}
                    @if(!empty($esc['prior_attempts']))
                        <div class="mt-5 rounded-xl bg-slate-950/50 border border-white/5 p-4">
                            <p class="text-xs font-mono text-slate-500 uppercase tracking-wider mb-3">Recent attempts</p>
                            <div class="space-y-2">
                                @foreach(array_slice($esc['prior_attempts'], -3) as $attempt)
                                    <div class="flex items-center gap-3 text-xs">
                                        <span class="font-mono text-slate-500 w-24">{{ $attempt['date'] ?? '—' }}</span>
                                        <span class="px-2 py-0.5 rounded-md
                                            {{ ($attempt['status'] ?? '') === 'processed' ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : '' }}
                                            {{ ($attempt['status'] ?? '') === 'failed' ? 'bg-rose-500/10 text-rose-400 border border-rose-500/20' : '' }}
                                            {{ ($attempt['status'] ?? '') === 'escalated' ? 'bg-amber-500/10 text-amber-400 border border-amber-500/20' : '' }}
                                        ">
                                            {{ $attempt['status'] ?? 'unknown' }}
                                        </span>
                                        @if(!empty($attempt['rejection_reason']))
                                            <span class="text-slate-400">{{ $attempt['rejection_reason'] }}</span>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    {{-- Response buttons --}}
                    @if($selectedEscalation === $esc['id'])
                        {{-- Expanded response form --}}
                        <div class="mt-5 pt-5 border-t border-white/10 space-y-4">
                            <div>
                                <label class="block text-xs font-medium text-slate-400 mb-2">
                                    Notes (optional)
                                </label>
                                <textarea wire:model="responseNotes" rows="3"
                                    class="w-full rounded-xl bg-slate-800/60 border border-slate-700 text-slate-100 placeholder-slate-500 px-4 py-3 text-sm focus:border-amber-500 focus:ring-2 focus:ring-amber-500/20 focus:outline-none"
                                    placeholder="What should the agent know?"></textarea>
                            </div>

                            <div class="flex flex-wrap gap-2">
                                <button wire:click="respond({{ $esc['id'] }}, 'investigate')"
                                    class="px-4 py-2 rounded-xl bg-sky-500/20 hover:bg-sky-500/30 border border-sky-500/40 text-sky-300 text-sm font-semibold transition">
                                    🔍 Investigate deeper
                                </button>
                                <button wire:click="respond({{ $esc['id'] }}, 'retry')"
                                    class="px-4 py-2 rounded-xl bg-emerald-500/20 hover:bg-emerald-500/30 border border-emerald-500/40 text-emerald-300 text-sm font-semibold transition">
                                    🔄 Retry with new approach
                                </button>
                                <button wire:click="respond({{ $esc['id'] }}, 'resolve')"
                                    class="px-4 py-2 rounded-xl bg-slate-700/40 hover:bg-slate-700/60 border border-slate-600/60 text-slate-200 text-sm font-semibold transition">
                                    ✅ Mark resolved
                                </button>
                                <button wire:click="respond({{ $esc['id'] }}, 'snooze')"
                                    class="px-4 py-2 rounded-xl bg-amber-500/20 hover:bg-amber-500/30 border border-amber-500/40 text-amber-300 text-sm font-semibold transition">
                                    ⏸ Snooze {{ $snoozeDays }} days
                                </button>
                                <button wire:click="$set('selectedEscalation', null)"
                                    class="px-4 py-2 text-sm text-slate-400 hover:text-white transition">
                                    Cancel
                                </button>
                            </div>
                        </div>
                    @else
                        <div class="mt-5">
                            <button wire:click="openResponse({{ $esc['id'] }})"
                                class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-white text-slate-950 font-semibold text-sm hover:bg-amber-300 transition">
                                Respond to escalation →
                            </button>
                        </div>
                    @endif
                </div>
            </div>
        @endforeach
    @endif
</div>