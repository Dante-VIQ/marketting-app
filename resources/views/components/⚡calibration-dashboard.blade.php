<?php

use App\Models\ConfidenceCalibration;
use Livewire\Component;
use Illuminate\Support\Facades\Auth;

new class extends Component
{
       public $brandId;
    public $byType = [];

    public function mount()
    {
        $this->brandId = Auth::user()->active_brand_id;
        $this->loadData();
    }

    public function loadData()
    {
        if (!$this->brandId) {
            $this->byType = [];
            return;
        }

        $rows = ConfidenceCalibration::where('brand_id', $this->brandId)
            ->where('total_predictions', '>=', 5)
            ->orderBy('opportunity_type')
            ->orderBy('confidence_bucket')
            ->get();

        $this->byType = $rows->groupBy('opportunity_type')->map(function ($group) {
            return $group->map(function ($cal) {
                return [
                    'range'    => sprintf('%.1f–%.1f', $cal->confidence_bucket / 10, ($cal->confidence_bucket + 1) / 10),
                    'samples'  => $cal->total_predictions,
                    'accuracy' => $cal->actual_accuracy,
                    'drift'    => round($cal->actual_accuracy - ($cal->confidence_bucket / 10), 3),
                ];
            })->values()->toArray();
        })->toArray();
    }
};
?>

<div class="space-y-8">
    @if(empty($byType))
        <div class="rounded-2xl border border-slate-800/80 bg-slate-900/40 p-12 text-center">
            <div class="text-4xl mb-3">📊</div>
            <p class="text-slate-400">Not enough data yet.</p>
            <p class="text-sm text-slate-500 mt-1">
                Calibration starts once 5+ outcomes are recorded per confidence bucket.
            </p>
        </div>
    @else
        @foreach($byType as $type => $buckets)
            <div class="rounded-2xl border border-white/10 bg-slate-900/40 backdrop-blur-xl p-6">
                <h3 class="text-lg font-bold text-white mb-4 font-mono uppercase tracking-wider">
                    {{ str_replace('_', ' ', $type) }}
                </h3>

                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="text-left text-xs font-mono text-slate-500 uppercase tracking-wider">
                                <th class="pb-3 pr-6">Bucket</th>
                                <th class="pb-3 pr-6 text-right">Samples</th>
                                <th class="pb-3 pr-6 text-right">Accuracy</th>
                                <th class="pb-3 text-right">Drift</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/5">
                            @foreach($buckets as $b)
                                <tr>
                                    <td class="py-3 pr-6 font-mono text-slate-300">{{ $b['range'] }}</td>
                                    <td class="py-3 pr-6 text-right text-slate-400">{{ $b['samples'] }}</td>
                                    <td class="py-3 pr-6 text-right text-slate-200 font-mono">
                                        {{ number_format($b['accuracy'] * 100, 1) }}%
                                    </td>
                                    <td class="py-3 text-right">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-mono
                                            {{ $b['drift'] < -0.05 ? 'bg-rose-500/10 text-rose-400 border border-rose-500/20' : '' }}
                                            {{ abs($b['drift']) <= 0.05 ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : '' }}
                                            {{ $b['drift'] > 0.05 ? 'bg-sky-500/10 text-sky-400 border border-sky-500/20' : '' }}
                                        ">
                                            {{ $b['drift'] >= 0 ? '+' : '' }}{{ number_format($b['drift'] * 100, 1) }}%
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endforeach
    @endif
</div>