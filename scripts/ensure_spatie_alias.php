<?php
$path = __DIR__.'/../bootstrap/app.php';
$code = file_get_contents($path);
if ($code === false) { fwrite(STDERR,"READ_FAIL\n"); exit(1); }

/* 1) تضمین use */
if (strpos($code,"use Illuminate\\Foundation\\Configuration\\Middleware;") === false) {
    $code = preg_replace('/^<\?php\s*/', "<?php\nuse Illuminate\\Foundation\\Configuration\\Middleware;\n", $code, 1);
}

/* 2) بلاک alias ها (تنها یکبار تزریق شود) */
$needle = 'Spatie\\Permission\\Middlewares\\PermissionMiddleware';
$aliasBlock = <<<TXT

    ->withMiddleware(function (Middleware \$middleware) {
        \$middleware->alias([
            'permission'          => \\Spatie\\Permission\\Middlewares\\PermissionMiddleware::class,
            'role'                => \\Spatie\\Permission\\Middlewares\\RoleMiddleware::class,
            'role_or_permission'  => \\Spatie\\Permission\\Middlewares\\RoleOrPermissionMiddleware::class,
        ]);
    })
TXT;

if (strpos($code, $needle) === false) {
    // درج درست قبل از ->create();
    if (preg_match('/->create\(\)\s*;/', $code)) {
        $code = preg_replace('/->create\(\)\s*;/', $aliasBlock."\n    ->create();", $code, 1);
    } else {
        // اگر ساختار فکتوری متفاوت بود، انتهای فایل اضافه کن
        $code .= "\n".$aliasBlock.";\n";
    }
}

echo $code;
