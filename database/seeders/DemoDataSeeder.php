<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        // --- Users ---
        $userId = DB::table('users')->insertGetId([
            'name' => 'Demo User',
            'email' => 'demo@example.com',
            'email_verified_at' => $now,
            'password' => bcrypt('password'),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // Attach role 'user' if exists
        $userRole = DB::table('roles')->where('slug','user')->value('id');
        if ($userRole) {
            DB::table('role_user')->updateOrInsert(['role_id'=>$userRole,'user_id'=>$userId], []);
        }

        // --- Social: posts ---
        DB::table('posts')->insert([
            [
                'title' => 'Hello IMDC',
                'summary' => 'First post',
                'content' => 'Welcome to IMDC demo content.',
                'user_id' => $userId,
                'status' => 'published',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'title' => 'Draft Example',
                'summary' => 'Second post as draft',
                'content' => 'This is a draft post.',
                'user_id' => $userId,
                'status' => 'draft',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        // --- Marketplace: shop/products/inventory ---
        $shopId = DB::table('shops')->insertGetId([
            'name' => 'Demo Shop',
            'owner_id' => $userId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $prodA = DB::table('products')->insertGetId([
            'shop_id' => $shopId,
            'name' => 'IMDC T-Shirt',
            'sku' => 'TS-' . Str::upper(Str::random(6)),
            'price' => 1990,
            'meta' => json_encode(['size'=>'L','color'=>'black']),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $prodB = DB::table('products')->insertGetId([
            'shop_id' => $shopId,
            'name' => 'IMDC Sticker Pack',
            'sku' => 'ST-' . Str::upper(Str::random(6)),
            'price' => 490,
            'meta' => json_encode(['qty'=>10]),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('inventory')->insert([
            ['product_id'=>$prodA,'stock'=>20,'created_at'=>$now,'updated_at'=>$now],
            ['product_id'=>$prodB,'stock'=>100,'created_at'=>$now,'updated_at'=>$now],
        ]);

        // --- Order with items (Observer will adjust stock & total) ---
        $orderId = DB::table('orders')->insertGetId([
            'user_id' => $userId,
            'status' => 'pending',
            'total' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('order_items')->insert([
            ['order_id'=>$orderId,'product_id'=>$prodA,'qty'=>2,'price'=>1990,'created_at'=>$now,'updated_at'=>$now],
            ['order_id'=>$orderId,'product_id'=>$prodB,'qty'=>1,'price'=>490,'created_at'=>$now,'updated_at'=>$now],
        ]);
        // After insert, total will be recalculated by observer: 2*1990 + 1*490 = 4470

        // --- DAO: proposal/vote ---
        $proposalId = DB::table('proposals')->insertGetId([
            'creator_id' => $userId,
            'title' => 'Enable VR_TOUR module',
            'body' => 'Proposal to enable VR tour for the city.',
            'starts_at' => $now,
            'ends_at' => $now->copy()->addDays(7),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('votes')->insert([
            'proposal_id' => $proposalId,
            'user_id' => $userId,
            'value' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}
