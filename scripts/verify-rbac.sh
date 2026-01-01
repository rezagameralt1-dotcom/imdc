#!/bin/bash
# RBAC Verification Script
# Verifies that RBAC is normalized to Spatie + Sanctum guard in Core DB only

set -euo pipefail

cd "$(dirname "$0")/.." || exit 1

echo "=== RBAC Verification (Core DB) ==="
echo ""

# 0. Checking Spatie middleware classes autoload...
echo "0. Checking Spatie middleware classes autoload..."
if php -r "require 'vendor/autoload.php'; exit(class_exists('Spatie\\\\Permission\\\\Middleware\\\\RoleMiddleware')?0:1);" 2>/dev/null; then
    echo "✓ Spatie RoleMiddleware autoloadable"
else
    echo "✗ Spatie RoleMiddleware missing"
    exit 1
fi

if php -r "require 'vendor/autoload.php'; exit(class_exists('Spatie\\\\Permission\\\\Middleware\\\\PermissionMiddleware')?0:1);" 2>/dev/null; then
    echo "✓ Spatie PermissionMiddleware autoloadable"
else
    echo "✗ Spatie PermissionMiddleware missing"
    exit 1
fi

if php -r "require 'vendor/autoload.php'; exit(class_exists('Spatie\\\\Permission\\\\Middleware\\\\RoleOrPermissionMiddleware')?0:1);" 2>/dev/null; then
    echo "✓ Spatie RoleOrPermissionMiddleware autoloadable"
else
    echo "✗ Spatie RoleOrPermissionMiddleware missing"
    exit 1
fi

