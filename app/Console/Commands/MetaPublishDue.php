<?php

namespace App\Console\Commands;

use App\Services\Meta\ContentPublisher;
use Illuminate\Console\Command;

class MetaPublishDue extends Command
{
    protected $signature = 'meta:publish-due';
    protected $description = 'Publish human-approved posts whose scheduled time has arrived (does nothing unless META_PUBLISHING_ENABLED=true)';

    public function handle(ContentPublisher $publisher): int
    {
        if (!ContentPublisher::enabled()) {
            $this->line('Auto-posting is off (META_PUBLISHING_ENABLED=false).');

            return self::SUCCESS;
        }
        $r = $publisher->publishDue();
        $this->line("Posted {$r['posted']}, failed {$r['failed']}, waiting on the daily cap {$r['skipped']}.");

        return $r['failed'] ? self::FAILURE : self::SUCCESS;
    }
}
