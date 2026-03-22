#!/usr/bin/env bash
# =============================================================================
# BimmerTech CarPlay Firmware Portal — One-shot Setup Script
# Run this once after extracting the project on a fresh Linux machine.
# =============================================================================
set -euo pipefail

RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'; NC='\033[0m'
ok()   { echo -e "${GREEN}✓${NC} $*"; }
warn() { echo -e "${YELLOW}!${NC} $*"; }
fail() { echo -e "${RED}✗ ERROR:${NC} $*"; exit 1; }

echo ""
echo "========================================================"
echo "  BimmerTech Firmware Portal — Setup"
echo "========================================================"
echo ""

# ── 1. Check PHP ─────────────────────────────────────────────────────────────
command -v php &>/dev/null || fail "PHP is not installed.
  Ubuntu/Debian: sudo apt install php php-sqlite3 php-xml php-mbstring php-curl php-zip
  Then re-run this script."

PHP_VER=$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')
ok "PHP $PHP_VER found"

# Require PHP 8.1+
php -r 'if(PHP_VERSION_ID < 80100) { fwrite(STDERR,"PHP 8.1+ required\n"); exit(1); }' \
  || fail "PHP 8.1 or higher is required. You have $PHP_VER."

# Check required extensions
for EXT in pdo_sqlite mbstring xml; do
  php -r "if(!extension_loaded('$EXT')){fwrite(STDERR,'');exit(1);}" 2>/dev/null \
    || fail "PHP extension '$EXT' is missing.
  Ubuntu/Debian: sudo apt install php-${EXT/pdo_/}"
done
ok "Required PHP extensions present"

# ── 2. Check Composer ─────────────────────────────────────────────────────────
command -v composer &>/dev/null || fail "Composer is not installed.
  Ubuntu/Debian: sudo apt install composer
  Or visit: https://getcomposer.org/download/"
ok "Composer found ($(composer --version --no-ansi 2>&1 | head -1))"

# ── 3. Check sqlite3 CLI ──────────────────────────────────────────────────────
if command -v sqlite3 &>/dev/null; then
  ok "sqlite3 CLI found"
  HAS_SQLITE3=1
else
  warn "sqlite3 CLI not found — will use PHP to seed the database instead."
  HAS_SQLITE3=0
fi

# ── 4. Install Composer dependencies ──────────────────────────────────────────
echo ""
echo "--- Installing PHP packages (this may take several minutes) ---"
composer install --no-dev --optimize-autoloader --no-interaction
ok "PHP packages installed"

# ── 5. Set admin password ─────────────────────────────────────────────────────
echo ""
echo "--- Admin panel password setup ---"
echo "Choose a password for the admin panel login (username is always 'admin')."
echo ""

while true; do
  read -s -p "Enter admin password (min 8 characters): " PASS1; echo ""
  read -s -p "Confirm password:                         " PASS2; echo ""
  if [[ "$PASS1" != "$PASS2" ]]; then
    warn "Passwords do not match. Try again."
  elif [[ ${#PASS1} -lt 8 ]]; then
    warn "Password must be at least 8 characters."
  else
    break
  fi
done

# Hash the password using Symfony's built-in command
HASH=$(php bin/console security:hash-password "$PASS1" --no-interaction 2>/dev/null \
  | grep -E '^\s*\$' | head -1 | sed 's/^[[:space:]]*//')

[[ -z "$HASH" ]] && fail "Could not hash password. Try running manually:
  php bin/console security:hash-password"

# Write the hash into .env, replacing the placeholder
if grep -q '^ADMIN_PASSWORD_HASH=' .env; then
  # Replace existing line (works on both GNU and BSD sed)
  sed -i "s|^ADMIN_PASSWORD_HASH=.*|ADMIN_PASSWORD_HASH=$HASH|" .env
else
  echo "ADMIN_PASSWORD_HASH=$HASH" >> .env
fi
ok "Admin password set"

# ── 6. Create var/ directory with correct permissions ─────────────────────────
mkdir -p var
chmod 775 var
ok "var/ directory ready"

# ── 7. Run database migrations (creates SQLite file + table) ─────────────────
echo ""
echo "--- Setting up database ---"
php bin/console doctrine:migrations:migrate --no-interaction
ok "Database table created at var/data.db"

# ── 8. Seed firmware version data ─────────────────────────────────────────────
echo ""
echo "--- Loading firmware version data ---"
if [[ $HAS_SQLITE3 -eq 1 ]]; then
  sqlite3 var/data.db < seed.sql
  COUNT=$(sqlite3 var/data.db "SELECT COUNT(*) FROM software_version;")
  ok "Loaded $COUNT firmware version entries via sqlite3"
else
  # Fall back to PHP PDO if sqlite3 CLI is not available
  php -r "
    \$pdo = new PDO('sqlite:' . __DIR__ . '/var/data.db');
    \$sql = file_get_contents(__DIR__ . '/seed.sql');
    // Strip comment lines and split on semicolons
    \$statements = array_filter(array_map('trim', explode(';', \$sql)));
    \$count = 0;
    foreach (\$statements as \$stmt) {
      if (\$stmt && strpos(\$stmt, '--') !== 0) {
        \$pdo->exec(\$stmt);
        if (stripos(\$stmt, 'INSERT') === 0) \$count++;
      }
    }
    echo 'Loaded ' . \$count . ' INSERT batches via PHP PDO' . PHP_EOL;
  "
  ok "Firmware data loaded via PHP"
fi

# ── 9. Clear and warm cache ───────────────────────────────────────────────────
echo ""
echo "--- Warming up cache ---"
php bin/console cache:clear  --no-debug 2>/dev/null || true
php bin/console cache:warmup --no-debug 2>/dev/null || true
ok "Cache ready"

# ── Done ──────────────────────────────────────────────────────────────────────
echo ""
echo "========================================================"
echo -e "  ${GREEN}Setup complete!${NC}"
echo "========================================================"
echo ""
echo "  Start the app:"
echo "    php -S localhost:8000 -t public/"
echo ""
echo "  Then open in your browser:"
echo "    Customer page : http://localhost:8000/carplay/software-download"
echo "    Admin panel   : http://localhost:8000/admin"
echo "      Username: admin"
echo "      Password: (what you just set)"
echo ""
