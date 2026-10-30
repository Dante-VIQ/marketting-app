<div
    x-data="{ open: @entangle('isOpen') }"
    class="fixed bottom-6 right-6 z-50 flex flex-col items-end gap-3"
>
    {{-- ── Floating toggle button ────────────────────────────────────────── --}}
    <button
        wire:click="toggleOpen"
        class="relative flex h-14 w-14 items-center justify-center rounded-full
               bg-gradient-to-br from-emerald-500 to-indigo-600
               shadow-lg shadow-emerald-500/30
               ring-1 ring-white/10
               hover:scale-105 active:scale-95
               transition-transform duration-200"
        title="{{ $isOpen ? 'Close chat' : 'Open Vumbi AI' }}"
    >
        <span x-show="!open" class="text-xl">✨</span>
        <span x-show="open"  class="text-lg text-white font-bold leading-none">✕</span>

        {{-- Pulse indicator when loading --}}
        @if($isLoading)
            <span class="absolute top-0 right-0 h-3 w-3 rounded-full bg-emerald-400 animate-ping"></span>
            <span class="absolute top-0 right-0 h-3 w-3 rounded-full bg-emerald-400"></span>
        @endif
    </button>

    {{-- ── Chat panel ───────────────────────────────────────────────────── --}}
    <div
        x-show="open"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 translate-y-4 scale-95"
        x-transition:enter-end="opacity-100 translate-y-0 scale-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 translate-y-0 scale-100"
        x-transition:leave-end="opacity-0 translate-y-4 scale-95"
        class="w-[360px] sm:w-[420px] rounded-2xl overflow-hidden
               bg-slate-900/80 backdrop-blur-xl
               border border-white/10
               shadow-2xl shadow-black/50
               flex flex-col"
        style="max-height: min(600px, calc(100vh - 120px));"
    >
        {{-- Header --}}
        <div class="flex items-center justify-between px-4 py-3 border-b border-white/10
                    bg-gradient-to-r from-emerald-900/40 to-indigo-900/40">
            <div class="flex items-center gap-2">
                <span class="text-lg">✨</span>
                <span class="text-sm font-semibold text-white">Vumbi AI</span>
                <span class="h-2 w-2 rounded-full bg-emerald-400 animate-pulse" title="Online"></span>
            </div>
            <button
                wire:click="clearChat"
                class="text-xs text-slate-400 hover:text-white transition-colors"
                title="Clear conversation"
            >
                New chat
            </button>
        </div>

        {{-- Messages --}}
        <div
            id="vumbi-chat-messages"
            class="flex-1 overflow-y-auto px-4 py-3 space-y-3 scroll-smooth"
            x-init="$el.scrollTop = $el.scrollHeight"
            x-on:send-to-ai.window="$nextTick(() => { $el.scrollTop = $el.scrollHeight })"
        >
            @foreach($messages as $msg)
                @if($msg['role'] === 'user')
                    {{-- User bubble --}}
                    <div class="flex justify-end">
                        <div class="max-w-[80%] rounded-2xl rounded-tr-sm px-3 py-2
                                    bg-indigo-600/80 text-white text-sm leading-relaxed">
                            {{ $msg['content'] }}
                        </div>
                    </div>
                @else
                    {{-- Assistant bubble --}}
                    <div class="flex items-start gap-2">
                        <div class="flex-shrink-0 h-7 w-7 rounded-full
                                    bg-gradient-to-br from-emerald-500 to-indigo-600
                                    flex items-center justify-center text-xs">
                            ✨
                        </div>
                        <div class="max-w-[85%] rounded-2xl rounded-tl-sm px-3 py-2
                                    bg-slate-800/80 text-slate-100 text-sm leading-relaxed
                                    prose prose-sm prose-invert max-w-none">
                            {!! \Illuminate\Support\Str::markdown(e($msg['content'])) !!}
                        </div>
                    </div>
                @endif
            @endforeach

            {{-- Typing indicator --}}
            @if($isLoading)
                <div class="flex items-start gap-2">
                    <div class="flex-shrink-0 h-7 w-7 rounded-full
                                bg-gradient-to-br from-emerald-500 to-indigo-600
                                flex items-center justify-center text-xs">
                        ✨
                    </div>
                    <div class="rounded-2xl rounded-tl-sm px-4 py-3 bg-slate-800/80">
                        <div class="flex gap-1 items-center h-4">
                            <span class="h-2 w-2 rounded-full bg-emerald-400 animate-bounce [animation-delay:0ms]"></span>
                            <span class="h-2 w-2 rounded-full bg-emerald-400 animate-bounce [animation-delay:150ms]"></span>
                            <span class="h-2 w-2 rounded-full bg-emerald-400 animate-bounce [animation-delay:300ms]"></span>
                        </div>
                    </div>
                </div>
            @endif
        </div>

        {{-- Input --}}
        <div class="px-3 py-3 border-t border-white/10 bg-slate-900/60">
            <form wire:submit="send" class="flex items-end gap-2">
                <textarea
                    wire:model="input"
                    x-data
                    x-on:keydown.enter.prevent="!$event.shiftKey && $wire.send()"
                    rows="1"
                    placeholder="Ask anything about your business…"
                    class="flex-1 resize-none rounded-xl bg-slate-800/80 border border-white/10
                           text-sm text-white placeholder-slate-500
                           px-3 py-2 leading-relaxed
                           focus:outline-none focus:ring-1 focus:ring-emerald-500/50
                           transition-all duration-150
                           max-h-28 overflow-y-auto"
                    style="field-sizing: content; min-height: 38px;"
                    @disabled($isLoading)
                    x-on:input="$el.style.height = 'auto'; $el.style.height = Math.min($el.scrollHeight, 112) + 'px';"
                ></textarea>
                <button
                    type="submit"
                    @disabled($isLoading || trim($input) === '')
                    class="flex-shrink-0 h-9 w-9 rounded-xl
                           bg-gradient-to-br from-emerald-500 to-indigo-600
                           flex items-center justify-center
                           text-white shadow-md
                           hover:scale-105 active:scale-95
                           disabled:opacity-40 disabled:cursor-not-allowed disabled:scale-100
                           transition-transform duration-150"
                    title="Send (Enter)"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M3.478 2.405a.75.75 0 00-.926.94l2.432 7.905H13.5a.75.75 0 010 1.5H4.984l-2.432 7.905a.75.75 0 00.926.94 60.519 60.519 0 0018.445-8.986.75.75 0 000-1.218A60.517 60.517 0 003.478 2.405z"/>
                    </svg>
                </button>
            </form>
            <p class="text-center text-[10px] text-slate-600 mt-1.5">
                Vumbi AI — your data, your decisions
            </p>
        </div>
    </div>
</div>

{{-- Auto-scroll when new messages arrive via Livewire --}}
<script>
    document.addEventListener('livewire:updated', () => {
        const el = document.getElementById('vumbi-chat-messages');
        if (el) el.scrollTop = el.scrollHeight;
    });
</script>
