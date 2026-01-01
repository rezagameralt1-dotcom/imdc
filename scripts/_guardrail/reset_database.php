#!/usr/bin/env php
<?php
/**
 * Guardrail Helper: Reset Database
 * 
 * Drops and recreates a database.
 * Outputs: "ok" to STDOUT on success.
 */

require __DIR__ . '/../../vendor/autoload.php';

$app = require_once __DIR__ . '/../../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$domain = $argv[1] ?? null;
$dbName = $argv[2] ?? null;

if (!$domain || !$dbName) {
    fwrite(STDERR, "Usage: reset_database.php <domain> <db_name>\n");
    exit(1);
}

try {
    $host = config("database.connections.{$domain}.host");
    $port = config("database.connections.{$domain}.port");
    $user = config("database.connections.{$domain}.username");
    $pass = config("database.connections.{$domain}.password");
    
    $pdo = new PDO("pgsql:host={$host};port={$port};dbname=postgres", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("DROP DATABASE IF EXISTS {$dbName} WITH (FORCE)");
    $pdo->exec("CREATE DATABASE {$dbName}");
    echo 'ok' . PHP_EOL;
} catch (Exception $e) {
    fwrite(STDERR, "ERROR: " . $e->getMessage() . "\n");
    exit(1);
}
