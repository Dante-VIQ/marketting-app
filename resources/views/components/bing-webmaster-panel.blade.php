{{--
  Bing Webmaster Tools Integration Panel
  Embedded in brand settings / integrations page.
  Uses Alpine.js for tab switching and form submission.
  Routes: brands.bing.* (BingSettingsController)
--}}
@props(['brand'])

<div
    x-data="bingPanel({{ $brand->id }}, {{ json_encode(route('brands.bing.status', $brand)) }}, {{ json_encode(route('brands.bing.save-api-key', $brand)) }}, {{ json_encode(route('brands.bing.remove-api-key', $brand)) }}, {{ json_encode(route('brands.bing.refresh', $brand)) }}, {{ json_encode(route('brands.bing.import-csv', $brand)) }})"
    x-init="init()"
    class="rounded-xl overflow-hidden border border-slate-200 dark:border-slate-700/60 bg-white dark:bg-slate-900 shadow-sm"
>
    {{-- Header --}}
    <div class="flex items-center justify-between px-6 py-4 border-b border-slate-200 dark:border-slate-700/60 bg-slate-50 dark:bg-slate-800/60">
        <div class="flex items-center gap-3">
            <div class="w-8 h-8 rounded-lg bg-orange-500/15 dark:bg-orange-500/20 flex items-center justify-center">
                <svg class="w-4 h-4 text-orange-500 dark:text-orange-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
            </div>
            <div>
                <h3 class="text-sm font-semibold text-slate-900 dark:text-slate-100">Bing Webmaster Tools</h3>
                <p class="text-xs text-slate-500 dark:text-slate-400">Search performance, keyword gaps, real user queries</p>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <span x-show="status.has_data" x-cloak
                class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-green-100 dark:bg-green-900/40 text-green-700 dark:text-green-300 border border-green-200 dark:border-green-700/50">
                <span class="w-1.5 h-1.5 rounded-full bg-green-500 dark:bg-green-400 inline-block"></span>
                Connected
            </span>
            <span x-show="status.has_imports && !loading" x-cloak
                class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-blue-100 dark:bg-blue-900/40 text-blue-700 dark:text-blue-300 border border-blue-200 dark:border-blue-700/50">
                <span class="w-1.5 h-1.5 rounded-full bg-blue-500 dark:bg-blue-400 inline-block"></span>
                CSV imported
            </span>
            <span x-show="status.has_api_key && !status.has_data && !status.has_imports && !loading" x-cloak
                class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-amber-100 dark:bg-amber-900/40 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-700/50">
                <span class="w-1.5 h-1.5 rounded-full bg-amber-500 dark:bg-amber-400 inline-block"></span>
                Key set, no data yet
            </span>
            <span x-show="!status.has_api_key && !loading" x-cloak
                class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-slate-100 dark:bg-slate-700/60 text-slate-500 dark:text-slate-400 border border-slate-200 dark:border-slate-600/50">
                Not connected
            </span>
        </div>
    </div>

    {{-- Tabs --}}
    <div class="flex border-b border-slate-200 dark:border-slate-700/60 bg-slate-50 dark:bg-slate-800/40 overflow-x-auto">
        @foreach([['setup','Setup'],['insights','Keyword Gaps'],['queries','Real Queries'],['import','Import CSV']] as [$key,$label])
        <button
            @click="tab = '{{ $key }}'"
            :class="tab === '{{ $key }}'
                ? 'border-b-2 border-orange-500 text-orange-600 dark:text-orange-400 bg-white dark:bg-slate-900/50'
                : 'text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 hover:bg-white/50 dark:hover:bg-slate-800/50'"
            class="px-4 py-2.5 text-xs font-medium transition-colors whitespace-nowrap"
        >{{ $label }}</button>
        @endforeach
    </div>

    {{-- ── Tab: Setup ───────────────────────────────────────────────────── --}}
    <div x-show="tab === 'setup'" class="p-6 space-y-5">

        {{-- Feature list --}}
        <div class="space-y-2">
            <p class="text-sm font-medium text-slate-700 dark:text-slate-200">Connect your Bing Webmaster Tools account to unlock:</p>
            <ul class="space-y-1.5 mt-2">
                @foreach([
                    'Real search queries your site ranks for on Bing',
                    'Keyword gaps — high impressions, poor ranking',
                    'Backlink summary and linking domain count',
                    'Content ideas fed directly into daily briefs',
                    'Real user queries used as AI visibility probes',
                ] as $feature)
                <li class="flex items-start gap-2 text-xs text-slate-600 dark:text-slate-300">
                    <svg class="w-3.5 h-3.5 mt-0.5 text-orange-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                    </svg>
                    {{ $feature }}
                </li>
                @endforeach
            </ul>
        </div>

        {{-- API Key form --}}
        <div class="space-y-2 pt-1">
            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300">
                Bing Webmaster API Key
                <span class="font-normal text-slate-400 dark:text-slate-500 ml-1">— stored in DB, never in code</span>
            </label>
            <div class="flex gap-2">
                <input
                    x-model="apiKey"
                    type="password"
                    placeholder="Paste your Bing API key…"
                    class="flex-1 text-sm rounded-lg px-3 py-2
                        bg-white dark:bg-slate-800
                        border border-slate-300 dark:border-slate-600
                        text-slate-900 dark:text-slate-100
                        placeholder-slate-400 dark:placeholder-slate-500
                        focus:outline-none focus:ring-2 focus:ring-orange-400 dark:focus:ring-orange-500 focus:border-transparent"
                    autocomplete="off"
                />
                <button
                    @click="saveApiKey()"
                    :disabled="saving || !apiKey"
                    class="px-4 py-2 text-xs font-semibold rounded-lg transition-colors
                        bg-orange-500 hover:bg-orange-600 dark:bg-orange-500 dark:hover:bg-orange-600
                        text-white disabled:opacity-40 disabled:cursor-not-allowed"
                >
                    <span x-show="!saving">Save Key</span>
                    <span x-show="saving" x-cloak>Saving…</span>
                </button>
            </div>
            <p x-show="status.has_api_key" x-cloak class="text-xs text-green-600 dark:text-green-400 flex items-center gap-1.5">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                API key is configured.
                <button @click="removeApiKey()" class="text-red-500 dark:text-red-400 underline hover:no-underline ml-1">Remove</button>
            </p>
        </div>

        {{-- Refresh row --}}
        <div x-show="status.has_api_key" x-cloak
            class="flex items-center justify-between pt-3 border-t border-slate-100 dark:border-slate-700/50">
            <p class="text-xs text-slate-500 dark:text-slate-400">
                Last fetched:
                <span class="text-slate-700 dark:text-slate-300 font-medium"
                    x-text="status.fetched_at ? new Date(status.fetched_at).toLocaleString() : 'Never'"></span>
            </p>
            <button
                @click="refresh()"
                :disabled="refreshing"
                class="inline-flex items-center gap-1.5 text-xs px-3 py-1.5 rounded-lg transition-colors
                    border border-slate-300 dark:border-slate-600
                    text-slate-700 dark:text-slate-300
                    hover:bg-slate-100 dark:hover:bg-slate-700/60
                    disabled:opacity-40 disabled:cursor-not-allowed"
            >
                <svg class="w-3 h-3" :class="refreshing ? 'animate-spin' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                </svg>
                <span x-show="!refreshing">Refresh Now</span>
                <span x-show="refreshing" x-cloak>Refreshing…</span>
            </button>
        </div>

        {{-- Imported data summary --}}
        <template x-if="status.has_imports">
            <div class="p-3 rounded-lg border border-blue-200 dark:border-blue-800/50 bg-blue-50 dark:bg-blue-950/30">
                <p class="text-xs font-semibold text-blue-700 dark:text-blue-300 mb-1.5">Imported data active:</p>
                <template x-for="[type, info] in Object.entries(status.import_counts || {})" :key="type">
                    <p class="text-xs text-blue-600 dark:text-blue-400">
                        <span class="font-medium capitalize" x-text="type.replace('_', ' ')"></span>:
                        <span x-text="info.row_count + ' rows'"></span>
                        <span class="text-blue-400 dark:text-blue-500" x-text="info.imported_at ? '— ' + new Date(info.imported_at).toLocaleDateString() : ''"></span>
                    </p>
                </template>
                <p class="text-xs text-blue-500 dark:text-blue-500 mt-1.5">
                    Imported data shows in Keyword Gaps and Real Queries tabs when no live API data is available.
                </p>
            </div>
        </template>

        {{-- Errors --}}
        <template x-if="status.errors && Object.keys(status.errors).length > 0">
            <div class="p-3 rounded-lg border bg-red-50 dark:bg-red-950/30 border-red-200 dark:border-red-800/50">
                <p class="text-xs font-semibold text-red-700 dark:text-red-400 mb-1.5">Fetch errors:</p>
                <template x-for="[key, msg] in Object.entries(status.errors)" :key="key">
                    <p class="text-xs text-red-600 dark:text-red-400 leading-relaxed">
                        <span class="font-medium" x-text="key"></span>:
                        <span x-text="msg"></span>
                    </p>
                </template>
            </div>
        </template>

        {{-- Toast --}}
        <div x-show="toast" x-cloak x-transition
            class="p-3 rounded-lg border bg-green-50 dark:bg-green-950/30 border-green-200 dark:border-green-800/50 text-xs text-green-700 dark:text-green-400"
            x-text="toast">
        </div>
    </div>

    {{-- ── Tab: Keyword Gaps ────────────────────────────────────────────── --}}
    <div x-show="tab === 'insights'" class="p-6">
        <div x-show="!status.has_data" class="py-10 text-center">
            <svg class="w-8 h-8 mx-auto mb-3 text-slate-300 dark:text-slate-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
            </svg>
            <p class="text-sm text-slate-500 dark:text-slate-400">No data yet.</p>
            <p class="text-xs text-slate-400 dark:text-slate-500 mt-1">Add your API key on the Setup tab and click Refresh Now.</p>
        </div>

        <div x-show="status.has_data" class="space-y-4">
            <p class="text-xs text-slate-500 dark:text-slate-400">
                Queries where your site has impressions but ranks position 6–30, or high impressions with CTR below 2%.
                These are your top content opportunities on Bing.
            </p>
            <div class="overflow-x-auto rounded-lg border border-slate-200 dark:border-slate-700/60">
                <table class="w-full text-xs">
                    <thead class="bg-slate-50 dark:bg-slate-800/60">
                        <tr>
                            <th class="text-left px-3 py-2.5 font-semibold text-slate-600 dark:text-slate-300 border-b border-slate-200 dark:border-slate-700/60">Query</th>
                            <th class="text-right px-3 py-2.5 font-semibold text-slate-600 dark:text-slate-300 border-b border-slate-200 dark:border-slate-700/60">Impr.</th>
                            <th class="text-right px-3 py-2.5 font-semibold text-slate-600 dark:text-slate-300 border-b border-slate-200 dark:border-slate-700/60">Pos.</th>
                            <th class="text-right px-3 py-2.5 font-semibold text-slate-600 dark:text-slate-300 border-b border-slate-200 dark:border-slate-700/60">CTR%</th>
                            <th class="text-right px-3 py-2.5 font-semibold text-slate-600 dark:text-slate-300 border-b border-slate-200 dark:border-slate-700/60">Type</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700/40">
                        <template x-for="gap in status.keyword_gaps" :key="gap.query">
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/40 transition-colors">
                                <td class="px-3 py-2.5 max-w-[220px]">
                                    <div class="flex items-center gap-1.5">
                                        <span class="text-slate-800 dark:text-slate-200 font-medium truncate" x-text="gap.query"></span>
                                        <span x-show="gap.source === 'imported'" x-cloak
                                            class="shrink-0 inline-block px-1 py-0.5 rounded text-[9px] font-semibold bg-blue-100 dark:bg-blue-900/40 text-blue-600 dark:text-blue-300 border border-blue-200 dark:border-blue-700/50">
                                            CSV
                                        </span>
                                    </div>
                                </td>
                                <td class="px-3 py-2.5 text-right text-slate-600 dark:text-slate-400 tabular-nums" x-text="gap.impressions.toLocaleString()"></td>
                                <td class="px-3 py-2.5 text-right font-semibold text-orange-600 dark:text-orange-400 tabular-nums" x-text="gap.position"></td>
                                <td class="px-3 py-2.5 text-right text-slate-600 dark:text-slate-400 tabular-nums" x-text="gap.ctr + '%'"></td>
                                <td class="px-3 py-2.5 text-right">
                                    <span
                                        :class="gap.gap_type === 'ctr_mismatch'
                                            ? 'bg-purple-100 dark:bg-purple-900/40 text-purple-700 dark:text-purple-300 border border-purple-200 dark:border-purple-700/50'
                                            : 'bg-orange-100 dark:bg-orange-900/40 text-orange-700 dark:text-orange-300 border border-orange-200 dark:border-orange-700/50'"
                                        class="inline-block px-1.5 py-0.5 rounded text-[10px] font-semibold"
                                        x-text="gap.gap_type === 'ctr_mismatch' ? 'CTR' : 'Rank'"
                                    ></span>
                                </td>
                            </tr>
                        </template>
                        <tr x-show="status.keyword_gaps && status.keyword_gaps.length === 0">
                            <td colspan="5" class="px-3 py-6 text-center text-xs text-slate-400 dark:text-slate-500">
                                No keyword gaps found yet — your site may need more search performance data.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p class="text-xs text-slate-400 dark:text-slate-500">
                These gaps feed directly into your daily brief as content action candidates.
            </p>
        </div>
    </div>

    {{-- ── Tab: Real Queries ────────────────────────────────────────────── --}}
    <div x-show="tab === 'queries'" class="p-6">
        <div x-show="!status.has_data" class="py-10 text-center">
            <svg class="w-8 h-8 mx-auto mb-3 text-slate-300 dark:text-slate-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>
            <p class="text-sm text-slate-500 dark:text-slate-400">No data yet.</p>
            <p class="text-xs text-slate-400 dark:text-slate-500 mt-1">Add your API key on the Setup tab and click Refresh Now.</p>
        </div>

        <div x-show="status.has_data" class="space-y-4">
            <p class="text-xs text-slate-500 dark:text-slate-400">
                Actual search queries driving impressions on Bing.
                Used as AI visibility probes to measure how often your brand appears in AI answers.
            </p>
            <div class="overflow-x-auto rounded-lg border border-slate-200 dark:border-slate-700/60">
                <table class="w-full text-xs">
                    <thead class="bg-slate-50 dark:bg-slate-800/60">
                        <tr>
                            <th class="text-left px-3 py-2.5 font-semibold text-slate-600 dark:text-slate-300 border-b border-slate-200 dark:border-slate-700/60">Query</th>
                            <th class="text-right px-3 py-2.5 font-semibold text-slate-600 dark:text-slate-300 border-b border-slate-200 dark:border-slate-700/60">Impressions</th>
                            <th class="text-right px-3 py-2.5 font-semibold text-slate-600 dark:text-slate-300 border-b border-slate-200 dark:border-slate-700/60">Clicks</th>
                            <th class="text-right px-3 py-2.5 font-semibold text-slate-600 dark:text-slate-300 border-b border-slate-200 dark:border-slate-700/60">Position</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700/40">
                        <template x-for="q in status.top_queries" :key="q.query">
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/40 transition-colors">
                                <td class="px-3 py-2.5 max-w-[240px]">
                                    <div class="flex items-center gap-1.5">
                                        <span class="text-slate-800 dark:text-slate-200 font-medium truncate" x-text="q.query"></span>
                                        <span x-show="q.source === 'imported'" x-cloak
                                            class="shrink-0 inline-block px-1 py-0.5 rounded text-[9px] font-semibold bg-blue-100 dark:bg-blue-900/40 text-blue-600 dark:text-blue-300 border border-blue-200 dark:border-blue-700/50">
                                            CSV
                                        </span>
                                    </div>
                                </td>
                                <td class="px-3 py-2.5 text-right text-slate-600 dark:text-slate-400 tabular-nums" x-text="q.impressions.toLocaleString()"></td>
                                <td class="px-3 py-2.5 text-right text-slate-600 dark:text-slate-400 tabular-nums" x-text="q.clicks"></td>
                                <td class="px-3 py-2.5 text-right tabular-nums">
                                    <span class="font-medium text-slate-700 dark:text-slate-300" x-text="q.impression_pos ?? q.click_position ?? '—'"></span>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- ── Tab: Import CSV ──────────────────────────────────────────────── --}}
    <div x-show="tab === 'import'" class="p-6 space-y-5">

        {{-- Export type cards --}}
        <div>
            <p class="text-sm text-slate-700 dark:text-slate-200 font-medium mb-1">Manual CSV exports</p>
            <p class="text-xs text-slate-500 dark:text-slate-400 mb-3">
                Some Bing data is only available as a manual export (no API endpoint).
                Upload these CSVs to enrich your content pipeline.
            </p>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                @foreach([
                    ['keyword_report',  'Keyword Report',  'Bing keyword rankings CSV (Keyword, Impressions, Clicks, CTR, Avg. Position)'],
                    ['topic_portfolio', 'Topic Portfolio', 'Topic clusters Bing sees your site covering'],
                    ['ai_performance',  'AI Performance',  'Bing AI citation and visibility analytics'],
                    ['citations',       'Citations',       'Pages Bing AI cites in its answers'],
                ] as [$val, $title, $desc])
                <div class="p-3 rounded-lg border border-slate-200 dark:border-slate-700/60 bg-slate-50 dark:bg-slate-800/50">
                    <p class="text-xs font-semibold text-slate-700 dark:text-slate-200">{{ $title }}</p>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">{{ $desc }}</p>
                </div>
                @endforeach
            </div>
            <p class="text-xs text-slate-400 dark:text-slate-500 mt-2.5 flex items-start gap-1.5">
                <svg class="w-3.5 h-3.5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                These are optional. Missing files don't block anything — they add extra context to AI planning.
            </p>
        </div>

        {{-- Upload form --}}
        <form @submit.prevent="importCsv()" class="space-y-3 pt-4 border-t border-slate-200 dark:border-slate-700/60">
            <div>
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">Export Type</label>
                <select x-model="importType"
                    class="w-full text-sm rounded-lg px-3 py-2
                        bg-white dark:bg-slate-800
                        border border-slate-300 dark:border-slate-600
                        text-slate-900 dark:text-slate-100
                        focus:outline-none focus:ring-2 focus:ring-orange-400 dark:focus:ring-orange-500">
                    <option value="keyword_report">Keyword Report (rankings CSV)</option>
                    <option value="topic_portfolio">Topic Portfolio</option>
                    <option value="ai_performance">AI Performance</option>
                    <option value="citations">Citations</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">CSV File</label>
                <input type="file" accept=".csv,.txt"
                    @change="importFile = $event.target.files[0]"
                    class="text-xs text-slate-600 dark:text-slate-400
                        file:mr-3 file:px-3 file:py-1.5 file:rounded-lg file:border-0
                        file:text-xs file:font-semibold
                        file:bg-orange-50 file:text-orange-700
                        dark:file:bg-orange-500/20 dark:file:text-orange-300
                        hover:file:bg-orange-100 dark:hover:file:bg-orange-500/30
                        cursor-pointer" />
            </div>
            <button
                type="submit"
                :disabled="importing || !importFile"
                class="px-4 py-2 text-xs font-semibold rounded-lg transition-colors
                    bg-orange-500 hover:bg-orange-600 dark:bg-orange-500 dark:hover:bg-orange-600
                    text-white disabled:opacity-40 disabled:cursor-not-allowed"
            >
                <span x-show="!importing">Upload & Import</span>
                <span x-show="importing" x-cloak>Importing…</span>
            </button>
            <div x-show="importResult" x-cloak x-transition
                class="p-3 rounded-lg border text-xs"
                :class="importResult?.success
                    ? 'bg-green-50 dark:bg-green-950/30 text-green-700 dark:text-green-300 border-green-200 dark:border-green-800/50'
                    : 'bg-red-50 dark:bg-red-950/30 text-red-700 dark:text-red-400 border-red-200 dark:border-red-800/50'"
                x-text="importResult?.message">
            </div>
        </form>
    </div>
