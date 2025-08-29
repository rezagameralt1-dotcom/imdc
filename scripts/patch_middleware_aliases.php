<?php
$path = __DIR__ . '/../bootstrap/app.php';
$code = file_get_contents($path);
if ($code === false) { fwrite(STDERR, "Cannot read bootstrap/app.php\n"); exit(1); }

if (strpos($code, '\Spatie\Permission\Middlewares\PermissionMiddleware') !== false) {
  echo "Already patched.\n"; exit(0);
}

$needsUse = (strpos($code, 'use Illuminate\Foundation\Configuration\Middleware;') === false);
if ($needsUse) {
  // درج use برای Middleware اگر نبود
  $code = preg_replace(
    '/^use\s+Illuminate\\\\Foundation\\\\Configuration\\\\Exceptions;$/m',
    "use Illuminate\\Foundation\\Configuration\\Exceptions;\nuse Illuminate\\Foundation\\Configuration\\Middleware;",
    $code,
    1,
    $count
  );
  if ($count === 0) {
    // اگر الگوی بالا پیدا نشد، بعد از <?php اضافه کن
    $code = preg_replace('/^<\?php\s*/', "<?php\nuse Illuminate\\Foundation\\Configuration\\Middleware;\n", $code, 1);
  }
}

$aliasBlock = <<<ALIAS
        \$middleware->alias([
            'permission'          => \\Spatie\\Permission\\Middlewares\\PermissionMiddleware::class,
            'role'                 => \\Spatie\\Permission\\Middlewares\\RoleMiddleware::class,
            'role_or_permission'   => \\Spatie\\Permission\\Middlewares\\RoleOrPermissionMiddleware::class,
        ]);

ALIAS;

$withMiddlewareSig = '->withMiddleware(function (Middleware $middleware) {';
if (strpos($code, $withMiddlewareSig) !== false) {
  // داخل بلوک موجود، بلافاصله بعد از سطر امضای تابع، alias را درج کن
  $code = preg_replace(
    '/->withMiddleware\(function\s*\(Middleware\s*\$middleware\)\s*\)\s*\{\s*/',
    $withMiddlewareSig . "\n" . $aliasBlock,
    $code,
    1
  );
} else {
  // هیچ withMiddleware ای نیست: یک بلوک کامل قبل از withExceptions یا قبل از ->create() اضافه کن
  $insert = "\n    ->withMiddleware(function (Middleware \$middleware) {\n$aliasBlock    })\n";
  if (strpos($code, '->withExceptions(') !== false) {
    $code = preg_replace('/(\-\>withExceptions\()/', $insert.'$1', $code, 1);
  } else {
    $code = preg_replace('/(\)\s*\-\>create\(\)\s*;\s*)$/', $insert."$1", $code, 1);
  }
}

if (!file_put_contents($path, $code)) { fwrite(STDERR, "Cannot write bootstrap/app.php\n"); exit(1); }
echo "Patched bootstrap/app.php\n";
