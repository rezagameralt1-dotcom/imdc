#!/usr/bin/env php
<?php
/**
 * Guardrail Helper: Check Table Exists
 * 
 * Checks if a table exists in a database connection.
 * Outputs: "yes" or "no" to STDOUT.
 */

require __DIR__ . '/../../vendor/autoload.php';

$app = require_once __DIR__ . '/../../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$domain = $argv[1] ?? null;
$tableName = $argv[2] ?? null;

if (!$domain || !$tableName) {
    fwrite(STDERR, "Usage: check_table_exists.php <domain> <table_name>\n");
    exit(1);
}

try {
    $exists = DB::connection($domain)->getSchemaBuilder()->hasTable($tableName);
    echo $exists ? 'yes' : 'no';
    echo PHP_EOL;
} catch (Exception $e) {
    echo 'no' . PHP_EOL;
    exit(1);
}
