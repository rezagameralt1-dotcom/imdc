<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$u = App\Models\User::where('email','admin@example.com')->firstOrFail();
var_export([$u->can('orders.read'), $u->can('orders.write')]); echo PHP_EOL;
print_r($u->getPermissionNames()->toArray());
