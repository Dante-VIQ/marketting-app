<?php

namespace App\Observers;

use App\Models\Brand;
use App\Models\KnowledgeBase;
use App\Services\AI\BrandContextAssembler;

/**
 * Flushes the BrandContextAssembler cache whenever brand-related data changes.
 *
 * Register in AppServiceProvider::boot():
 *   Brand::observe(BrandContextObserver::class);
 *   KnowledgeBase::observe(BrandContextObserver::class);
 */
class BrandContextObserver
{
    // ── Brand model hooks ───────────────────────────────────────────────────────

    public function updated(Brand $brand): void
    {
        BrandContextAssembler::flush($brand);
    }

    public function deleted(Brand $brand): void
    {
        BrandContextAssembler::flush($brand);
    }

    // ── KnowledgeBase model hooks ────────────────────────────────────────────────
    // KnowledgeBase events pass a KnowledgeBase model; we load its brand.

    /**
     * @param  Brand|KnowledgeBase  $model
     */
    public function saved($model): void
    {
        $this->flushForModel($model);
    }

    /**
     * @param  Brand|KnowledgeBase  $model
     */
    public function forceDeleted($model): void
    {
        $this->flushForModel($model);
    }

    // ── Helper ──────────────────────────────────────────────────────────────────

    private function flushForModel($model): void
    {
        if ($model instanceof Brand) {
            BrandContextAssembler::flush($model);
        } elseif ($model instanceof KnowledgeBase && $model->brand_id) {
            $brand = Brand::find($model->brand_id);
            if ($brand) {
                BrandContextAssembler::flush($brand);
            }
        }
    }
}
