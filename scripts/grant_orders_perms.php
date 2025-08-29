<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Spatie\Permission\Models\Permission;

Permission::findOrCreate('orders.read', 'web');
Permission::findOrCreate('orders.write', 'web');

$user = App\Models\User::where('email','admin@example.com')->firstOrFail();
$user->givePermissionTo(['orders.read','orders.write']);

echo "Granted orders.* to {$user->email}\n";
