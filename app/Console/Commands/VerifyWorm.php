<?php

namespace App\Console\Commands;

use App\Services\Worm\WormLogService;
use Illuminate\Console\Command;

class VerifyWorm extends Command
{
    protected $signature = 'imdc:verify-worm';
    protected $description = 'Verify WORM log chain integrity';

    public function handle(): int
    {
        // Guardrail: Verify schema matches expected columns
        $expectedColumns = ['id', 'event_type', 'entity_type', 'entity_id', 'payload_json', 'prev_hash', 'hash', 'created_at'];
        $schemaCheck = $this->verifySchema($expectedColumns);
        if (!$schemaCheck['valid']) {
            $this->error('✗ WORM schema mismatch:');
            $this->error("  {$schemaCheck['error']}");
            return 1;
        }

        $service = new WormLogService();
        $result = $service->verifyChain();

        if ($result['valid']) {
            $this->info('✓ WORM chain integrity verified');
            return 0;
        }

        $this->error('✗ WORM chain verification failed:');
        foreach ($result['errors'] as $error) {
            $this->error("  - {$error}");
        }

        // Print detailed diagnostics for first mismatch
        if (!empty($result['diagnostics'])) {
            $diag = $result['diagnostics'];
            $this->line('');
            $this->warn('Detailed diagnostics for first mismatch:');
            $this->line("  Row ID: {$diag['row_id']}");
            $this->line("  Expected hash: {$diag['expected_hash']}");
            $this->line("  Actual hash: {$diag['actual_hash']}");
            $this->line("  Occurred at (normalized): {$diag['occurred_at_normalized']}");
            $this->line("  Payload JSON (canonical, first 200 chars): {$diag['payload_json_canonical_preview']}");
        }

        return 1;
    }

    /**
     * Verify worm_logs table schema matches expected columns
     *
     * @param array $expectedColumns
     * @return array ['valid' => bool, 'error' => string|null]
     */
    private function verifySchema(array $expectedColumns): array
    {
        try {
            $columns = \Illuminate\Support\Facades\DB::connection('nfts')
                ->select("SELECT column_name FROM information_schema.columns WHERE table_schema = 'public' AND table_name = 'worm_logs' ORDER BY column_name");
            
            $actualColumns = array_map(fn($col) => $col->column_name, $columns);
            
            $missing = array_diff($expectedColumns, $actualColumns);
            $extra = array_diff($actualColumns, $expectedColumns);
            
            if (!empty($missing) || !empty($extra)) {
                $errors = [];
                if (!empty($missing)) {
                    $errors[] = 'Missing columns: ' . implode(', ', $missing);
                }
                if (!empty($extra)) {
                    $errors[] = 'Unexpected columns: ' . implode(', ', $extra);
                }
                return ['valid' => false, 'error' => implode('; ', $errors)];
            }
            
            return ['valid' => true, 'error' => null];
        } catch (\Exception $e) {
            return ['valid' => false, 'error' => 'Schema check failed: ' . $e->getMessage()];
        }
    }
}
