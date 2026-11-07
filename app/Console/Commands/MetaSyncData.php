<?php

namespace App\Console\Commands;

use App\Models\Brand;
use App\Services\Meta\AdInsightsSync;
use App\Services\Meta\CommentSync;
use App\Services\Meta\LeadSync;
use Illuminate\Console\Command;

class MetaSyncData extends Command
{
    protected $signature = 'meta:sync-data {brand? : Brand id (omit for all active brands)} {--only= : comma list of ads,comments,leads}';
    protected $description = 'Pull ad performance by audience, customer comments and lead-ad leads from Meta';

    public function handle(AdInsightsSync $ads, CommentSync $comments, LeadSync $leads): int
    {
        if (!config('meta.access_token')) {
            $this->warn('META_ACCESS_TOKEN is not set; nothing to sync.');

            return self::SUCCESS;
        }
        $only = array_filter(array_map('trim', explode(',', (string) $this->option('only')))) ?: ['ads', 'comments', 'leads'];
        $brands = $this->argument('brand') ? Brand::whereKey($this->argument('brand'))->get() : Brand::where('is_active', true)->get();

        foreach ($brands as $brand) {
            $line = [];
            $errors = [];
            if (in_array('ads', $only, true)) {
                $r = $ads->run($brand->id);
                $line[] = "ad rows {$r['rows']}";
                $errors = array_merge($errors, $r['errors']);
            }
            if (in_array('comments', $only, true)) {
                $r = $comments->run($brand->id);
                $line[] = "new comments {$r['saved']}";
                $errors = array_merge($errors, $r['errors']);
            }
            if (in_array('leads', $only, true)) {
                $r = $leads->run($brand->id);
                $line[] = "new leads {$r['created']}";
                $errors = array_merge($errors, $r['errors']);
            }
            $this->line("{$brand->name}: " . implode(', ', $line));
            foreach ($errors as $e) {
                $this->warn("  {$e}");
            }
        }

        return self::SUCCESS;
    }
}
