<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;

class VerifySanctumCore extends Command
{
    protected $signature = 'imdc:verify-sanctum-core';
    protected $description = 'Verify Sanctum is configured to use core database connection';

    public function handle(): int
    {
        $errors = [];

        // Check 1: personal_access_tokens table exists on core connection
        if (!Schema::connection('core')->hasTable('personal_access_tokens')) {
            $errors[] = "personal_access_tokens table does not exist on core connection";
        }

        // Check 2: PersonalAccessToken model uses core connection
        $tokenModel = Sanctum::personalAccessTokenModel();
        $tokenInstance = new $tokenModel();
        $modelConnection = $tokenInstance->getConnectionName();

        if ($modelConnection !== 'core') {
            $errors[] = "PersonalAccessToken model uses connection '{$modelConnection}' instead of 'core'";
        }

        // Check 3: Verify we can query the table on core connection
        try {
            $count = DB::connection('core')->table('personal_access_tokens')->count();
            $this->info("✓ personal_access_tokens table exists on core (count: {$count})");
        } catch (\Exception $e) {
            $errors[] = "Cannot query personal_access_tokens on core connection: " . $e->getMessage();
        }

        if (empty($errors)) {
            $this->info("✓ PersonalAccessToken model connection: {$modelConnection}");
            $this->info("✓ Sanctum is correctly configured to use core database");
            return 0;
        }

        $this->error("✗ Sanctum core connection verification failed:");
        foreach ($errors as $error) {
            $this->error("  - {$error}");
        }
        return 1;
    }
}
