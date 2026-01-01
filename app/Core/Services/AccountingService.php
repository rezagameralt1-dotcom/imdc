<?php

namespace App\Core\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AccountingService
{
    /**
     * Check if accounting sync is enabled via feature flag
     */
    public function isEnabled(): bool
    {
        // Default ON for local, OFF for production (unless explicitly enabled)
        $env = config('app.env', 'local');
        $explicit = config('accounting.sync_enabled', null);
        
        if ($explicit !== null) {
            return (bool) $explicit;
        }
        
        // Default: ON for local, OFF for others
        return $env === 'local';
    }

    /**
     * Create accounting voucher for order payment
     * 
     * @param string $orderId
     * @param float $amount
     * @param string $currency
     * @param string|null $traceId
     * @return string|null Voucher ID or null if disabled
     */
    public function createPaymentVoucher(string $orderId, float $amount, string $currency = 'USD', ?string $traceId = null): ?string
    {
        if (!$this->isEnabled()) {
            Log::debug('Accounting sync disabled, skipping voucher creation', [
                'order_id' => $orderId,
                'trace_id' => $traceId,
            ]);
            return null;
        }

        try {
            return DB::connection('core')->transaction(function () use ($orderId, $amount, $currency, $traceId) {
                $voucherNumber = 'PAY-' . strtoupper(substr($orderId, 0, 8)) . '-' . date('YmdHis');
                $voucherId = (string) \Illuminate\Support\Str::uuid();
                
                DB::connection('core')->table('accounting_vouchers')->insert([
                    'id' => $voucherId,
                    'voucher_number' => $voucherNumber,
                    'voucher_type' => 'payment',
                    'reference_id' => $orderId,
                    'reference_type' => 'order',
                    'total_amount' => $amount,
                    'currency' => $currency,
                    'status' => 'posted',
                    'description' => "Payment for order {$orderId}",
                    'trace_id' => $traceId,
                    'posted_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // Create voucher entries (double-entry bookkeeping)
                // Debit: Accounts Receivable (or Cash)
                DB::connection('core')->table('accounting_voucher_entries')->insert([
                    'id' => (string) \Illuminate\Support\Str::uuid(),
                    'voucher_id' => $voucherId,
                    'account_code' => '1100', // Cash/Accounts Receivable
                    'account_name' => 'Cash',
                    'debit' => $amount,
                    'credit' => 0,
                    'description' => "Payment received for order {$orderId}",
                    'sequence' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // Credit: Revenue
                DB::connection('core')->table('accounting_voucher_entries')->insert([
                    'id' => (string) \Illuminate\Support\Str::uuid(),
                    'voucher_id' => $voucherId,
                    'account_code' => '4000', // Revenue
                    'account_name' => 'Sales Revenue',
                    'debit' => 0,
                    'credit' => $amount,
                    'description' => "Revenue from order {$orderId}",
                    'sequence' => 2,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // Post to ledger
                DB::connection('core')->table('accounting_ledger')->insert([
                    'id' => (string) \Illuminate\Support\Str::uuid(),
                    'account_code' => '1100',
                    'account_name' => 'Cash',
                    'debit' => $amount,
                    'credit' => 0,
                    'currency' => $currency,
                    'reference_type' => 'order',
                    'reference_id' => $orderId,
                    'description' => "Payment for order {$orderId}",
                    'trace_id' => $traceId,
                    'posted_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::connection('core')->table('accounting_ledger')->insert([
                    'id' => (string) \Illuminate\Support\Str::uuid(),
                    'account_code' => '4000',
                    'account_name' => 'Sales Revenue',
                    'debit' => 0,
                    'credit' => $amount,
                    'currency' => $currency,
                    'reference_type' => 'order',
                    'reference_id' => $orderId,
                    'description' => "Revenue from order {$orderId}",
                    'trace_id' => $traceId,
                    'posted_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                return $voucherId;
            });
        } catch (\Throwable $e) {
            Log::error('Failed to create accounting voucher', [
                'order_id' => $orderId,
                'error' => $e->getMessage(),
                'trace_id' => $traceId,
            ]);
            // Don't throw - accounting is non-critical
            return null;
        }
    }
}
