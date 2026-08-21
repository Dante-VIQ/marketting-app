<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class CheckScheduledJobs extends Command
{
    protected $signature = 'schedule:check';
    protected $description = 'Check scheduled jobs and their status';

    public function handle()
    {
        $this->info('Checking scheduled jobs...');
        Artisan::call('schedule:list');
        $this->line(Artisan::output());
    }
}