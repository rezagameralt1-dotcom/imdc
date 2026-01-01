#!/usr/bin/env php
<?php
/**
 * Guardrail Helper: Mint Token for Admin User
 * 
 * Finds user with Admin role via Spatie, falls back to first user.
 * Outputs: token secret to STDOUT (machine-parseable).
 */

require __DIR__ . '/../../vendor/autoload.php';

$app = require_once __DIR__ . '/../../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;

try {
    // Try to find user with Admin role via Spatie
    $adminUser = User::on('core')->whereHas('roles', function($q) {
        $q->where('name', 'Admin');
    })->first();
    
    if (!$adminUser) {
        // Fallback to first available user
        $adminUser = User::on('core')->first();
    }
    
    if (!$adminUser) {
        fwrite(STDERR, "ERROR: No users found in core database\n");
        exit(1);
    }
    
    // Mint token for the user
    $token = $adminUser->createToken('diagnostic')->plainTextToken;
    $secret = $token;
    if (is_string($token) && str_contains($token, '|')) {
        $parts = explode('|', $token, 2);
        $secret = $parts[1] ?? '';
    }
    
    if (empty($secret)) {
        fwrite(STDERR, "ERROR: Failed to extract token secret\n");
        exit(1);
    }
    
    echo $secret . PHP_EOL;
} catch (Exception $e) {
    fwrite(STDERR, "ERROR: " . $e->getMessage() . "\n");
    exit(1);
}
