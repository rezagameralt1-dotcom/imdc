<?php
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();
use Spatie\Permission\Models\Permission;
$perms = ['orders.read','orders.write','products.manage','inventory.read','inventory.manage'];
foreach ($perms as $p) Permission::findOrCreate($p,'web');
$user = App\Models\User::where('email','admin@example.com')->firstOrFail();
$user->givePermissionTo($perms);
echo "✅ granted to {$user->email}\n";
