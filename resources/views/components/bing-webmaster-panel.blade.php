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
    class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl overflow-hidden"
>
    {{-- Header --}}
    <div class="flex items-center justify-between px-6 py-4 border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/50">
        <div class="flex items-center gap-3">
            <div class="w-8 h-8 rounded-lg bg-orange-500/10 flex items-center justify-center">
                <svg class="w-4 h-4 text-orange-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            </div>
            <div>
                <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Bing Webmaster Tools</h3>
                <p class="text-xs text-slate-500 dark:text-slate-400">Search performance, keyword gaps, real user queries</p>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <span x-show="status.has_data" x-cloak class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400">
                <span class="w-1.5 h-1.5 rounded-full bg-green-500 inline-block"></span>
                Connected
            </span>
            <span x-show="status.has_api_key && !status.has_data && !loading" x-cloak class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-yellow-100 dark:bg-yellow-900/30 text-yellow-700 dark:text-yellow-400">
                <span class="w-1.5 h-1.5 rounded-full bg-yellow-500 inline-block"></span>
                Key set, no data yet
            </span>
            <span x-show="!status.has_api_key && !loading" x-cloak class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400">
                Not connected
            </span>
        </div>
    </div>

    {{-- Tabs --}}
    <div class="flex border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/30">
        <button
            @click="tab = 'setup'"
            :class="tab === 'setup' ? 'border-b-2 border-orange-500 text-orange-600 dark:text-orange-400' : 'text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-200'"
            class="px-4 py-2.5 text-xs font-medium transition-colors"
        >Setup</button>
        <button
            @click="tab = 'insights'"
            :class="tab === 'insights' ? 'border-b-2 border-orange-500 text-orange-600 dark:text-orange-400' : 'text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-200'"
            class="px-4 py-2.5 text-xs font-medium transition-colors"
        >Keyword Gaps</button>
        <button
            @click="tab = 'queries'"
            :class="tab === 'queries' ? 'border-b-2 border-orange-500 text-orange-600 dark:text-orange-400' : 'text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-200'"
            class="px-4 py-2.5 text-xs font-medium transition-colors"
        >Real Queries</button>
        <button
            @click="tab = 'import'"
            :class="tab === 'import' ? 'border-b-2 border-orange-500 text-orange-600 dark:text-orange-400' : 'text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-200'"
            class="px-4 py-2.5 text-xs font-medium transition-colors"
        >Import CSV</button>
    </div>

    {{-- Tab: Setup --}}
    <div x-show="tab === 'setup'" class="p-6 space-y-5">
        <div class="text-sm text-slate-600 dark:text-slate-400 space-y-1">
            <p>Connect your <strong>Bing Webmaster Tools</strong> account to unlock:</p>
            <ul class="list-disc list-inside space-y-0.5 text-slate-500 dark:text-slate-400 mt-2">
                <li>Real search queries your site ranks for on Bing</li>
                <li>Keyword gaps — high impressions, poor ranking</li>
                <li>Backlink summary and indexing status</li>
                <li>Content ideas fed directly into daily briefs</li>
                <li>Real user queries used as AI visibility probes</li>
            </ul>
        </div>

        {{-- API Key form --}}
        <div class="space-y-3">
            <label class="block text-xs font-medium text-slate-700 dark:text-slate-300">
                Bing Webmaster API Key
                <span class="text-slate-400 font-normal">(stored securely, never exposed)</span>
            </label>
            <div class="flex gap-2">
                <input
                    x-model="apiKey"
                    type="password"
                    placeholder="Paste your Bing API key…"
                    class="flex-1 text-sm border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 bg-white dark:bg-slate-800 text-slate-900 dark:text-white placeholder-slate-400 focus:ring-2 focus:ring-orange-400 focus:outline-none"
                    autocomplete="off"
                />
                <button
                    @click="saveApiKey()"
                    :disabled="saving || !apiKey"
                    class="px-4 py-2 text-xs font-medium bg-orange-500 hover:bg-orange-600 disabled:opacity-50 text-white rounded-lg transition-colors"
                >
                    <span x-show="!saving">Save Key</span>
                    <span x-show="saving" x-cloak>Saving…</span>
                </button>
            </div>
            <p x-show="status.has_api_key" x-cloak class="text-xs text-green-600 dark:text-green-400">✓ API key is configured. <button @click="removeApiKey()" class="underline hover:no-underline text-red-500">Remove</button></p>
        </div>

        {{-- Refresh --}}
        <div x-show="status.has_api_key" x-cloak class="pt-2 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between">
            <div class="text-xs text-slate-500 dark:text-slate-400">
                Last fetched: <span x-text="status.fetched_at ? new Date(status.fetched_at).toLocaleString() : 'Never'"></span>
            </div>
            <button
                @click="refresh()"
                :disabled="refreshing"
                class="text-xs px-3 py-1.5 border border-slate-300 dark:border-slate-600 rounded-lg hover:bg-slate-50 dark:hover:bg-slate-800 text-slate-600 dark:text-slate-300 transition-colors disabled:opacity-50"
            >
                <span x-show="!refreshing">↻ Refresh Now</span>
                <span x-show="refreshing" x-cloak>Refreshing…</span>
            </button>
        </div>

        {{-- Errors --}}
        <template x-if="status.errors && Object.keys(status.errors).length > 0">
            <div class="p-3 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg">
                <p class="text-xs font-medium text-red-700 dark:text-red-400 mb-1">Fetch errors:</p>
                <template x-for="[key, msg] in Object.entries(status.errors)" :key="key">
                    <p class="text-xs text-red-600 dark:text-red-400"><span x-text="key"></span>: <span x-text="msg"></span></p>
                </template>
            </div>
        </template>

        {{-- Toast --}}
        <div x-show="toast" x-cloak x-transition class="p-3 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg text-xs text-green-700 dark:text-green-400" x-text="toast"></div>
    </div>

    {{-- Tab: Keyword Gaps --}}
    <div x-show="tab === 'insights'" class="p-6">
        <div x-show="!status.has_data" class="text-center py-8 text-slate-500 dark:text-slate-400 text-sm">
            <p>No data yet. Connect your API key and click <strong>Refresh Now</strong>.</p>
        </div>
        <div x-show="status.has_data" class="space-y-3">
            <p class="text-xs text-slate-500 dark:text-slate-400 mb-3">
                Queries where your site has impressions but ranks position 6–30, or high impressions with low CTR.
                These are your top content opportunities for Bing.
            </p>
            <div class="overflow-x-auto">
                <table class="w-full text-xs">
                    <thead>
                        <tr class="border-b border-slate-200 dark:border-slate-700">
                            <th class="text-left py-2 pr-4 font-medium text-slate-500 dark:text-slate-400">Query</th>
                            <th class="text-right py-2 px-2 font-medium text-slate-500 dark:text-slate-400">Impr.</th>
                            <th class="text-right py-2 px-2 font-medium text-slate-500 dark:text-slate-400">Pos.</th>
                            <th class="text-right py-2 px-2 font-medium text-slate-500 dark:text-slate-400">CTR%</th>
                            <th class="text-right py-2 pl-2 font-medium text-slate-500 dark:text-slate-400">Type</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        <template x-for="gap in status.keyword_gaps" :key="gap.query">
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50">
                                <td class="py-2 pr-4 text-slate-800 dark:text-slate-200 font-medium" x-text="gap.query"></td>
                                <td class="py-2 px-2 text-right text-slate-600 dark:text-slate-400" x-text="gap.impressions.toLocaleString()"></td>
                                <td class="py-2 px-2 text-right text-orange-600 dark:text-orange-400 font-medium" x-text="gap.position"></td>
                                <td class="py-2 px-2 text-right text-slate-600 dark:text-slate-400" x-text="gap.ctr + '%'"></td>
                                <td class="py-2 pl-2 text-right">
                                    <span
                                        :class="gap.gap_type === 'ctr_mismatch' ? 'bg-purple-100 dark:bg-purple-900/30 text-purple-700 dark:text-purple-400' : 'bg-orange-100 dark:bg-orange-900/30 text-orange-700 dark:text-orange-400'"
                                        class="px-1.5 py-0.5 rounded text-[10px] font-medium"
                                        x-text="gap.gap_type === 'ctr_mismatch' ? 'CTR' : 'Rank'"
                                    ></span>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
            <p class="text-xs text-slate-400 dark:text-slate-500 mt-2">
                These gaps feed directly into your daily brief as content action candidates.
            </p>
        </div>
    </div>

    {{-- Tab: Real Queries --}}
    <div x-show="tab === 'queries'" class="p-6">
        <div x-show="!status.has_data" class="text-center py-8 text-slate-500 dark:text-slate-400 text-sm">
            <p>No data yet. Connect your API key and click <strong>Refresh Now</strong>.</p>
        </div>
        <div x-show="status.has_data" class="space-y-3">
            <p class="text-xs text-slate-500 dark:text-slate-400 mb-3">
                Actual search queries driving impressions on Bing. Used as AI visibility probes to measure brand mention rates.
            </p>
            <div class="overflow-x-auto">
                <table class="w-full text-xs">
                    <thead>
                        <tr class="border-b border-slate-200 dark:border-slate-700">
                            <th class="text-left py-2 pr-4 font-medium text-slate-500 dark:text-slate-400">Query</th>
                            <th class="text-right py-2 px-2 font-medium text-slate-500 dark:text-slate-400">Impressions</th>
                            <th class="text-right py-2 px-2 font-medium text-slate-500 dark:text-slate-400">Clicks</th>
                            <th class="text-right py-2 pl-2 font-medium text-slate-500 dark:text-slate-400">Position</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        <template x-for="q in status.top_queries" :key="q.query">
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50">
                                <td class="py-2 pr-4 text-slate-800 dark:text-slate-200 font-medium" x-text="q.query"></td>
                                <td class="py-2 px-2 text-right text-slate-600 dark:text-slate-400" x-text="q.impressions.toLocaleString()"></td>
                                <td class="py-2 px-2 text-right text-slate-600 dark:text-slate-400" x-text="q.clicks"></td>
                                <td class="py-2 pl-2 text-right text-slate-600 dark:text-slate-400" x-text="q.position"></td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Tab: Import CSV --}}
    <div x-show="tab === 'import'" class="p-6 space-y-5">
        <div class="text-sm text-slate-600 dark:text-slate-400">
            <p>Some Bing data is only available as a manual export (no API endpoint). Upload these CSVs to enrich your content pipeline further.</p>
            <div class="mt-3 grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div class="p-3 bg-slate-50 dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700">
                    <p class="text-xs font-semibold text-slate-700 dark:text-slate-300">Topic Portfolio</p>
                    <p class="text-xs text-slate-500 mt-0.5">Topic clusters Bing sees your site covering</p>
                </div>
                <div class="p-3 bg-slate-50 dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700">
                    <p class="text-xs font-semibold text-slate-700 dark:text-slate-300">AI Performance</p>
                    <p class="text-xs text-slate-500 mt-0.5">Bing AI citation and visibility data</p>
                </div>
                <div class="p-3 bg-slate-50 dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700">
                    <p class="text-xs font-semibold text-slate-700 dark:text-slate-300">Citations</p>
                    <p class="text-xs text-slate-500 mt-0.5">Pages Bing AI cites in its answers</p>
                </div>
            </div>
            <p class="text-xs text-slate-400 dark:text-slate-500 mt-2">
                ⚠️ These are <em>optional</em>. Missing files don't block anything — they simply add more context to AI planning.
            </p>
        </div>

        {{-- Upload form --}}
        <form @submit.prevent="importCsv()" class="space-y-3 border-t border-slate-100 dark:border-slate-800 pt-4">
            <div>
                <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">Export Type</label>
                <select x-model="importType" class="w-full text-sm border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-2 focus:ring-orange-400 focus:outline-none">
                    <option value="topic_portfolio">Topic Portfolio</option>
                    <option value="ai_performance">AI Performance</option>
                    <option value="citations">Citations</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">CSV File</label>
                <input type="file" accept=".csv,.txt" @change="importFile = $event.target.files[0]" class="text-xs text-slate-700 dark:text-slate-300 file:mr-3 file:px-3 file:py-1.5 file:rounded-lg file:border-0 file:text-xs file:font-medium file:bg-orange-50 file:text-orange-700 hover:file:bg-orange-100 dark:file:bg-orange-900/30 dark:file:text-orange-400" />
            </div>
            <button
                type="submit"
                :disabled="importing || !importFile"
                class="px-4 py-2 text-xs font-medium bg-orange-500 hover:bg-orange-600 disabled:opacity-50 text-white rounded-lg transition-colors"
            >
                <span x-show="!importing">Upload & Import</span>
                <span x-show="importing" x-cloak>Importing…</span>
            </button>
            <div x-show="importResult" x-cloak x-transition class="p-3 rounded-lg text-xs" :class="importResult?.success ? 'bg-green-50 dark:bg-green-900/20 text-green-700 dark:text-green-400 border border-green-200 dark:border-green-800' : 'bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-400 border border-red-200 dark:border-red-800'" x-text="importResult?.message"></div>
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
                const r = await fetch(statusUrl, { headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content } });
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
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                    body: JSON.stringify({ bing_api_key: this.apiKey })
                });
                const d = await r.json();
                if (d.success) {
                    this.toast = '✓ API key saved.';
                    this.apiKey = '';
                    this.status.has_api_key = true;
                    setTimeout(() => this.toast = '', 3000);
                    await this.refresh();
                }
            } catch (e) { console.error(e); }
            this.saving = false;
        },

        async removeApiKey() {
            if (!confirm('Remove the Bing API key? Bing data will no longer be fetched.')) return;
            const r = await fetch(removeKeyUrl, {
                method: 'DELETE',
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }
            });
            const d = await r.json();
            if (d.success) { this.status.has_api_key = false; this.status.has_data = false; }
        },

        async refresh() {
            this.refreshing = true;
            try {
                const r = await fetch(refreshUrl, {
                    method: 'POST',
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }
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
                const r = await fetch(importCsvUrl, { method: 'POST', headers: { 'Accept': 'application/json' }, body: form });
                this.importResult = await r.json();
            } catch (e) { this.importResult = { success: false, message: e.message }; }
            this.importing = false;
        },
    };
}
</script>
