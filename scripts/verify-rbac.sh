#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR" || exit 1

# Helper: Detect if running inside container
is_container() {
  [[ "${1:-}" == "--in-container" ]] && return 0
  [[ -f "/.dockerenv" ]] && return 0
  [[ -f "/proc/self/cgroup" ]] && grep -qE "docker|kubepods" /proc/self/cgroup 2>/dev/null && return 0
  return 1
}

# Helper: Check if docker compose is available
has_docker_compose() {
  command -v docker >/dev/null 2>&1 && docker compose version >/dev/null 2>&1
}

# Determine execution context (deterministic: single decision at startup)
if is_container "${1:-}"; then
  EXEC_CTX="container"
elif has_docker_compose; then
  # Host mode: delegate everything to container and exit
  echo "=== RBAC Verification (Core DB) ==="
  echo
  echo "Context: host-delegating"
  echo
  # Delegate to container: always use /var/www/html (container path)
  docker compose exec -T app sh -lc 'cd /var/www/html && ./scripts/verify-rbac.sh --in-container'
  exit $?
else
  EXEC_CTX="host-direct"
fi

# From here on, we're running in container context
echo "=== RBAC Verification (Core DB) ==="
echo
echo "Context: ${EXEC_CTX}"
echo

# Helper: Normalize class string (collapse repeated backslashes to single backslash)
normalize_class() {
  local str="$1"
  echo "$str" | sed -E 's/\\{2,}/\\/g'
}

# Helper: Check if PHP class exists (using inline php -r with env var, no temp files)
php_check_class() {
  local classname="$1"
  local normalized
  normalized="$(normalize_class "$classname")"
  
  # Use environment variable to pass class name (avoids escaping backslashes)
  local php_code
  php_code="require getcwd().\"/vendor/autoload.php\"; \$c=getenv(\"IMDC_CLASS\"); exit(class_exists(\$c)?0:1);"
  
  (cd "$ROOT_DIR" && IMDC_CLASS="$normalized" php -r "$php_code" 2>/dev/null)
  return $?
}

# Helper: Run PHP code inline (no temp files, uses env vars for inputs)
run_php_inline() {
  local php_code="$1"
  local env_vars="${2:-}"
  
  # Execute PHP code directly (PHP variables are already escaped with \)
  if [[ -n "$env_vars" ]]; then
    (cd "$ROOT_DIR" && eval "$env_vars" && php -r "$php_code" 2>&1)
  else
    (cd "$ROOT_DIR" && php -r "$php_code" 2>&1)
  fi
}

