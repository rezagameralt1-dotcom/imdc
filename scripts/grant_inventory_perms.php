<?php
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Spatie\Permission\Models\Permission;

foreach (['inventory.read','inventory.manage'] as $perm) {
  Permission::findOrCreate($perm,'web');
}
$user = App\Models\User::where('email','admin@example.com')->firstOrFail();
$user->givePermissionTo(['inventory.read','inventory.manage']);
echo "Granted inventory.* to {$user->email}\n";
