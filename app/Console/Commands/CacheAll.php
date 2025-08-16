<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class CacheAll extends Command
{
    protected $signature = 'digitalcity:cache:all';

    protected $description = 'Warm all caches: config, route, view.';

    public function handle(): int
    {
        $this->call('config:clear');
        $this->call('config:cache');
        $this->call('route:clear');
        $this->call('route:cache');
        $this->call('view:clear');
        $this->call('view:cache');

        $this->info('All caches warmed.');

        return self::SUCCESS;
    }
}
