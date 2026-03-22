#!/usr/bin/env bash
set -e

echo ""
echo "=== BimmerTech Firmware App — Setup ==="
echo ""

# 1. Check PHP
if ! command -v php &>/dev/null; then
    echo "ERROR: PHP is not installed."
    exit 1
fi
PHP_VERSION=$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')
echo "✓ PHP $PHP_VERSION found"

# 2. Check Composer
if ! command -v composer &>/dev/null; then
    echo "ERROR: Composer is not installed. Run: sudo apt install composer"
    exit 1
fi
echo "✓ Composer found"

# 3. Install PHP dependencies
echo ""
echo "--- Installing PHP dependencies ---"
composer install --no-dev --optimize-autoloader --no-scripts --no-interaction
echo "✓ Dependencies installed"

# Clean and set correct .env values
echo ""
echo "--- Configuring .env ---"

ENV_FILE=".env"

# Remove Symfony auto-generated blocks (###> ... ###< sections)
sed -i '/###>/,/###</d' "$ENV_FILE"

# Remove duplicate blank lines
sed -i '/^$/N;/^\n$/d' "$ENV_FILE"

# Set correct values (update if exists, append if not)
set_env() {
    local KEY=$1
    local VALUE=$2
    if grep -q "^${KEY}=" "$ENV_FILE" 2>/dev/null; then
        sed -i "s|^${KEY}=.*|${KEY}=${VALUE}|" "$ENV_FILE"
    else
        echo "${KEY}=${VALUE}" >> "$ENV_FILE"
    fi
}

set_env "APP_ENV"   "prod"
set_env "APP_DEBUG" "0"

echo "✓ .env configured"

# 4. Admin password setup (commented out — password is hardcoded in security.yaml)
# To enable dynamic password setup, uncomment this section and update security.yaml
# to use '%env(ADMIN_PASSWORD_HASH)%' instead of a hardcoded hash.
#
# echo ""
# echo "--- Admin password setup ---"
# echo "Choose a password for the admin panel (username is: admin)"
# echo ""
#
# while true; do
#     read -s -p "Enter admin password (min 8 chars): " ADMIN_PASS
#     echo ""
#     read -s -p "Confirm admin password:              " ADMIN_PASS2
#     echo ""
#
#     if [ -z "$ADMIN_PASS" ]; then
#         echo "Password cannot be empty. Try again."; echo ""; continue
#     fi
#     if [ "$ADMIN_PASS" != "$ADMIN_PASS2" ]; then
#         echo "Passwords do not match. Try again."; echo ""; continue
#     fi
#     if [ ${#ADMIN_PASS} -lt 8 ]; then
#         echo "Password must be at least 8 characters. Try again."; echo ""; continue
#     fi
#     break
# done
#
# TMPFILE=$(mktemp)
# printf '%s' "$ADMIN_PASS" > "$TMPFILE"
#
# HASHED=$(php -r "
# require_once __DIR__ . '/vendor/autoload.php';
# use Symfony\Component\PasswordHasher\Hasher\NativePasswordHasher;
# \$h = new NativePasswordHasher(null, null, 13);
# echo \$h->hash(file_get_contents('$TMPFILE'));
# ")
#
# rm -f "$TMPFILE"
#
# if [ -z "$HASHED" ]; then
#     echo "ERROR: Could not hash password."
#     exit 1
# fi
#
# php -r "
# \$env = file_get_contents('.env');
# \$newLine = 'ADMIN_PASSWORD_HASH=' . '$HASHED';
# if (preg_match('/^ADMIN_PASSWORD_HASH=.*/m', \$env)) {
#     \$env = preg_replace('/^ADMIN_PASSWORD_HASH=.*/m', \$newLine, \$env);
# } else {
#     \$env .= PHP_EOL . \$newLine . PHP_EOL;
# }
# file_put_contents('.env', \$env);
# "
# echo "✓ Admin password saved"

# 5. Create var directory
mkdir -p var
chmod 775 var

# 6. Create database table with PHP PDO
echo ""
echo "--- Setting up database ---"
php -r "
\$db = new PDO('sqlite:' . __DIR__ . '/var/data.db');
\$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
\$db->exec('CREATE TABLE IF NOT EXISTS software_version (
    id             INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
    name           VARCHAR(120) NOT NULL,
    system_version VARCHAR(120) NOT NULL,
    st_link        VARCHAR(500) DEFAULT NULL,
    gd_link        VARCHAR(500) DEFAULT NULL,
    link           VARCHAR(500) DEFAULT NULL,
    is_latest      BOOLEAN NOT NULL DEFAULT 0,
    sort_order     INTEGER DEFAULT NULL
)');
\$db->exec('CREATE INDEX IF NOT EXISTS idx_sv ON software_version (system_version)');
echo 'Database ready' . PHP_EOL;
"
echo "✓ Database created at var/data.db"

# Mark migration as done so Doctrine does not complain
php bin/console doctrine:migrations:migrate --no-interaction 2>/dev/null || true

# 7. Load seed data
echo ""
echo "--- Loading firmware data ---"

EXISTING=$(php -r "
try {
    \$db = new PDO('sqlite:' . __DIR__ . '/var/data.db');
    echo \$db->query('SELECT COUNT(*) FROM software_version')->fetchColumn();
} catch(Exception \$e) { echo 0; }
" 2>/dev/null || echo "0")

if [ "${EXISTING:-0}" -gt "0" ] 2>/dev/null; then
    echo "✓ Database already has $EXISTING entries — skipping seed"
else
    if command -v sqlite3 &>/dev/null; then
        sqlite3 var/data.db < seed.sql
        COUNT=$(sqlite3 var/data.db "SELECT COUNT(*) FROM software_version;")
        echo "✓ Loaded $COUNT firmware entries"
    else
        php -r "
            \$db = new PDO('sqlite:' . __DIR__ . '/var/data.db');
            \$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            \$sql = file_get_contents('seed.sql');
            \$lines = array_filter(explode(PHP_EOL, \$sql), function(\$l) {
                \$t = trim(\$l);
                return \$t !== '' && strpos(\$t, '--') !== 0;
            });
            \$stmts = array_filter(array_map('trim', explode(';', implode(PHP_EOL, \$lines))));
            foreach (\$stmts as \$s) { if (\$s) \$db->exec(\$s); }
            \$count = \$db->query('SELECT COUNT(*) FROM software_version')->fetchColumn();
            echo 'Loaded ' . \$count . ' firmware entries' . PHP_EOL;
        "
        echo "✓ Firmware data loaded via PHP"
    fi
fi

# 8. Install bundle assets (fixes EasyAdmin CSS/JS in admin panel)
echo ""
echo "--- Installing assets ---"
php bin/console assets:install public --no-interaction
echo "✓ Assets installed"

# 9. Clear and warm cache
echo ""
echo "--- Clearing cache ---"
rm -rf var/cache/*
php bin/console cache:clear --no-debug --no-interaction
php bin/console cache:warmup --no-debug --no-interaction
echo "✓ Cache cleared"

echo ""
echo "=============================================="
echo "  Setup complete!"
echo "=============================================="
echo ""
echo "Start the app:"
echo "  php -S localhost:8000 -t public/"
echo ""
echo "Then open:"
echo "  Customer page: http://localhost:8000/carplay/software-download"
echo "  Admin panel:   http://localhost:8000/admin"
echo "    Username: admin"
echo "    Password: Admin1234"
echo ""