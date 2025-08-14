<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PromoteAdmin extends Command
{
    protected $signature = 'digitalcity:admin:promote {email}';

    protected $description = 'Promote a user to admin by email.';

    public function handle(): int
    {
        $email = $this->argument('email');
        /** @var User|null $user */
        $user = User::where('email', $email)->first();

        if (! $user) {
            $this->error("User not found: {$email}");

            return self::FAILURE;
        }

        DB::transaction(function () use ($user) {
            $user->is_admin = true;
            if (is_null($user->email_verified_at)) {
                $user->email_verified_at = now();
            }
            $user->save();
        });

        $this->info("User promoted to admin: {$email}");

        return self::SUCCESS;
    }
}
