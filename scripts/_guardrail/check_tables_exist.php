#!/usr/bin/env php
<?php
/**
 * Guardrail Helper: Check Multiple Tables Exist
 * 
 * Checks if multiple tables exist across different connections.
 * Outputs: JSON object with table => "EXISTS"|"MISSING" to STDOUT.
 */

require __DIR__ . '/../../vendor/autoload.php';

$app = require_once __DIR__ . '/../../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

try {
    $tables = [
        'products' => 'products',
        'orders' => 'orders',
        'order_items' => 'orders',
        'inventory_items' => 'inventory',
        'inventory_reservations' => 'inventory',
        'accounting_vouchers' => 'core',
        'accounting_ledger' => 'core',
    ];
    
    $results = [];
    foreach ($tables as $table => $conn) {
        $exists = DB::connection($conn)->getSchemaBuilder()->hasTable($table);
        $results[$table] = $exists ? 'EXISTS' : 'MISSING';
    }
    
    echo json_encode($results) . PHP_EOL;
} catch (Exception $e) {
    fwrite(STDERR, "ERROR: " . $e->getMessage() . "\n");
    exit(1);
}