# 1. Check legacy RBAC tables are absent
echo "1. Checking legacy RBAC tables are absent..."
LEGACY_COUNT=$(docker compose exec -T core-db psql -U postgres -d imdc_core -tAc "
    SELECT COUNT(*) 
    FROM information_schema.tables 
    WHERE table_schema = 'public' 
    AND table_name IN ('permission_role', 'permission_user', 'role_user');
" || echo "0")

if [ "$LEGACY_COUNT" = "0" ]; then
    echo "✓ Legacy RBAC tables absent (expected)"
else
    echo "✗ Found $LEGACY_COUNT legacy RBAC table(s) (unexpected)"
    exit 1
fi

# 2. Check Spatie RBAC tables exist
echo ""
echo "2. Checking Spatie RBAC tables exist..."
SPATIE_COUNT=$(docker compose exec -T core-db psql -U postgres -d imdc_core -tAc "
    SELECT COUNT(*) 
    FROM information_schema.tables 
    WHERE table_schema = 'public' 
    AND table_name IN ('roles', 'permissions', 'model_has_roles', 'model_has_permissions', 'role_has_permissions');
" || echo "0")

if [ "$SPATIE_COUNT" = "5" ]; then
    echo "✓ All 5 Spatie RBAC tables exist"
else
    echo "✗ Missing Spatie RBAC tables (expected 5, found $SPATIE_COUNT)"
    echo "  Run: php artisan migrate --force --database=core"
    exit 1
fi

# 3. Check all roles use 'sanctum' guard
echo ""
echo "3. Checking roles guard_name normalization..."
NON_SANCTUM_ROLES=$(docker compose exec -T core-db psql -U postgres -d imdc_core -tAc "
    SELECT COUNT(*) 
    FROM roles 
    WHERE guard_name != 'sanctum';
" || echo "0")

if [ "$NON_SANCTUM_ROLES" = "0" ]; then
    echo "✓ All roles use 'sanctum' guard"
else
    echo "✗ Found $NON_SANCTUM_ROLES role(s) with non-sanctum guard"
    echo "  Run: php artisan migrate --force --database=core"
    exit 1
fi

# 4. Check all permissions use 'sanctum' guard
echo ""
echo "4. Checking permissions guard_name normalization..."
NON_SANCTUM_PERMS=$(docker compose exec -T core-db psql -U postgres -d imdc_core -tAc "
    SELECT COUNT(*) 
    FROM permissions 
    WHERE guard_name != 'sanctum';
" || echo "0")

if [ "$NON_SANCTUM_PERMS" = "0" ]; then
    echo "✓ All permissions use 'sanctum' guard"
else
    echo "✗ Found $NON_SANCTUM_PERMS permission(s) with non-sanctum guard"
    echo "  Run: php artisan migrate --force --database=core"
    exit 1
fi

# 5. Check for duplicate roles (PostgreSQL-compatible query)
echo ""
echo "5. Checking for duplicate role names..."
DUP_ROLES=$(docker compose exec -T core-db psql -U postgres -d imdc_core -tAc "
    SELECT COUNT(*) 
    FROM (
        SELECT name 
        FROM roles 
        GROUP BY name 
        HAVING COUNT(*) > 1
    ) AS duplicates;
" || echo "0")

if [ "$DUP_ROLES" = "0" ]; then
    echo "✓ No duplicate role names"
else
    echo "✗ Found $DUP_ROLES duplicate role name(s)"
    echo "  Run: php artisan migrate --force --database=core"
    exit 1
fi

# 6. Check for duplicate permissions (PostgreSQL-compatible query)
echo ""
echo "6. Checking for duplicate permission names..."
DUP_PERMS=$(docker compose exec -T core-db psql -U postgres -d imdc_core -tAc "
    SELECT COUNT(*) 
    FROM (
        SELECT name 
        FROM permissions 
        GROUP BY name 
        HAVING COUNT(*) > 1
    ) AS duplicates;
" || echo "0")

if [ "$DUP_PERMS" = "0" ]; then
    echo "✓ No duplicate permission names"
else
    echo "✗ Found $DUP_PERMS duplicate permission name(s)"
    echo "  Run: php artisan migrate --force --database=core"
    exit 1
fi

# 7. Verify Spatie Role count matches Core DB
echo ""
echo "7. Verifying Spatie Role count matches Core DB..."
php artisan tinker --execute="
use Illuminate\Support\Facades\DB;
\$spatieCount = \Spatie\Permission\Models\Role::where('guard_name', 'sanctum')->count();
\$dbCount = DB::connection('core')->table('roles')->where('guard_name', 'sanctum')->count();
echo 'Spatie Role count (sanctum): ' . \$spatieCount . PHP_EOL;
echo 'DB roles count (sanctum): ' . \$dbCount . PHP_EOL;
if (\$spatieCount === \$dbCount) {
    echo '✓ Counts match' . PHP_EOL;
} else {
    echo '✗ Counts do not match' . PHP_EOL;
    exit(1);
}
" || exit 1

# 8. Verify config values
echo ""
echo "8. Verifying config values..."
php artisan tinker --execute="
\$conn = config('permission.connection');
\$guardName = config('permission.defaults.guard_name');
\$guard = config('permission.defaults.guard');
echo 'Config connection: ' . \$conn . PHP_EOL;
echo 'Config guard_name: ' . \$guardName . PHP_EOL;
echo 'Config guard: ' . \$guard . PHP_EOL;
if (\$conn === 'core' && \$guardName === 'sanctum' && \$guard === 'sanctum') {
    echo '✓ Config values correct' . PHP_EOL;
} else {
    echo '✗ Config values incorrect' . PHP_EOL;
    exit(1);
}
" || exit 1

# 9. Verify User model guard and HasRoles trait
echo ""
echo "9. Verifying User model guard and HasRoles trait..."
php artisan tinker --execute="
use App\Models\User;
use Spatie\Permission\Traits\HasRoles;

// Check HasRoles trait using class_uses_recursive (includes parent traits)
\$userTraits = class_uses_recursive(User::class);
\$hasTrait = in_array(HasRoles::class, \$userTraits, true);

// Get guard_name from User model via reflection
\$userInstance = new User();
\$guardNameProperty = null;
try {
    \$reflection = new ReflectionClass(User::class);
    \$property = \$reflection->getProperty('guard_name');
    \$property->setAccessible(true);
    \$guardNameProperty = \$property->getValue(\$userInstance);
} catch (ReflectionException \$e) {
    // Fallback: try to get via magic property
    \$guardNameProperty = \$userInstance->guard_name ?? null;
}

echo 'User guard_name: ' . (\$guardNameProperty ?? 'not set') . PHP_EOL;
echo 'User has HasRoles trait: ' . (\$hasTrait ? 'YES' : 'NO') . PHP_EOL;

if (\$guardNameProperty === 'sanctum' && \$hasTrait) {
    echo '✓ User model configured correctly' . PHP_EOL;
} else {
    echo '✗ User model misconfigured' . PHP_EOL;
    if (\$guardNameProperty !== 'sanctum') {
        echo '  Expected guard_name: sanctum, got: ' . (\$guardNameProperty ?? 'null') . PHP_EOL;
    }
    if (!\$hasTrait) {
        echo '  Missing HasRoles trait' . PHP_EOL;
    }
    exit(1);
}
" || exit 1

# 10. Verify Spatie middleware classes exist
echo ""
echo "10. Verifying Spatie middleware classes exist..."
php artisan imdc:verify-permission-middleware || exit 1

echo ""
echo "=== Verification Complete ==="
echo "✓ All RBAC checks passed"
