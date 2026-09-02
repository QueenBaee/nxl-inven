#!/usr/bin/env bash
# ==============================================================================
# Pre-Deployment Preflight Check for POS & Inventory Monolith
# Read-only verification before running maintenance-mode deployment
# ==============================================================================

set -euo pipefail

EXIT_CODE=0
PHP_BIN="${PHP_BIN:-php}"

echo "=============================================================================="
echo ">>> [PREFLIGHT] Starting Pre-Deployment Sanity Checks"
echo "=============================================================================="

# 1. Check PHP CLI and Version
if ! command -v "${PHP_BIN}" >/dev/null 2>&1; then
    echo "❌ [FAIL] PHP binary '${PHP_BIN}' not found in PATH."
    exit 1
fi

PHP_VERSION=$("${PHP_BIN}" -r 'echo PHP_VERSION;')
echo "✓ PHP Version: ${PHP_VERSION}"

# Check PHP >= 8.3
"${PHP_BIN}" -r '
if (version_compare(PHP_VERSION, "8.3.0", "<")) {
    echo "❌ [FAIL] PHP 8.3+ required. Found: " . PHP_VERSION . "\n";
    exit(1);
}
'

# 2. Check Required PHP Extensions
REQUIRED_EXTENSIONS=(
    bcmath
    ctype
    curl
    dom
    fileinfo
    filter
    json
    mbstring
    openssl
    pcre
    pdo
    pdo_mysql
    tokenizer
    xml
    zip
)

echo "--- Checking PHP Extensions ---"
for ext in "${REQUIRED_EXTENSIONS[@]}"; do
    if "${PHP_BIN}" -r "exit(extension_loaded('${ext}') ? 0 : 1);"; then
        echo "  ✓ Extension '${ext}' is loaded"
    else
        echo "  ❌ [FAIL] Required PHP extension '${ext}' is MISSING!"
        EXIT_CODE=1
    fi
done

# 3. Check Composer
if command -v composer >/dev/null 2>&1; then
    COMPOSER_VER=$(composer --version | head -n 1)
    echo "✓ Composer: ${COMPOSER_VER}"
else
    echo "❌ [FAIL] Composer is not installed in PATH."
    EXIT_CODE=1
fi

# 4. Check .env file and Critical Production Variables
if [ ! -f ".env" ]; then
    echo "❌ [FAIL] Production '.env' file does not exist!"
    EXIT_CODE=1
else
    echo "✓ Production '.env' file found"

    # Check APP_KEY
    if grep -qE '^APP_KEY=base64:.+' .env; then
        echo "  ✓ APP_KEY is generated"
    else
        echo "  ❌ [FAIL] APP_KEY is missing or invalid in .env!"
        EXIT_CODE=1
    fi

    # Check APP_DEBUG
    if grep -qE '^APP_DEBUG=(false|0|off)' .env; then
        echo "  ✓ APP_DEBUG is set to false"
    elif grep -qE '^APP_DEBUG=(true|1|on)' .env; then
        echo "  ⚠️ [WARN] APP_DEBUG is true. Must be set to false in production!"
    fi
fi

# 5. Check Directory Permissions (Writable Storage & Bootstrap Cache)
for dir in storage bootstrap/cache storage/framework/views storage/framework/sessions storage/logs; do
    if [ -d "${dir}" ]; then
        if [ -w "${dir}" ]; then
            echo "  ✓ Directory '${dir}' is writable"
        else
            echo "  ❌ [FAIL] Directory '${dir}' is NOT writable!"
            EXIT_CODE=1
        fi
    else
        echo "  ⚠️ Directory '${dir}' does not exist yet (will be created during setup)"
    fi
done

# 6. Check Database Connectivity
if "${PHP_BIN}" artisan db:monitor >/dev/null 2>&1 || "${PHP_BIN}" -r "
try {
    require __DIR__ . '/vendor/autoload.php';
    \$app = require_once __DIR__ . '/bootstrap/app.php';
    \$kernel = \$app->make(Illuminate\Contracts\Console\Kernel::class);
    \$kernel->bootstrap();
    Illuminate\Support\Facades\DB::connection()->getPdo();
    exit(0);
} catch (\Throwable \$e) {
    exit(1);
}
" 2>/dev/null; then
    echo "✓ Database connection test passed"
else
    echo "⚠️ [WARN] Database connectivity check failed or vendor not installed yet."
fi

echo "=============================================================================="
if [ ${EXIT_CODE} -eq 0 ]; then
    echo "✅ [PREFLIGHT PASSED] Environment is ready for deployment."
else
    echo "❌ [PREFLIGHT FAILED] Critical requirements missing. Fix errors before deploying."
fi
echo "=============================================================================="

exit ${EXIT_CODE}

