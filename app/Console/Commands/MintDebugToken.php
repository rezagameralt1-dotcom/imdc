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
    protected $signature = 'imdc:mint-debug-token {--email= : Exact user email to mint token for}';
    protected $description = 'Mint a debug Sanctum token for a user (prints token only)';

    public function handle(): int
    {
        $email = (string) $this->option('email');
        
        // If email provided: find user by exact email, else: first user by ascending id
        if ($email !== '') {
            $user = \App\Models\User::query()->where('email', $email)->first();
        } else {
            $user = \App\Models\User::query()->orderBy('id')->first();
        }
        
        // If user not found: print error to STDERR and exit non-zero
        if (!$user) {
            fwrite(STDERR, "ERROR: User not found\n");
            return self::FAILURE;
        }
        
        // Create Sanctum token with name "diagnostic"
        $plain = $user->createToken('diagnostic')->plainTextToken;
        
        // Extract token_secret (Sanctum format is "token_id|token_secret")
        $secret = $plain;
        if (is_string($plain) && str_contains($plain, '|')) {
            $parts = explode('|', $plain, 2);
            $secret = $parts[1] ?? '';
        }
        
        // If secret is empty, fail
        if (empty($secret)) {
            fwrite(STDERR, "ERROR: Failed to mint token\n");
            return self::FAILURE;
        }
        
        // Output ONLY the token_secret (no token_id prefix)
        $this->output->writeln($secret);
        
        return self::SUCCESS;
    }
}
