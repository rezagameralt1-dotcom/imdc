<?php

declare(strict_types=1);

namespace App\Core\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * ShopCustomerResolver
 *
 * هدف: نگاشت کاربر Core به شناسه مبهم shop_customer_id
 * این شناسه در ماژول‌هایی مثل Orders استفاده می‌شود تا وابستگی مستقیم به users حذف شود.
 *
 * جدول: pgsql.shop_customers
 * - id (bigint)
 * - user_id (bigint) UNIQUE
 * - shop_customer_id (char(36)) UNIQUE
 * - created_at, updated_at
 */
final class ShopCustomerResolver
{
    public function resolveForUserId(int $userId): string
    {
        $row = DB::connection('core')
            ->table('shop_customers')
            ->where('user_id', $userId)
            ->first();

        if ($row && isset($row->shop_customer_id) && is_string($row->shop_customer_id) && $row->shop_customer_id !== '') {
            return $row->shop_customer_id;
        }

        $alias = (string) Str::uuid();

        $ok = DB::connection('core')
            ->table('shop_customers')
            ->updateOrInsert(
                ['user_id' => $userId],
                ['shop_customer_id' => $alias, 'created_at' => now(), 'updated_at' => now()]
            );

        if (! $ok) {
            throw new RuntimeException('Failed to resolve shop_customer_id');
        }

        return $alias;
    }
}
