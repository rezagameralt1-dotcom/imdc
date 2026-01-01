#!/usr/bin/env php
<?php
/**
 * Guardrail Helper: Verify DID Profile
 * 
 * Verifies DID profile matches expected values.
 * Outputs: "verified" or error message to STDOUT.
 */

require __DIR__ . '/../../vendor/autoload.php';

$app = require_once __DIR__ . '/../../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$userId = $argv[1] ?? null;
$expectedDid = $argv[2] ?? null;

if (!$userId || !$expectedDid) {
    fwrite(STDERR, "Usage: verify_did_profile.php <user_id> <expected_did>\n");
    exit(1);
}

try {
    $profile = \App\Dids\Models\DidProfile::where('user_id', $userId)->first();
    
    if (!$profile) {
        echo 'not_found' . PHP_EOL;
        exit(1);
    }
    
    if ($profile->did !== $expectedDid) {
        echo 'did_mismatch' . PHP_EOL;
        exit(1);
    }
    
    if ($profile->display_name !== 'Updated DID Name') {
        echo 'name_mismatch' . PHP_EOL;
        exit(1);
    }
    
    if ($profile->wallet_address !== '0xupdated123') {
        echo 'wallet_mismatch' . PHP_EOL;
        exit(1);
    }
    
    echo 'ok' . PHP_EOL;
} catch (Exception $e) {
    echo 'failed: ' . $e->getMessage() . PHP_EOL;
    exit(1);
}
