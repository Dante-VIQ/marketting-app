<?php

namespace App\Console\Commands;

use App\Models\Brand;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class IssueAgentKey extends Command
{
    protected $signature = 'agent:issue-key {brand : Brand id} {--revoke : Remove the brand key instead}';
    protected $description = 'Issue (or revoke) a per-brand agent API key. The key is shown once and only its hash is stored.';

    public function handle(): int
    {
        $brand = Brand::find($this->argument('brand'));
        if (!$brand) {
            $this->error('Brand not found.');
            return self::FAILURE;
        }

        if ($this->option('revoke')) {
            $brand->forceFill(['agent_api_key_hash' => null])->save();
            $this->info("Revoked agent key for brand {$brand->id} ({$brand->name}).");
            return self::SUCCESS;
        }

        $key = 'vak_' . Str::random(48);
        $brand->forceFill(['agent_api_key_hash' => hash('sha256', $key)])->save();

        $this->info("Agent key for brand {$brand->id} ({$brand->name}) — shown once, store it now:");
        $this->line($key);
        $this->comment('Set it as LARAVEL_API_KEY (and BRAND_ID=' . $brand->id . ') for that brand\'s agent process.');

        return self::SUCCESS;
    }
}