</div>

<script>
function bingPanel(brandId, statusUrl, saveKeyUrl, removeKeyUrl, refreshUrl, importCsvUrl) {
    return {
        tab: 'setup',
        apiKey: '',
        status: { has_api_key: false, has_data: false, keyword_gaps: [], top_queries: [], errors: {} },
        loading: false,
        saving: false,
        refreshing: false,
        importing: false,
        importType: 'topic_portfolio',
        importFile: null,
        importResult: null,
        toast: '',

        async init() {
            this.loading = true;
            try {
                const r = await fetch(statusUrl, {
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    }
                });
                this.status = await r.json();
            } catch (e) { console.error('Bing status error', e); }
            this.loading = false;
        },

        async saveApiKey() {
            if (!this.apiKey) return;
            this.saving = true;
            try {
                const r = await fetch(saveKeyUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({ bing_api_key: this.apiKey })
                });
                const d = await r.json();
                if (d.success) {
                    this.toast = '✓ API key saved successfully.';
                    this.apiKey = '';
                    this.status.has_api_key = true;
                    setTimeout(() => this.toast = '', 3500);
                    await this.refresh();
                }
            } catch (e) { console.error(e); }
            this.saving = false;
        },

        async removeApiKey() {
            if (!confirm('Remove the Bing API key? Bing data will no longer be fetched for this brand.')) return;
            const r = await fetch(removeKeyUrl, {
                method: 'DELETE',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                }
            });
            const d = await r.json();
            if (d.success) {
                this.status.has_api_key = false;
                this.status.has_data = false;
            }
        },

        async refresh() {
            this.refreshing = true;
            try {
                const r = await fetch(refreshUrl, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    }
                });
                const d = await r.json();
                if (d.success) await this.init();
            } catch (e) { console.error(e); }
            this.refreshing = false;
        },

        async importCsv() {
            if (!this.importFile) return;
            this.importing = true;
            this.importResult = null;
            const form = new FormData();
            form.append('type', this.importType);
            form.append('file', this.importFile);
            form.append('_token', document.querySelector('meta[name="csrf-token"]').content);
            try {
                const r = await fetch(importCsvUrl, {
                    method: 'POST',
                    headers: { 'Accept': 'application/json' },
                    body: form
                });
                this.importResult = await r.json();
            } catch (e) {
                this.importResult = { success: false, message: e.message };
            }
            this.importing = false;
        },
    };
}
</script>
