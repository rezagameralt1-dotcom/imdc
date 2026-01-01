#!/usr/bin/env php
<?php
/**
 * Guardrail Helper: Check Database Exists
 * 
 * Checks if a database exists in PostgreSQL.
 * Outputs: "yes" or "no" to STDOUT.
 */

require __DIR__ . '/../../vendor/autoload.php';

$app = require_once __DIR__ . '/../../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$domain = $argv[1] ?? null;
$dbName = $argv[2] ?? null;

if (!$domain || !$dbName) {
    fwrite(STDERR, "Usage: check_db_exists.php <domain> <db_name>\n");
    exit(1);
}

try {
    $host = config("database.connections.{$domain}.host");
    $port = config("database.connections.{$domain}.port");
    $user = config("database.connections.{$domain}.username");
    $pass = config("database.connections.{$domain}.password");
    
    $pdo = new PDO("pgsql:host={$host};port={$port};dbname=postgres", $user, $pass);
    $stmt = $pdo->query("SELECT 1 FROM pg_database WHERE datname=" . $pdo->quote($dbName));
    echo $stmt->fetchColumn() ? 'yes' : 'no';
    echo PHP_EOL;
} catch (Exception $e) {
    echo 'no' . PHP_EOL;
    exit(1);
}