# Helper: Run PostgreSQL query using PHP PDO (no psql dependency)
php_pg_query() {
  local sql="$1"
  local php_output
  local php_exit
  
  # Temporarily disable exit on error for PHP call
  set +e
  php_output="$(cd "$ROOT_DIR" && IMDC_SQL="$sql" php -r '
    $h=getenv("DB_CORE_HOST") ?: (getenv("DB_HOST") ?: "db");
    $p=getenv("DB_PORT") ?: (getenv("CORE_DB_PORT") ?: "5432");
    $d=getenv("DB_DATABASE") ?: (getenv("CORE_DB_DATABASE") ?: "imdc_core");
    $u=getenv("DB_USERNAME") ?: (getenv("CORE_DB_USERNAME") ?: "postgres");
    $pw=getenv("DB_PASSWORD") ?: (getenv("CORE_DB_PASSWORD") ?: (getenv("POSTGRES_PASSWORD") ?: ""));
    $dsn="pgsql:host=$h;port=$p;dbname=$d";
    try {
      $pdo=new PDO($dsn,$u,$pw,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
      $sql=getenv("IMDC_SQL");
      if (!$sql) { fwrite(STDERR, "ERROR: IMDC_SQL not set".PHP_EOL); exit(1); }
      $stmt=$pdo->query($sql);
      $rows=$stmt->fetchAll(PDO::FETCH_COLUMN,0);
      foreach($rows as $r){ echo $r, PHP_EOL; }
      exit(0);
    } catch (PDOException $e) {
      fwrite(STDERR, "PDO_ERROR: ".$e->getMessage().PHP_EOL);
      exit(1);
    } catch (Exception $e) {
      fwrite(STDERR, "ERROR: ".$e->getMessage().PHP_EOL);
      exit(1);
    }
  ' 2>&1)"
  php_exit=$?
  set -e
  
  # Return output and exit code via global vars
  PHP_PG_OUTPUT="$php_output"
  PHP_PG_EXIT=$php_exit
}

# Helper: Run PostgreSQL query returning key-value pairs (for distinct guard_name queries)
php_pg_query_kv() {
  local sql="$1"
  local php_output
  local php_exit
  
  # Temporarily disable exit on error for PHP call
  set +e
  php_output="$(cd "$ROOT_DIR" && IMDC_SQL="$sql" php -r '
    $h=getenv("DB_CORE_HOST") ?: (getenv("DB_HOST") ?: "db");
    $p=getenv("DB_PORT") ?: (getenv("CORE_DB_PORT") ?: "5432");
    $d=getenv("DB_DATABASE") ?: (getenv("CORE_DB_DATABASE") ?: "imdc_core");
    $u=getenv("DB_USERNAME") ?: (getenv("CORE_DB_USERNAME") ?: "postgres");
    $pw=getenv("DB_PASSWORD") ?: (getenv("CORE_DB_PASSWORD") ?: (getenv("POSTGRES_PASSWORD") ?: ""));
    $dsn="pgsql:host=$h;port=$p;dbname=$d";
    try {
      $pdo=new PDO($dsn,$u,$pw,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
      $sql=getenv("IMDC_SQL");
      if (!$sql) { fwrite(STDERR, "ERROR: IMDC_SQL not set".PHP_EOL); exit(1); }
      $stmt=$pdo->query($sql);
      $rows=$stmt->fetchAll(PDO::FETCH_ASSOC);
      foreach($rows as $r){ echo $r["g"]."|".$r["c"], PHP_EOL; }
      exit(0);
    } catch (PDOException $e) {
      fwrite(STDERR, "PDO_ERROR: ".$e->getMessage().PHP_EOL);
      exit(1);
    } catch (Exception $e) {
      fwrite(STDERR, "ERROR: ".$e->getMessage().PHP_EOL);
      exit(1);
    }
  ' 2>&1)"
  php_exit=$?
  set -e
  
  # Return output and exit code via global vars
  PHP_PG_OUTPUT="$php_output"
  PHP_PG_EXIT=$php_exit
}

# Helper: Run PostgreSQL query returning all columns pipe-separated (for duplicate checks)
php_pg_query_all() {
  local sql="$1"
  local php_output
  local php_exit
  
  # Temporarily disable exit on error for PHP call
  set +e
  php_output="$(cd "$ROOT_DIR" && IMDC_SQL="$sql" php -r '
    $h=getenv("DB_CORE_HOST") ?: (getenv("DB_HOST") ?: "db");
    $p=getenv("DB_PORT") ?: (getenv("CORE_DB_PORT") ?: "5432");
    $d=getenv("DB_DATABASE") ?: (getenv("CORE_DB_DATABASE") ?: "imdc_core");
    $u=getenv("DB_USERNAME") ?: (getenv("CORE_DB_USERNAME") ?: "postgres");
    $pw=getenv("DB_PASSWORD") ?: (getenv("CORE_DB_PASSWORD") ?: (getenv("POSTGRES_PASSWORD") ?: ""));
    $dsn="pgsql:host=$h;port=$p;dbname=$d";
    try {
      $pdo=new PDO($dsn,$u,$pw,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
      $sql=getenv("IMDC_SQL");
      if (!$sql) { fwrite(STDERR, "ERROR: IMDC_SQL not set".PHP_EOL); exit(1); }
      $stmt=$pdo->query($sql);
      $rows=$stmt->fetchAll(PDO::FETCH_ASSOC);
      foreach($rows as $r){
        $parts=array();
        foreach($r as $k=>$v){ $parts[]=$v; }
        echo implode("|",$parts), PHP_EOL;
      }
      exit(0);
    } catch (PDOException $e) {
      fwrite(STDERR, "PDO_ERROR: ".$e->getMessage().PHP_EOL);
      exit(1);
    } catch (Exception $e) {
      fwrite(STDERR, "ERROR: ".$e->getMessage().PHP_EOL);
      exit(1);
    }
  ' 2>&1)"
  php_exit=$?
  set -e
  
  # Return output and exit code via global vars
  PHP_PG_OUTPUT="$php_output"
  PHP_PG_EXIT=$php_exit
}

echo "0. Checking Spatie middleware classes autoload..."
# Define exact class names (single backslash in bash string = literal backslash)
declare -a MIDDLEWARE_CLASSES=(
  "Spatie\\Permission\\Middleware\\RoleMiddleware"
  "Spatie\\Permission\\Middleware\\PermissionMiddleware"
  "Spatie\\Permission\\Middleware\\RoleOrPermissionMiddleware"
)

declare -a MIDDLEWARE_LABELS=(
  "role"
  "permission"
  "role_or_permission"
)

declare -a MIDDLEWARE_FILES=(
  "vendor/spatie/laravel-permission/src/Middleware/RoleMiddleware.php"
  "vendor/spatie/laravel-permission/src/Middleware/PermissionMiddleware.php"
  "vendor/spatie/laravel-permission/src/Middleware/RoleOrPermissionMiddleware.php"
)

ALL_OK=true
for i in "${!MIDDLEWARE_CLASSES[@]}"; do
  class="${MIDDLEWARE_CLASSES[$i]}"
  label="${MIDDLEWARE_LABELS[$i]}"
  file="${MIDDLEWARE_FILES[$i]}"
  
  # Normalize class string (collapse any repeated backslashes)
  normalized_class="$(normalize_class "$class")"
  
  # Check if vendor file exists
  file_exists=false
  [[ -f "$file" ]] && file_exists=true
  
  # Check class exists via PHP
  if php_check_class "$normalized_class"; then
    echo "✓ Spatie ${label} middleware autoloadable: ${normalized_class}"
  else
    echo "✗ MISSING CLASS: ${normalized_class}"
    if [[ "$file_exists" == "true" ]]; then
      echo "  (File exists: ${file}, but class not autoloadable)"
    else
      echo "  (File missing: ${file})"
    fi
    ALL_OK=false
  fi
done

if [[ "$ALL_OK" != "true" ]]; then
  exit 1
fi
echo

echo "1. Checking legacy RBAC tables are absent..."
# Use PHP PDO directly (no psql dependency, no Laravel bootstrap)
LEGACY_TABLES=("roles_legacy" "permissions_legacy" "role_user" "permission_role" "user_roles" "user_permissions")
LEGACY_TABLES_QUOTED=""
for t in "${LEGACY_TABLES[@]}"; do
  if [[ -n "$LEGACY_TABLES_QUOTED" ]]; then
    LEGACY_TABLES_QUOTED="${LEGACY_TABLES_QUOTED},'${t}'"
  else
    LEGACY_TABLES_QUOTED="'${t}'"
  fi
done

LEGACY_SQL="SELECT table_name FROM information_schema.tables WHERE table_schema='public' AND table_name IN (${LEGACY_TABLES_QUOTED}) ORDER BY table_name;"

# Run PHP PDO query (with error handling)
set +e
php_pg_query "$LEGACY_SQL"
LEGACY_TABLES_EXIT=$PHP_PG_EXIT
LEGACY_TABLES_OUTPUT="$PHP_PG_OUTPUT"
set -e

# Normalize exit code (never 255) - map any non-0/1 to 1
if [[ $LEGACY_TABLES_EXIT -ne 0 ]] && [[ $LEGACY_TABLES_EXIT -ne 1 ]]; then
  LEGACY_TABLES_EXIT=1
fi

# Check results
if [[ $LEGACY_TABLES_EXIT -ne 0 ]]; then
  # PHP PDO connection/query error
  echo "✗ Legacy RBAC check failed"
  echo "  Error: $LEGACY_TABLES_OUTPUT"
  exit 1
fi

# PHP PDO succeeded (exit 0) - check if any tables were found
LEGACY_TABLES_FOUND="$(echo "$LEGACY_TABLES_OUTPUT" | grep -v '^$' | grep -v 'PDO_ERROR' | grep -v 'ERROR' || true)"
if [[ -z "$LEGACY_TABLES_FOUND" ]]; then
  # No tables found (empty output) = PASS
  echo "✓ Legacy RBAC tables absent (expected)"
else
  # Tables found = FAIL
  echo "✗ Legacy RBAC tables present (unexpected)"
  echo "  Found tables:"
  echo "$LEGACY_TABLES_FOUND" | while read -r table; do
    [[ -n "$table" ]] && echo "    - $table"
  done
  exit 1
fi
echo

echo "2. Checking Spatie RBAC tables exist..."
# Use PHP PDO directly (no Laravel bootstrap, no artisan)
SPATIE_TABLES=("roles" "permissions" "model_has_roles" "model_has_permissions" "role_has_permissions")
SPATIE_TABLES_QUOTED=""
for t in "${SPATIE_TABLES[@]}"; do
  if [[ -n "$SPATIE_TABLES_QUOTED" ]]; then
    SPATIE_TABLES_QUOTED="${SPATIE_TABLES_QUOTED},'${t}'"
  else
    SPATIE_TABLES_QUOTED="'${t}'"
  fi
done

SPATIE_SQL="SELECT table_name FROM information_schema.tables WHERE table_schema='public' AND table_name IN (${SPATIE_TABLES_QUOTED}) ORDER BY table_name;"

# Run PHP PDO query (with error handling)
set +e
php_pg_query "$SPATIE_SQL"
SPATIE_TABLES_EXIT=$PHP_PG_EXIT
SPATIE_TABLES_OUTPUT="$PHP_PG_OUTPUT"
set -e

# Normalize exit code (never 255) - map any non-0/1 to 1
if [[ $SPATIE_TABLES_EXIT -ne 0 ]] && [[ $SPATIE_TABLES_EXIT -ne 1 ]]; then
  SPATIE_TABLES_EXIT=1
fi

# Check results
if [[ $SPATIE_TABLES_EXIT -ne 0 ]]; then
  # PHP PDO connection/query error
  echo "✗ Spatie RBAC tables check failed"
  echo "  Error: $SPATIE_TABLES_OUTPUT"
  exit 1
fi

# PHP PDO succeeded (exit 0) - count found tables
SPATIE_TABLES_FOUND="$(echo "$SPATIE_TABLES_OUTPUT" | grep -v '^$' | grep -v 'PDO_ERROR' | grep -v 'ERROR' || true)"
SPATIE_TABLES_COUNT=0
if [[ -n "$SPATIE_TABLES_FOUND" ]]; then
  SPATIE_TABLES_COUNT="$(echo "$SPATIE_TABLES_FOUND" | wc -l | tr -d ' ')"
fi

# Expected 5 tables
EXPECTED_COUNT=5
if [[ $SPATIE_TABLES_COUNT -eq $EXPECTED_COUNT ]]; then
  # All 5 tables found = PASS
  echo "✓ Spatie RBAC tables present (${SPATIE_TABLES_COUNT}/${EXPECTED_COUNT})"
else
  # Missing tables = FAIL
  echo "✗ Spatie RBAC tables missing"
  # Find missing tables by comparing expected vs found
  MISSING_TABLES=()
  for t in "${SPATIE_TABLES[@]}"; do
    if ! echo "$SPATIE_TABLES_FOUND" | grep -q "^${t}$"; then
      MISSING_TABLES+=("$t")
    fi
  done
  if [[ ${#MISSING_TABLES[@]} -gt 0 ]]; then
    echo "  Missing: $(IFS=','; echo "${MISSING_TABLES[*]}")"
  else
    echo "  Found ${SPATIE_TABLES_COUNT}/${EXPECTED_COUNT} tables"
  fi
  exit 1
fi
echo

echo "3. Checking roles guard_name normalization..."
# Use PHP PDO directly (no Laravel bootstrap, no artisan)
# Determine expected guard name
EXPECT_GUARD="${PERMISSION_GUARD_NAME:-${AUTH_GUARD:-sanctum}}"

# Query for distinct guard_name values with counts
ROLES_DISTINCT_SQL="SELECT COALESCE(guard_name,'') AS g, COUNT(*) AS c FROM roles GROUP BY COALESCE(guard_name,'') ORDER BY g;"

set +e
php_pg_query_kv "$ROLES_DISTINCT_SQL"
ROLES_DISTINCT_EXIT=$PHP_PG_EXIT
ROLES_DISTINCT_OUTPUT="$PHP_PG_OUTPUT"
set -e

# Normalize exit code (never 255)
if [[ $ROLES_DISTINCT_EXIT -ne 0 ]] && [[ $ROLES_DISTINCT_EXIT -ne 1 ]]; then
  ROLES_DISTINCT_EXIT=1
fi

if [[ $ROLES_DISTINCT_EXIT -ne 0 ]]; then
  echo "✗ Roles guard_name check failed"
  echo "  Error: $ROLES_DISTINCT_OUTPUT"
  exit 1
fi

# Query for count of mismatches
ROLES_BAD_SQL="SELECT COUNT(*) FROM roles WHERE COALESCE(guard_name,'') <> '${EXPECT_GUARD}';"

set +e
php_pg_query "$ROLES_BAD_SQL"
ROLES_BAD_EXIT=$PHP_PG_EXIT
ROLES_BAD_OUTPUT="$PHP_PG_OUTPUT"
set -e

# Normalize exit code (never 255)
if [[ $ROLES_BAD_EXIT -ne 0 ]] && [[ $ROLES_BAD_EXIT -ne 1 ]]; then
  ROLES_BAD_EXIT=1
fi

if [[ $ROLES_BAD_EXIT -ne 0 ]]; then
  echo "✗ Roles guard_name check failed"
  echo "  Error: $ROLES_BAD_OUTPUT"
  exit 1
fi

# Parse results
ROLES_BAD_COUNT="$(echo "$ROLES_BAD_OUTPUT" | grep -v '^$' | grep -v 'PDO_ERROR' | grep -v 'ERROR' | head -1 | tr -d ' ' || echo "0")"
ROLES_DISTINCT_VALUES="$(echo "$ROLES_DISTINCT_OUTPUT" | grep -v '^$' | grep -v 'PDO_ERROR' | grep -v 'ERROR' || true)"

# Check if all guard_name values match expected
if [[ "$ROLES_BAD_COUNT" == "0" ]]; then
  echo "✓ roles.guard_name normalized: ${EXPECT_GUARD}"
else
  echo "✗ roles.guard_name not normalized"
  echo "  Expected: ${EXPECT_GUARD}"
  echo "  Mismatched rows: ${ROLES_BAD_COUNT}"
  if [[ -n "$ROLES_DISTINCT_VALUES" ]]; then
    echo "  Distinct values found:"
    echo "$ROLES_DISTINCT_VALUES" | while IFS='|' read -r guard count; do
      [[ -n "$guard" ]] && echo "    - ${guard}: ${count} row(s)"
    done
  fi
  exit 1
fi
echo

echo "4. Checking permissions guard_name normalization..."
# Use PHP PDO directly (no Laravel bootstrap, no artisan)
# Query for distinct guard_name values with counts
PERMS_DISTINCT_SQL="SELECT COALESCE(guard_name,'') AS g, COUNT(*) AS c FROM permissions GROUP BY COALESCE(guard_name,'') ORDER BY g;"

set +e
php_pg_query_kv "$PERMS_DISTINCT_SQL"
PERMS_DISTINCT_EXIT=$PHP_PG_EXIT
PERMS_DISTINCT_OUTPUT="$PHP_PG_OUTPUT"
set -e

# Normalize exit code (never 255)
if [[ $PERMS_DISTINCT_EXIT -ne 0 ]] && [[ $PERMS_DISTINCT_EXIT -ne 1 ]]; then
  PERMS_DISTINCT_EXIT=1
fi

if [[ $PERMS_DISTINCT_EXIT -ne 0 ]]; then
  echo "✗ Permissions guard_name check failed"
  echo "  Error: $PERMS_DISTINCT_OUTPUT"
  exit 1
fi

# Query for count of mismatches
PERMS_BAD_SQL="SELECT COUNT(*) FROM permissions WHERE COALESCE(guard_name,'') <> '${EXPECT_GUARD}';"

set +e
php_pg_query "$PERMS_BAD_SQL"
PERMS_BAD_EXIT=$PHP_PG_EXIT
PERMS_BAD_OUTPUT="$PHP_PG_OUTPUT"
set -e

# Normalize exit code (never 255)
if [[ $PERMS_BAD_EXIT -ne 0 ]] && [[ $PERMS_BAD_EXIT -ne 1 ]]; then
  PERMS_BAD_EXIT=1
fi

if [[ $PERMS_BAD_EXIT -ne 0 ]]; then
  echo "✗ Permissions guard_name check failed"
  echo "  Error: $PERMS_BAD_OUTPUT"
  exit 1
fi

# Parse results
PERMS_BAD_COUNT="$(echo "$PERMS_BAD_OUTPUT" | grep -v '^$' | grep -v 'PDO_ERROR' | grep -v 'ERROR' | head -1 | tr -d ' ' || echo "0")"
PERMS_DISTINCT_VALUES="$(echo "$PERMS_DISTINCT_OUTPUT" | grep -v '^$' | grep -v 'PDO_ERROR' | grep -v 'ERROR' || true)"

# Check if all guard_name values match expected
if [[ "$PERMS_BAD_COUNT" == "0" ]]; then
  echo "✓ permissions.guard_name normalized: ${EXPECT_GUARD}"
else
  echo "✗ permissions.guard_name not normalized"
  echo "  Expected: ${EXPECT_GUARD}"
  echo "  Mismatched rows: ${PERMS_BAD_COUNT}"
  if [[ -n "$PERMS_DISTINCT_VALUES" ]]; then
    echo "  Distinct values found:"
    echo "$PERMS_DISTINCT_VALUES" | while IFS='|' read -r guard count; do
      [[ -n "$guard" ]] && echo "    - ${guard}: ${count} row(s)"
    done
  fi
  exit 1
fi
echo

echo "5. Checking for duplicate role names..."
# Use PHP PDO directly (no Laravel bootstrap, no artisan)
# Query for duplicate (guard_name, name) pairs
DUP_ROLES_SQL="SELECT COALESCE(guard_name,'') AS guard_name, name, COUNT(*) AS c FROM roles GROUP BY COALESCE(guard_name,''), name HAVING COUNT(*) > 1 ORDER BY c DESC, guard_name, name;"

set +e
php_pg_query_all "$DUP_ROLES_SQL"
DUP_ROLES_EXIT=$PHP_PG_EXIT
DUP_ROLES_OUTPUT="$PHP_PG_OUTPUT"
set -e

# Normalize exit code (never 255)
if [[ $DUP_ROLES_EXIT -ne 0 ]] && [[ $DUP_ROLES_EXIT -ne 1 ]]; then
  DUP_ROLES_EXIT=1
fi

if [[ $DUP_ROLES_EXIT -ne 0 ]]; then
  echo "✗ Duplicate roles check failed"
  echo "  Error: $DUP_ROLES_OUTPUT"
  exit 1
fi

# Parse results - filter out error messages
DUP_ROLES_FOUND="$(echo "$DUP_ROLES_OUTPUT" | grep -v '^$' | grep -v 'PDO_ERROR' | grep -v 'ERROR' || true)"

if [[ -z "$DUP_ROLES_FOUND" ]]; then
  # No duplicates found = PASS
  echo "✓ No duplicate role names"
else
  # Duplicates found = FAIL
  echo "✗ Duplicate roles found"
  echo "$DUP_ROLES_FOUND" | while IFS='|' read -r guard_name name count; do
    [[ -n "$guard_name" ]] && [[ -n "$name" ]] && [[ -n "$count" ]] && echo "  ${guard_name}:${name} x${count}"
  done
  exit 1
fi
echo

echo "6. Checking for duplicate permission names..."
# Use PHP PDO directly (no Laravel bootstrap, no artisan)
# Query for duplicate (guard_name, name) pairs
DUP_PERMS_SQL="SELECT COALESCE(guard_name,'') AS guard_name, name, COUNT(*) AS c FROM permissions GROUP BY COALESCE(guard_name,''), name HAVING COUNT(*) > 1 ORDER BY c DESC, guard_name, name;"

set +e
php_pg_query_all "$DUP_PERMS_SQL"
DUP_PERMS_EXIT=$PHP_PG_EXIT
DUP_PERMS_OUTPUT="$PHP_PG_OUTPUT"
set -e

# Normalize exit code (never 255)
if [[ $DUP_PERMS_EXIT -ne 0 ]] && [[ $DUP_PERMS_EXIT -ne 1 ]]; then
  DUP_PERMS_EXIT=1
fi

if [[ $DUP_PERMS_EXIT -ne 0 ]]; then
  echo "✗ Duplicate permissions check failed"
  echo "  Error: $DUP_PERMS_OUTPUT"
  exit 1
fi

# Parse results - filter out error messages
DUP_PERMS_FOUND="$(echo "$DUP_PERMS_OUTPUT" | grep -v '^$' | grep -v 'PDO_ERROR' | grep -v 'ERROR' || true)"

if [[ -z "$DUP_PERMS_FOUND" ]]; then
  # No duplicates found = PASS
  echo "✓ No duplicate permission names"
else
  # Duplicates found = FAIL
  echo "✗ Duplicate permissions found"
  echo "$DUP_PERMS_FOUND" | while IFS='|' read -r guard_name name count; do
    [[ -n "$guard_name" ]] && [[ -n "$name" ]] && [[ -n "$count" ]] && echo "  ${guard_name}:${name} x${count}"
  done
  exit 1
fi
echo

echo "7. Verifying Spatie Role count looks sane..."
# Use PHP PDO directly (no Laravel bootstrap, no artisan)
# Query for role count
ROLE_COUNT_SQL="SELECT COUNT(*) FROM roles;"

set +e
php_pg_query "$ROLE_COUNT_SQL"
ROLE_COUNT_EXIT=$PHP_PG_EXIT
ROLE_COUNT_OUTPUT="$PHP_PG_OUTPUT"
set -e

# Normalize exit code (never 255)
if [[ $ROLE_COUNT_EXIT -ne 0 ]] && [[ $ROLE_COUNT_EXIT -ne 1 ]]; then
  ROLE_COUNT_EXIT=1
fi

if [[ $ROLE_COUNT_EXIT -ne 0 ]]; then
  echo "✗ Role count check failed"
  echo "  Error: $ROLE_COUNT_OUTPUT"
  exit 1
fi

# Parse result - filter out error messages and get count
ROLE_COUNT_RAW="$(echo "$ROLE_COUNT_OUTPUT" | grep -v '^$' | grep -v 'PDO_ERROR' | grep -v 'ERROR' | head -1 | tr -d ' ' || echo "")"

# Validate count is numeric and in range
if [[ -z "$ROLE_COUNT_RAW" ]]; then
  echo "✗ Role count check failed: empty result"
  exit 1
fi

# Check if numeric
if ! [[ "$ROLE_COUNT_RAW" =~ ^[0-9]+$ ]]; then
  echo "✗ Role count check failed: non-numeric result: ${ROLE_COUNT_RAW}"
  exit 1
fi

ROLE_COUNT=$((ROLE_COUNT_RAW + 0))  # Convert to integer

# Validate range: 1 <= count <= 200
MIN_COUNT=1
MAX_COUNT=200
if [[ $ROLE_COUNT -ge $MIN_COUNT ]] && [[ $ROLE_COUNT -le $MAX_COUNT ]]; then
  echo "✓ Role count sane: ${ROLE_COUNT}"
else
  echo "✗ Role count out of range: ${ROLE_COUNT} (expected ${MIN_COUNT}..${MAX_COUNT})"
  exit 1
fi
echo

echo "8. Verifying config values..."
# Use plain PHP to load config/permission.php directly (no Laravel bootstrap, no artisan)
# Determine expected values from environment
EXPECT_GUARD="${PERMISSION_GUARD_NAME:-${AUTH_GUARD:-sanctum}}"
EXPECT_CONN="${PERMISSION_CONNECTION:-pgsql}"

set +e
CONFIG_OUTPUT="$(cd "$ROOT_DIR" && EXPECT_GUARD="$EXPECT_GUARD" EXPECT_CONN="$EXPECT_CONN" php -r '
  $root=__DIR__;
  $cfgFile=$root."/config/permission.php";
  if (!file_exists($cfgFile)) {
    fwrite(STDERR, "ERROR: config/permission.php not found".PHP_EOL);
    exit(1);
  }
  $cfg=require $cfgFile;
  if (!is_array($cfg)) {
    fwrite(STDERR, "ERROR: config/permission.php did not return array".PHP_EOL);
    exit(1);
  }
  $expectGuard=getenv("EXPECT_GUARD") ?: "sanctum";
  $expectConn=getenv("EXPECT_CONN") ?: "pgsql";
  $errors=array();
  $ok=true;
  
  // Check guard_name (prefer defaults.guard_name, fallback to defaults.guard)
  $guardName=null;
  if (isset($cfg["defaults"]["guard_name"])) {
    $guardName=$cfg["defaults"]["guard_name"];
  } elseif (isset($cfg["defaults"]["guard"])) {
    $guardName=$cfg["defaults"]["guard"];
  }
  if ($guardName===null) {
    $errors[]="defaults.guard_name or defaults.guard missing";
    $ok=false;
  } else {
    $guardNameStr=(string)$guardName;
    if ($guardNameStr!==$expectGuard) {
      $errors[]="defaults.guard_name mismatch: expected \"$expectGuard\", got \"$guardNameStr\"";
      $ok=false;
    } else {
      echo "✓ defaults.guard_name: $guardNameStr".PHP_EOL;
    }
  }
  
  // Check connection
  $conn=isset($cfg["connection"]) ? (string)$cfg["connection"] : null;
  if ($conn===null || $conn==="") {
    $errors[]="connection key missing or empty";
    $ok=false;
  } else {
    if ($conn!==$expectConn) {
      $errors[]="connection mismatch: expected \"$expectConn\", got \"$conn\"";
      $ok=false;
    } else {
      echo "✓ connection: $conn".PHP_EOL;
    }
  }
  
  if (!$ok) {
    foreach($errors as $e) {
      fwrite(STDERR, "✗ $e".PHP_EOL);
    }
    exit(1);
  }
  exit(0);
' 2>&1)"
CONFIG_EXIT=$?
set -e

# Normalize exit code (never 255)
if [[ $CONFIG_EXIT -ne 0 ]] && [[ $CONFIG_EXIT -ne 1 ]]; then
  CONFIG_EXIT=1
fi

if [[ $CONFIG_EXIT -eq 0 ]]; then
  echo "$CONFIG_OUTPUT"
  echo
  echo "✓ Config values look correct"
else
  echo "$CONFIG_OUTPUT"
  echo
  echo "✗ Config values incorrect"
  exit 1
fi
echo

echo "=== RBAC Guardrail PASSED ==="
