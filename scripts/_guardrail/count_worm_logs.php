#!/usr/bin/env php
<?php
/**
 * Guardrail Helper: Count WORM Logs
 * 
 * Counts WORM log entries.
 * Outputs: count to STDOUT.
 */

require __DIR__ . '/../../vendor/autoload.php';

$app = require_once __DIR__ . '/../../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

try {
    $count = \App\Nfts\Models\WormLog::count();
    echo $count . PHP_EOL;
} catch (Exception $e) {
    echo 'failed: ' . $e->getMessage() . PHP_EOL;
    exit(1);
}
