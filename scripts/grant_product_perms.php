<?php
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Spatie\Permission\Models\Permission;

Permission::findOrCreate('products.manage','web');
// اگه پروژه‌ت پرمیژن‌های جدا برای موجودی داره، اینا هم (اگر وجود دارند) مفیدند:
@Permission::findOrCreate('inventory.read','web');
@Permission::findOrCreate('inventory.manage','web');

$user = App\Models\User::where('email','admin@example.com')->firstOrFail();
$user->givePermissionTo(array_filter([
  'products.manage',
  // 'inventory.read',
  // 'inventory.manage',
]));

echo "Granted inventory permissions to {$user->email}\n";
