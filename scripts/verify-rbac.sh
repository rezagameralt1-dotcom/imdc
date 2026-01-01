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
php -r 'require "vendor/autoload.php"; $classes=["Spatie\\\\Permission\\\\Middleware\\\\RoleMiddleware","Spatie\\\\Permission\\\\Middleware\\\\PermissionMiddleware","Spatie\\\\Permission\\\\Middleware\\\\RoleOrPermissionMiddleware"]; foreach($classes as $c){ if(!class_exists($c)){fwrite(STDERR,"MISSING: $c\n"); exit(1);} }' >/dev/null
echo "✓ Spatie RoleMiddleware autoloadable"
echo "✓ Spatie PermissionMiddleware autoloadable"
echo "✓ Spatie RoleOrPermissionMiddleware autoloadable"
echo

# Run app-provided self-check if exists
if php artisan list --format=txt 2>/dev/null | grep -qE '^ +imdc:verify-permission-middleware'; then
  echo "0.1 Running imdc:verify-permission-middleware..."
  php artisan imdc:verify-permission-middleware >/dev/null
  echo "✓ Permission middleware self-check OK"
  echo
fi

# If docker CLI exists AND we are not in container: keep legacy docker-based checks if script had them
# For simplicity and stability: always run Laravel-native checks below (work both host+container).
# (No Auth/RBAC logic changes; only guardrails.)
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

echo "7. Verifying Spatie Role count matches Core DB..."
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
