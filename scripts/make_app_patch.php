<?php
$path = __DIR__ . '/../bootstrap/app.php';
$code = file_get_contents($path);
if ($code === false) { fwrite(STDERR,"READ_FAIL\n"); exit(1); }

/* اطمینان از use Middleware در بالای فایل */
if (strpos($code,"use Illuminate\\Foundation\\Configuration\\Middleware;") === false) {
    $code = preg_replace('/^<\?php\s*/', "<?php\nuse Illuminate\\Foundation\\Configuration\\Middleware;\n", $code, 1);
}

/* بلوک alias های Spatie */
$insert = <<<TXT

    ->withMiddleware(function (Middleware \$middleware) {
        \$middleware->alias([
            'permission' => \\Spatie\\Permission\\Middlewares\\PermissionMiddleware::class,
            'role' => \\Spatie\\Permission\\Middlewares\\RoleMiddleware::class,
            'role_or_permission' => \\Spatie\\Permission\\Middlewares\\RoleOrPermissionMiddleware::class,
        ]);
    })
TXT;

/* درج قبل از withExceptions یا قبل از create() */
if (strpos($code, '->withExceptions(') !== false) {
    $code = str_replace('->withExceptions(', $insert."\n    ->withExceptions(", $code);
} elseif (strpos($code, '->create()') !== false) {
    $code = str_replace('->create()', $insert."\n    ->create()", $code);
} else {
    $code = preg_replace('/;\s*$/', $insert.";\n", $code, 1);
}

echo $code;
