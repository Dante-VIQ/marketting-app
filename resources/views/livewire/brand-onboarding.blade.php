<div class="max-w-2xl mx-auto py-12 px-4">

    {{-- Progress bar --}}
    <div class="mb-10">
        <div class="flex items-center justify-between mb-3">
            @foreach([1 => 'Your Brand', 2 => 'Your Audience', 3 => 'First Goal'] as $n => $label)
                <div class="flex items-center gap-2 {{ $loop->last ? '' : 'flex-1' }}">
                    <div class="flex items-center justify-center w-8 h-8 rounded-full text-xs font-bold shrink-0 transition-all duration-300
                        {{ $step > $n ? 'bg-emerald-500 text-white' : ($step === $n ? 'bg-emerald-500/20 text-emerald-400 ring-2 ring-emerald-500/50' : 'bg-slate-800 text-slate-500') }}">
                        @if($step > $n)
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                            </svg>
                        @else
                            {{ $n }}
                        @endif
                    </div>
                    <span class="text-xs font-medium hidden sm:block
                        {{ $step === $n ? 'text-white' : 'text-slate-500' }}">
                        {{ $label }}
                    </span>
                    @if(!$loop->last)
                        <div class="flex-1 mx-3 h-px {{ $step > $n ? 'bg-emerald-500/50' : 'bg-slate-800' }} transition-colors duration-300"></div>
                    @endif
                </div>
            @endforeach
        </div>
    </div>

    {{-- Card --}}
    <div class="bg-slate-900/80 backdrop-blur-xl border border-slate-800/60 rounded-2xl shadow-2xl overflow-hidden">

        {{-- Step 1: Brand Identity --}}
        @if($step === 1)
        <div class="p-8">
            <div class="mb-6">
                <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-500/10 px-3 py-1 text-xs font-medium text-emerald-400 ring-1 ring-inset ring-emerald-500/20 mb-3">
                    Step 1 of 3
                </span>
                <h2 class="text-2xl font-bold text-white">Tell us about your brand</h2>
                <p class="mt-1 text-sm text-slate-400">This is how Vumbi AI identifies and represents you.</p>
            </div>

            <div class="space-y-5">
                <div>
                    <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1.5">Brand Name *</label>
                    <input wire:model="name" type="text" placeholder="e.g. Vumbi Ventures"
                           class="w-full px-4 py-3 rounded-xl bg-slate-800/60 border border-slate-700/60 text-white placeholder-slate-500 text-sm
                                  focus:outline-none focus:ring-2 focus:ring-emerald-500/60 focus:border-emerald-500/60 transition-colors">
                    @error('name') <p class="mt-1.5 text-xs text-red-400">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1.5">Website URL</label>
                    <input wire:model="website_url" type="url" placeholder="https://yoursite.com"
                           class="w-full px-4 py-3 rounded-xl bg-slate-800/60 border border-slate-700/60 text-white placeholder-slate-500 text-sm
                                  focus:outline-none focus:ring-2 focus:ring-emerald-500/60 focus:border-emerald-500/60 transition-colors">
                    @error('website_url') <p class="mt-1.5 text-xs text-red-400">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1.5">Brand Type *</label>
                    <div class="grid grid-cols-2 gap-3">
                        @foreach($domainTypes as $key => $type)
                            <label wire:key="dt-{{ $key }}"
                                   class="relative flex items-start gap-3 p-4 rounded-xl border cursor-pointer transition-all duration-150
                                          {{ $domain_type === $key
                                             ? 'bg-emerald-500/10 border-emerald-500/40 ring-1 ring-emerald-500/30'
                                             : 'bg-slate-800/40 border-slate-700/40 hover:border-slate-600' }}">
                                <input type="radio" wire:model="domain_type" value="{{ $key }}" class="sr-only">
                                <span class="text-2xl mt-0.5 shrink-0">{{ $type['icon'] }}</span>
                                <div>
                                    <p class="text-sm font-semibold {{ $domain_type === $key ? 'text-emerald-300' : 'text-white' }}">{{ $type['label'] }}</p>
                                    <p class="text-xs text-slate-500 mt-0.5 leading-relaxed">{{ $type['description'] }}</p>
                                </div>
                            </label>
                        @endforeach
                    </div>
                    @error('domain_type') <p class="mt-1.5 text-xs text-red-400">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1.5">Timezone *</label>
                    <select wire:model="timezone"
                            class="w-full px-4 py-3 rounded-xl bg-slate-800/60 border border-slate-700/60 text-white text-sm
                                   focus:outline-none focus:ring-2 focus:ring-emerald-500/60 focus:border-emerald-500/60 transition-colors">
                        @foreach($timezones as $tz)
                            <option value="{{ $tz }}" {{ $timezone === $tz ? 'selected' : '' }}>{{ $tz }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>
        @endif

        {{-- Step 2: Audience --}}
        @if($step === 2)
        <div class="p-8">
            <div class="mb-6">
                <span class="inline-flex items-center gap-1.5 rounded-full bg-indigo-500/10 px-3 py-1 text-xs font-medium text-indigo-400 ring-1 ring-inset ring-indigo-500/20 mb-3">
                    Step 2 of 3
                </span>
                <h2 class="text-2xl font-bold text-white">Who are you talking to?</h2>
                <p class="mt-1 text-sm text-slate-400">This shapes how the AI writes for you. Be specific — it matters.</p>
            </div>

            <div class="space-y-5">
                <div>
                    <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1.5">Brand Voice *</label>
                    <p class="text-xs text-slate-500 mb-2">How does your brand sound? Tone, style, what you never say.</p>
                    <textarea wire:model="brand_voice" rows="3"
                              placeholder="e.g. Warm and knowledgeable. We speak like a trusted local guide, not a travel brochure. We never use corporate speak or exaggerated superlatives."
                              class="w-full px-4 py-3 rounded-xl bg-slate-800/60 border border-slate-700/60 text-white placeholder-slate-500 text-sm
                                     focus:outline-none focus:ring-2 focus:ring-emerald-500/60 focus:border-emerald-500/60 transition-colors resize-none"></textarea>
                    @error('brand_voice') <p class="mt-1.5 text-xs text-red-400">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1.5">Target Audience *</label>
                    <p class="text-xs text-slate-500 mb-2">Who exactly is your customer? Demographics, interests, pain points.</p>
                    <textarea wire:model="target_audience" rows="2"
                              placeholder="e.g. East African professionals aged 28–45 who want authentic safari and cultural travel experiences, value local expertise, and are tired of generic tourist packages."
                              class="w-full px-4 py-3 rounded-xl bg-slate-800/60 border border-slate-700/60 text-white placeholder-slate-500 text-sm
                                     focus:outline-none focus:ring-2 focus:ring-emerald-500/60 focus:border-emerald-500/60 transition-colors resize-none"></textarea>
                    @error('target_audience') <p class="mt-1.5 text-xs text-red-400">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1.5">Key Differentiator *</label>
                    <p class="text-xs text-slate-500 mb-2">What do you offer that nobody else does?</p>
                    <textarea wire:model="key_differentiator" rows="2"
                              placeholder="e.g. We're the only platform built by Africans for African travel — community-vetted itineraries, M-Pesa friendly booking, and a storytelling-first approach."
                              class="w-full px-4 py-3 rounded-xl bg-slate-800/60 border border-slate-700/60 text-white placeholder-slate-500 text-sm
                                     focus:outline-none focus:ring-2 focus:ring-emerald-500/60 focus:border-emerald-500/60 transition-colors resize-none"></textarea>
                    @error('key_differentiator') <p class="mt-1.5 text-xs text-red-400">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>
        @endif

        {{-- Step 3: First goal --}}
        @if($step === 3)
        <div class="p-8">
            <div class="mb-6">
                <span class="inline-flex items-center gap-1.5 rounded-full bg-sky-500/10 px-3 py-1 text-xs font-medium text-sky-400 ring-1 ring-inset ring-sky-500/20 mb-3">
                    Step 3 of 3
                </span>
                <h2 class="text-2xl font-bold text-white">What's your #1 goal right now?</h2>
                <p class="mt-1 text-sm text-slate-400">Your AI brief and content plan will be shaped around this first.</p>
            </div>

            <div class="space-y-3">
                @foreach([
                    'grow_social'        => ['icon' => '📣', 'label' => 'Grow social media presence', 'desc' => 'Build followers, increase reach, and drive engagement on Facebook and Instagram.'],
                    'increase_traffic'   => ['icon' => '📈', 'label' => 'Drive more website traffic', 'desc' => 'Get more people to your site from social and search.'],
                    'get_leads'          => ['icon' => '🎯', 'label' => 'Generate more leads', 'desc' => 'Capture potential customers and nurture them into buyers.'],
                    'beat_competitors'   => ['icon' => '🏆', 'label' => 'Outperform competitors', 'desc' => "Find gaps in your competitors' strategy and fill them first."],
                ] as $value => $option)
                    <label wire:key="goal-{{ $value }}"
                           class="flex items-center gap-4 p-4 rounded-xl border cursor-pointer transition-all duration-150
                                  {{ $first_goal === $value
                                     ? 'bg-sky-500/10 border-sky-500/40 ring-1 ring-sky-500/30'
                                     : 'bg-slate-800/40 border-slate-700/40 hover:border-slate-600' }}">
                        <input type="radio" wire:model="first_goal" value="{{ $value }}" class="sr-only">
                        <span class="text-2xl shrink-0">{{ $option['icon'] }}</span>
                        <div>
                            <p class="text-sm font-semibold {{ $first_goal === $value ? 'text-sky-300' : 'text-white' }}">{{ $option['label'] }}</p>
                            <p class="text-xs text-slate-500 mt-0.5">{{ $option['desc'] }}</p>
                        </div>
                        @if($first_goal === $value)
                            <div class="ml-auto">
                                <svg class="w-5 h-5 text-sky-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                                </svg>
                            </div>
                        @endif
                    </label>
                @endforeach
                @error('first_goal') <p class="mt-1.5 text-xs text-red-400">{{ $message }}</p> @enderror
            </div>
        </div>
        @endif

        {{-- Navigation buttons --}}
        <div class="border-t border-slate-800/60 px-8 py-5 flex items-center justify-between">
            <div>
                @if($step > 1)
                    <button wire:click="prevStep" type="button"
                            class="px-4 py-2.5 rounded-xl text-sm font-medium text-slate-400 hover:text-white
                                   bg-slate-800/60 border border-slate-700/60 hover:border-slate-600 transition-all">
                        ← Back
                    </button>
                @endif
            </div>

            <div class="flex items-center gap-3">
                <span class="text-xs text-slate-600">Step {{ $step }} of 3</span>
                @if($step < 3)
                    <button wire:click="nextStep" type="button"
                            class="px-6 py-2.5 rounded-xl text-sm font-semibold text-white
                                   bg-gradient-to-r from-emerald-500 to-teal-600
                                   hover:from-emerald-400 hover:to-teal-500
                                   shadow-lg shadow-emerald-500/20 transition-all duration-200">
                        Continue →
                    </button>
                @else
                    <button wire:click="finish" wire:loading.attr="disabled" type="button"
                            class="px-6 py-2.5 rounded-xl text-sm font-semibold text-white
                                   bg-gradient-to-r from-emerald-500 to-teal-600
                                   hover:from-emerald-400 hover:to-teal-500
                                   shadow-lg shadow-emerald-500/20 transition-all duration-200
                                   disabled:opacity-60">
                        <span wire:loading.remove>Launch my brand 🚀</span>
                        <span wire:loading>Setting up…</span>
                    </button>
                @endif
            </div>
        </div>

    </div>

</div>
