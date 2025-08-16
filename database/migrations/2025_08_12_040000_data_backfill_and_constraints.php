<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::beginTransaction();
        try {
            $this->backfillPostsStatus();
            $this->backfillMessagesReadAt();
            $this->enforceConstraints();
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function down(): void
    {
        // Non-destructive rollback (constraints are kept)
    }

    private function backfillPostsStatus(): void
    {
        if (! Schema::hasTable('posts')) {
            return;
        }

        // PostgreSQL-safe batching: pick IDs then update WHERE IN (no LIMIT in UPDATE)
        do {
            $ids = DB::table('posts')
                ->select('id')
                ->whereNull('status')
                ->limit(1000)
                ->pluck('id');
            if ($ids->isEmpty()) {
                break;
            }

            DB::table('posts')->whereIn('id', $ids)->update(['status' => 'draft']);
        } while (true);
    }

    private function backfillMessagesReadAt(): void
    {
        if (! Schema::hasTable('messages')) {
            return;
        }

        do {
            $ids = DB::table('messages')
                ->select('id')
                ->whereNull('read_at')
                ->where('is_read', true)
                ->limit(1000)
                ->pluck('id');
            if ($ids->isEmpty()) {
                break;
            }

            DB::table('messages')->whereIn('id', $ids)->update(['read_at' => now()]);
        } while (true);
    }

    private function enforceConstraints(): void
    {
        // Example constraints with guards
        if (Schema::hasTable('orders')) {
            // Ensure status default
            try {
                DB::statement("ALTER TABLE orders ALTER COLUMN status SET DEFAULT 'pending'");
            } catch (\Throwable $e) { /* ignore if already set */
            }
        }
        if (Schema::hasTable('posts')) {
            // Ensure non-null title
            try {
                DB::statement('ALTER TABLE posts ALTER COLUMN title SET NOT NULL');
            } catch (\Throwable $e) { /* ignore */
            }
        }
    }
};
