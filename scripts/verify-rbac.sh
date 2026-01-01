#!/usr/bin/env bash
set -euo pipefail

echo "=== RBAC Verification (Core DB) ==="
echo

# Detect container context (best-effort)
IN_CONTAINER="0"
if [[ -f "/.dockerenv" ]]; then IN_CONTAINER="1"; fi

HAS_DOCKER="0"
if command -v docker >/dev/null 2>&1; then HAS_DOCKER="1"; fi

echo "Context: $([[ "$IN_CONTAINER" == "1" ]] && echo "container" || echo "host")"
echo "Docker CLI: $([[ "$HAS_DOCKER" == "1" ]] && echo "present" || echo "missing")"
echo

echo "0. Checking Spatie middleware classes autoload..."

php -r '
require "vendor/autoload.php";

function resolveFqcnFromFile(string $file): ?string {
  if (!is_file($file)) return null;
  $src = file_get_contents($file);
  if ($src === false) return null;

  // namespace Foo\Bar;
  if (!preg_match("/^namespace\s+([^;]+);/m", $src, $m)) return null;
  $ns = trim($m[1]);

  // class ClassName
  if (!preg_match("/\bclass\s+([A-Za-z_][A-Za-z0-9_]*)\b/", $src, $c)) return null;
  $cls = trim($c[1]);

  return $ns . "\\" . $cls;
}

function findFirst(array $paths): ?string {
  foreach ($paths as $p) if (is_file($p)) return $p;
  return null;
}

$base = "vendor/spatie/laravel-permission/src";

// try common locations first, then fallback to glob search
$targets = [
  "role" => [
    $base."/Middleware/RoleMiddleware.php",
    $base."/Middlewares/RoleMiddleware.php",
  ],
  "permission" => [
    $base."/Middleware/PermissionMiddleware.php",
    $base."/Middlewares/PermissionMiddleware.php",
  ],
  "role_or_permission" => [
    $base."/Middleware/RoleOrPermissionMiddleware.php",
    $base."/Middlewares/RoleOrPermissionMiddleware.php",
  ],
];

$resolved = [];
foreach ($targets as $key => $candidates) {
  $file = findFirst($candidates);

  // fallback: locate by filename anywhere under src
  if (!$file) {
    $name = basename($candidates[0]); // e.g. RoleMiddleware.php
    $hits = glob($base."/**/".$name, GLOB_BRACE);
    if ($hits && count($hits) > 0) $file = $hits[0];
  }

  if (!$file) {
    fwrite(STDERR, "MISSING FILE for $key (expected under $base)\n");
    exit(1);
  }

  $fqcn = resolveFqcnFromFile($file);
  if (!$fqcn) {
    fwrite(STDERR, "FAILED to resolve namespace/class from file: $file\n");
    exit(1);
  }

  if (!class_exists($fqcn)) {
    fwrite(STDERR, "MISSING CLASS: $fqcn (from $file)\n");
    exit(1);
  }

  $resolved[$key] = $fqcn;
}

echo "RESOLVED role=".$resolved["role"].PHP_EOL;
echo "RESOLVED permission=".$resolved["permission"].PHP_EOL;
echo "RESOLVED role_or_permission=".$resolved["role_or_permission"].PHP_EOL;
' | sed -n '1,30p'

echo "✓ Spatie RoleMiddleware autoloadable (resolved)"
echo "✓ Spatie PermissionMiddleware autoloadable (resolved)"
echo "✓ Spatie RoleOrPermissionMiddleware autoloadable (resolved)"
echo

# Run app-provided self-check if exists
if php artisan list --format=txt 2>/dev/null | grep -qE '^ +imdc:verify-permission-middleware'; then
  echo "0.1 Running imdc:verify-permission-middleware..."
  php artisan imdc:verify-permission-middleware >/dev/null
  echo "✓ Permission middleware self-check OK"
  echo
fi

echo "1. Checking legacy RBAC tables are absent..."
php artisan tinker --execute='
$pdo = DB::connection(config("database.default"))->getPdo();
$tables = ["roles_legacy","permissions_legacy","role_user","permission_role","user_roles","user_permissions"];
$found = [];
foreach ($tables as $t) {
  try {
    $q = $pdo->prepare("select to_regclass(?)");
    $q->execute([$t]);
    $r = $q->fetchColumn();
    if ($r) $found[] = $t;
  } catch (\Throwable $e) {}
}
if (count($found) > 0) { throw new \RuntimeException("FOUND legacy tables: ".implode(",",$found)); }
' >/dev/null
echo "✓ Legacy RBAC tables absent (expected)"
echo

echo "2. Checking Spatie RBAC tables exist..."
php artisan tinker --execute='
$pdo = DB::connection(config("database.default"))->getPdo();
$tables = ["roles","permissions","model_has_roles","model_has_permissions","role_has_permissions"];
$missing = [];
foreach ($tables as $t) {
  $q = $pdo->prepare("select to_regclass(?)");
  $q->execute([$t]);
  $r = $q->fetchColumn();
  if (!$r) $missing[] = $t;
}
if (count($missing) > 0) { throw new \RuntimeException("MISSING tables: ".implode(",",$missing)); }
' >/dev/null
echo "✓ All 5 Spatie RBAC tables exist"
echo

echo "3. Checking roles guard_name normalization..."
php artisan tinker --execute='
$bad = DB::table("roles")->where("guard_name","<>","sanctum")->count();
if ($bad > 0) { throw new \RuntimeException("roles bad_guard_count=".$bad); }
' >/dev/null
echo "✓ All roles use 'sanctum' guard"
echo

echo "4. Checking permissions guard_name normalization..."
php artisan tinker --execute='
$bad = DB::table("permissions")->where("guard_name","<>","sanctum")->count();
if ($bad > 0) { throw new \RuntimeException("permissions bad_guard_count=".$bad); }
' >/dev/null
echo "✓ All permissions use 'sanctum' guard"
echo

echo "5. Checking for duplicate role names..."
php artisan tinker --execute='
$dup = DB::table("roles")->select("name", DB::raw("count(*) as c"))->groupBy("name")->havingRaw("count(*) > 1")->count();
if ($dup > 0) { throw new \RuntimeException("duplicate_roles=".$dup); }
' >/dev/null
echo "✓ No duplicate role names"
echo

echo "6. Checking for duplicate permission names..."
php artisan tinker --execute='
$dup = DB::table("permissions")->select("name", DB::raw("count(*) as c"))->groupBy("name")->havingRaw("count(*) > 1")->count();
if ($dup > 0) { throw new \RuntimeException("duplicate_permissions=".$dup); }
' >/dev/null
echo "✓ No duplicate permission names"
echo

echo "7. Verifying Spatie Role count looks sane..."
php artisan tinker --execute='
$count = DB::table("roles")->where("guard_name","sanctum")->count();
if ($count <= 0) { throw new \RuntimeException("role_count=".$count); }
' >/dev/null
echo "✓ Role count looks sane"
echo

echo "8. Verifying config values..."
php artisan tinker --execute='
$conn = config("database.default");
$guardName = config("permission.defaults.guard_name");
$authGuard = config("auth.defaults.guard");
echo "Config connection: ".$conn.PHP_EOL;
echo "Config guard_name: ".$guardName.PHP_EOL;
echo "Config guard: ".$authGuard.PHP_EOL;
if ($conn === "" || $guardName !== "sanctum" || $authGuard === "") {
  throw new \RuntimeException("Config values incorrect");
}
'
echo "✓ Config values look correct"
echo

echo "=== RBAC Guardrail PASSED ==="
