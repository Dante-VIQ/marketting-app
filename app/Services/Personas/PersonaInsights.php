<?php

namespace App\Services\Personas;

use App\Models\Lead;
use App\Models\Persona;

class PersonaInsights
{
    /** Personas with their confidence, for the page. */
    public function personas(int $brandId): array
    {
        return Persona::where('brand_id', $brandId)->with('evidence')->orderByDesc('is_active')->orderBy('name')->get()
            ->map(fn (Persona $p) => [
                'model' => $p,
                'confidence' => PersonaConfidence::assess($p->evidence->map(fn ($e) => [
                    'kind' => $e->kind, 'observed_on' => $e->observed_on?->toDateString(),
                ])->all()),
            ])->all();
    }

    /** What real leads look like, as a starting point for hypotheses. Null/empty means unknown, never zero-by-default. */
    public function leadSignals(int $brandId): array
    {
        $leads = Lead::where('brand_id', $brandId);
        $total = (clone $leads)->count();
        $top = fn (string $col) => $total === 0 ? [] : (clone $leads)->whereNotNull($col)->where($col, '!=', '')
            ->selectRaw("$col as label, COUNT(*) as n")->groupBy($col)->orderByDesc('n')->limit(5)->get()
            ->map(fn ($r) => ['label' => (string) $r->label, 'count' => (int) $r->n])->all();

        return ['total' => $total, 'by_category' => $top('category'), 'by_source' => $top('source')];
    }

    /** Compact block for the agent: what we believe about the audience, and how sure we are. */
    public function summary(int $brandId): array
    {
        $rows = array_filter($this->personas($brandId), fn ($r) => $r['model']->is_active);

        return [
            'count' => count($rows),
            'personas' => array_values(array_map(fn ($r) => [
                'name' => $r['model']->name,
                'confidence' => $r['confidence']['level'],
                'stale' => $r['confidence']['stale'],
                'goals' => array_slice($r['model']->goals ?? [], 0, 3),
                'pains' => array_slice($r['model']->pains ?? [], 0, 3),
                'objections' => array_slice($r['model']->objections ?? [], 0, 3),
                'channels' => array_slice($r['model']->channels ?? [], 0, 4),
            ], $rows)),
            'note' => count($rows) === 0
                ? 'No personas defined yet. Do not assume an audience; recommend research before audience-specific claims.'
                : 'Treat "hypothesis" personas as guesses, not facts.',
        ];
    }
}
