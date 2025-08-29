<?php

namespace App\Services\Marketplace;

use App\Models\AccountingVoucher;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

class AccountingSyncService
{
    public function createVoucher(int $orderId, string $type, float $amount, array $payload=[]): AccountingVoucher {
        return AccountingVoucher::create([
            'order_id'=>$orderId, 'type'=>$type, 'amount'=>$amount,
            'status'=> Config::get('feature_flags.ACCOUNTING_SYNC') ? 'pending' : 'synced',
            'payload'=>$payload,
            'synced_at'=> Config::get('feature_flags.ACCOUNTING_SYNC') ? null : now(),
        ]);
    }

    public function attemptSync(AccountingVoucher $voucher): AccountingVoucher {
        if (!config('feature_flags.ACCOUNTING_SYNC')) return $voucher;
        // درایور ساده/آفلاین: همیشه موفق
        $voucher->update(['status'=>'synced','synced_at'=>now()]);
        Log::info('Accounting synced', ['voucher_id'=>$voucher->id]);
        return $voucher;
    }
}
