<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

/**
 * Mint Debug Token
 * 
 * Mint a debug Sanctum token for a user (prints token only)
 */
class MintDebugToken extends Command
{
    protected $signature = 'imdc:mint-debug-token {--email= : Exact user email to mint token for} {--database=core : Database connection to use}';
    protected $description = 'Mint a debug Sanctum token for a user (prints token only)';

    public function handle(): int
    {
        try {
            // Get database connection (default: core) - NEVER use default connection
            $db = (string) ($this->option('database') ?: 'core');
            $email = (string) $this->option('email');
            
            // Force ALL user operations on the specified connection (default: core)
            // Use User::on() to ensure connection is set explicitly - NEVER hit default DB
            $targetEmail = $email !== '' ? $email : 'debug@imdc.local';
            
            // Try to find debug user by email on the specified connection
            $user = \App\Models\User::on($db)->where('email', $targetEmail)->first();
            
            // If not found, create IMDC Debug user on the same connection
            if (!$user) {
                try {
                    $user = \App\Models\User::on($db)->create([
                        'name' => 'IMDC Debug',
                        'email' => 'debug@imdc.local',
                        'password' => \Illuminate\Support\Facades\Hash::make(bin2hex(random_bytes(32))),
                        'email_verified_at' => now(),
                    ]);
                } catch (\Exception $e) {
                    // Write concise error to STDERR (no stack trace to prevent terminal crash)
                    fwrite(STDERR, "ERROR: Failed to create user on connection '{$db}': " . $e->getMessage() . "\n");
                    return 1;
                }
            }
            
            // Create Sanctum token with name "diagnostic"
            // Ensure user model uses the correct connection for token creation
            try {
                $plain = $user->createToken('diagnostic')->plainTextToken;
            } catch (\Exception $e) {
                // Write concise error to STDERR (no stack trace to prevent terminal crash)
                fwrite(STDERR, "ERROR: Failed to mint token: " . $e->getMessage() . "\n");
                return 1;
            }
            
            // Extract token_secret (Sanctum format is "token_id|token_secret")
            $secret = $plain;
            if (is_string($plain) && str_contains($plain, '|')) {
                $parts = explode('|', $plain, 2);
                $secret = $parts[1] ?? '';
            }
            
            // If secret is empty, fail
            if (empty($secret)) {
                fwrite(STDERR, "ERROR: Failed to extract token secret\n");
                return 1;
            }
            
            // Output ONLY the token_secret to STDOUT (no extra text, scripts can capture cleanly)
            // Use fwrite to STDOUT to ensure clean output even in wrappers
            // NEVER output stack traces or diagnostics to STDOUT (prevents terminal crash)
            fwrite(STDOUT, $secret);
            fwrite(STDOUT, "\n");
            
            return 0;
        } catch (\Throwable $e) {
            // Write concise error to STDERR (catch all exceptions including \Error)
            // NEVER dump stack traces to STDOUT (prevents terminal crash)
            fwrite(STDERR, "ERROR: " . $e->getMessage() . "\n");
            return 1;
        }
    }
}
