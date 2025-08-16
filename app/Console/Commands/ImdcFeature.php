<?php

namespace App\Console\Commands;

use App\Support\FeatureFlags;
use Illuminate\Console\Command;

class ImdcFeature extends Command
{
    protected $signature = 'imdc:feature 
        {action : get|set|on|off|toggle|list}
        {key? : Feature key like EXCHANGE, DAO}
        {--meta= : JSON metadata to attach when setting}';

    protected $description = 'Manage runtime feature flags (DB + ENV fallback)';

    public function handle(): int
    {
        $action = strtolower((string) $this->argument('action'));
        $key = $this->argument('key');

        switch ($action) {
            case 'list':
                $rows = FeatureFlags::all();
                if (! $rows) {
                    $this->info('No feature flags in DB (ENV-only or none).');

                    return 0;
                }
                $this->table(['Key', 'Enabled', 'Meta'], array_map(function ($r) {
                    return [$r['key'], $r['enabled'] ? 'on' : 'off', $r['meta'] ? json_encode($r['meta']) : '-'];
                }, $rows));

                return 0;

            case 'get':
                if (! $key) {
                    $this->error('Key is required');

                    return 1;
                }
                $val = FeatureFlags::enabled($key, null);
                $this->line(sprintf('%s => %s', strtoupper($key), $val ? 'on' : 'off'));

                return 0;

            case 'set':
                if (! $key) {
                    $this->error('Key is required');

                    return 1;
                }
                $meta = $this->option('meta') ? json_decode((string) $this->option('meta'), true) : [];
                $state = $this->confirm('Enable feature?', true);
                FeatureFlags::set($key, $state, $meta ?: []);
                $this->info(sprintf('%s set to %s', strtoupper($key), $state ? 'on' : 'off'));

                return 0;

            case 'on':
                if (! $key) {
                    $this->error('Key is required');

                    return 1;
                }
                FeatureFlags::set($key, true);
                $this->info(sprintf('%s => on', strtoupper($key)));

                return 0;

            case 'off':
                if (! $key) {
                    $this->error('Key is required');

                    return 1;
                }
                FeatureFlags::set($key, false);
                $this->info(sprintf('%s => off', strtoupper($key)));

                return 0;

            case 'toggle':
                if (! $key) {
                    $this->error('Key is required');

                    return 1;
                }
                $curr = FeatureFlags::enabled($key, false);
                FeatureFlags::set($key, ! $curr);
                $this->info(sprintf('%s => %s', strtoupper($key), ! $curr ? 'on' : 'off'));

                return 0;
        }

        $this->error('Unknown action. Use: get|set|on|off|toggle|list');

        return 1;
    }
}
