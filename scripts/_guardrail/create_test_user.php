#!/usr/bin/env php
<?php
/**
 * Guardrail Helper: Create Test User
 * 
 * Creates a test user with unique email.
 * Outputs: user ID to STDOUT.
 */

require __DIR__ . '/../../vendor/autoload.php';

$app = require_once __DIR__ . '/../../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;

try {
    $user = User::firstOrCreate(
        ['email' => 'nft-test-user-' . time() . '@test.local'],
        ['name' => 'NFT Test User', 'password' => bcrypt('test123')]
    );
    echo $user->id . PHP_EOL;
} catch (Exception $e) {
    echo 'failed: ' . $e->getMessage() . PHP_EOL;
    exit(1);
}
