#!/bin/bash
#
# Boekhouden - installatie van de database
#
#   ./setup.sh                     installeer of werk de database bij
#   ./setup.sh --with-sample-data  idem, plus 130 fictieve transacties
#   ./setup.sh --help              alle opties
#
# Het script is idempotent: het maakt aan wat ontbreekt en laat bestaande
# gegevens met rust. Er wordt nooit een database of tabel verwijderd.
#
# @author P. Theijssen

set -euo pipefail

cd "$(dirname "${BASH_SOURCE[0]}")"

# --- instellingen (overschrijfbaar via omgevingsvariabelen of vlaggen) ---
DB_HOST="${DB_HOST:-localhost}"
DB_NAME="${DB_NAME:-boekhouden}"
DB_USER="${DB_USER:-boekhouden}"
DB_PASS="${DB_PASS:-}"
ADMIN_USER="${ADMIN_USER:-admin}"
ADMIN_EMAIL="${ADMIN_EMAIL:-admin@example.com}"
ADMIN_PASS="${ADMIN_PASS:-}"
WITH_SAMPLE_DATA=0

# Beheerderstoegang tot de databaseserver, om de database en gebruiker aan
# te maken. Leeg laten om de standaard te gebruiken (root zonder wachtwoord
# op macOS/Homebrew, `sudo mysql` op Linux).
ROOT_USER="${ROOT_USER:-root}"
ROOT_PASS="${ROOT_PASS:-}"

usage() {
    sed -n '2,10p' "${BASH_SOURCE[0]}" | sed 's/^# \{0,1\}//'
    cat <<'EOF'

Opties:
  --with-sample-data   voeg voorbeelddata toe (alleen op een lege administratie)
  --db-name NAAM       databasenaam           (standaard: boekhouden)
  --db-user NAAM       databasegebruiker      (standaard: boekhouden)
  --db-pass WACHTW     wachtwoord             (standaard: automatisch gegenereerd)
  --db-host HOST       databasehost           (standaard: localhost)
  --admin-user NAAM    inlognaam beheerder    (standaard: admin)
  --admin-pass WACHTW  wachtwoord beheerder   (standaard: automatisch gegenereerd)
  --admin-email ADRES  e-mailadres beheerder  (standaard: admin@example.com)
  --root-user NAAM     beheeraccount van de databaseserver (standaard: root)
  --root-pass WACHTW   wachtwoord daarvan
  -h, --help           deze uitleg

Alle opties kunnen ook als omgevingsvariabele (DB_NAME, ADMIN_PASS, ...).
EOF
}

while [[ $# -gt 0 ]]; do
    case "$1" in
        --with-sample-data) WITH_SAMPLE_DATA=1; shift ;;
        --db-name)     DB_NAME="$2";     shift 2 ;;
        --db-user)     DB_USER="$2";     shift 2 ;;
        --db-pass)     DB_PASS="$2";     shift 2 ;;
        --db-host)     DB_HOST="$2";     shift 2 ;;
        --admin-user)  ADMIN_USER="$2";  shift 2 ;;
        --admin-pass)  ADMIN_PASS="$2";  shift 2 ;;
        --admin-email) ADMIN_EMAIL="$2"; shift 2 ;;
        --root-user)   ROOT_USER="$2";   shift 2 ;;
        --root-pass)   ROOT_PASS="$2";   shift 2 ;;
        -h|--help)     usage; exit 0 ;;
        *) echo "Onbekende optie: $1" >&2; usage >&2; exit 1 ;;
    esac
done

step() { printf '\n\033[1m%s\033[0m\n' "$*"; }
ok()   { printf '  \033[32m✓\033[0m %s\n' "$*"; }
fail() { printf '  \033[31m✗\033[0m %s\n' "$*" >&2; exit 1; }

# --- 1. controleer of alles aanwezig is ---------------------------------
step "[1/5] Vereisten controleren"

command -v php >/dev/null || fail "php is niet gevonden. Installeer PHP 8.0 of nieuwer."
php -r 'exit(version_compare(PHP_VERSION, "8.0", ">=") ? 0 : 1);' \
    || fail "PHP $(php -r 'echo PHP_VERSION;') is te oud, 8.0 of nieuwer is vereist."
php -r 'exit(extension_loaded("pdo_mysql") ? 0 : 1);' \
    || fail "De PHP-extensie pdo_mysql ontbreekt (op Debian/Ubuntu: apt install php-mysql)."
ok "php $(php -r 'echo PHP_VERSION;') met pdo_mysql"

MYSQL_BIN=""
for candidate in mariadb mysql; do
    command -v "$candidate" >/dev/null && { MYSQL_BIN="$candidate"; break; }
done
[[ -n "$MYSQL_BIN" ]] || fail "Geen mysql- of mariadb-client gevonden."
ok "databaseclient: $MYSQL_BIN"

# Beheerderstoegang bepalen: eerst zonder sudo, dan met.
admin_sql() { "${ADMIN_CMD[@]}" "$@"; }

ADMIN_CMD=("$MYSQL_BIN" -h "$DB_HOST" -u "$ROOT_USER")
[[ -n "$ROOT_PASS" ]] && ADMIN_CMD+=("-p${ROOT_PASS}")

if ! admin_sql -e 'SELECT 1' >/dev/null 2>&1; then
    ADMIN_CMD=(sudo "$MYSQL_BIN" -u "$ROOT_USER")
    [[ -n "$ROOT_PASS" ]] && ADMIN_CMD+=("-p${ROOT_PASS}")
    echo "  Beheerderstoegang tot de database vereist sudo..."
    admin_sql -e 'SELECT 1' >/dev/null 2>&1 \
        || fail "Kan niet als '$ROOT_USER' inloggen op de databaseserver. Draait hij, en klopt --root-pass?"
fi
ok "verbinding met de databaseserver op $DB_HOST"

# --- 2. database en gebruiker aanmaken ----------------------------------
step "[2/5] Database en gebruiker"

DB_EXISTED=$(admin_sql --batch --skip-column-names \
    -e "SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = '${DB_NAME}';")

if [[ -z "$DB_PASS" ]]; then
    if [[ -f php/config.php ]]; then
        # Hergebruik het bestaande wachtwoord, anders breekt een tweede run de app.
        DB_PASS=$(php -r '
            $src = file_get_contents("php/config.php");
            preg_match("/DB_PASS[^,]*,\s*[\x27\"](.*?)[\x27\"]/", $src, $m);
            echo $m[1] ?? "";
        ')
    fi
    [[ -z "$DB_PASS" ]] && DB_PASS=$(php -r 'echo bin2hex(random_bytes(12));')
fi

admin_sql <<SQL
CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\`
  CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
ALTER USER '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost';
FLUSH PRIVILEGES;
SQL

[[ "$DB_EXISTED" == "0" ]] && ok "database '${DB_NAME}' aangemaakt" \
                           || ok "database '${DB_NAME}' bestond al, wordt bijgewerkt"
ok "databasegebruiker '${DB_USER}'@'localhost'"

# --- 3. schema installeren of bijwerken ---------------------------------
step "[3/5] Schema"

admin_sql "$DB_NAME" < sql/schema.sql
ok "tabellen, views en referentiedata bijgewerkt"

# --- 4. config.php en opslagmap -----------------------------------------
step "[4/5] Configuratie en opslag"

# Bonnetjes staan op het filesystem, niet in de database.
RECEIPT_DIR_PATH="${RECEIPT_DIR:-storage/receipts}"
mkdir -p "$RECEIPT_DIR_PATH"
chmod 770 "$RECEIPT_DIR_PATH"

# Tweede slot op de deur: ook als de map ooit binnen de docroot belandt,
# weigert Apache hem te serveren. Bonnetjes gaan alleen via view_receipt.php.
if [[ ! -f storage/.htaccess ]]; then
    cat > storage/.htaccess <<'EOF'
# Bonnetjes worden uitsluitend geserveerd door php/view_receipt.php,
# dat eerst controleert of de ingelogde gebruiker ze mag zien.
Require all denied
EOF
fi
ok "opslagmap ${RECEIPT_DIR_PATH} (rechten 770, afgeschermd)"

if [[ -f php/config.php ]]; then
    ok "php/config.php bestaat al, blijft ongewijzigd"
else
    umask 077
    sed -e "s|{{DB_HOST}}|${DB_HOST}|" \
        -e "s|{{DB_NAME}}|${DB_NAME}|" \
        -e "s|{{DB_USER}}|${DB_USER}|" \
        -e "s|{{DB_PASS}}|${DB_PASS}|" \
        php/config_example.php > php/config.php
    chmod 600 php/config.php
    ok "php/config.php aangemaakt"
fi

php -r 'require "php/config.php"; $pdo->query("SELECT 1");' \
    || fail "De app kan geen verbinding maken met de database. Controleer php/config.php."
ok "verbinding vanuit de app werkt"

# --- 5. beheerder en voorbeelddata --------------------------------------
step "[5/5] Beheerder"

USER_COUNT=$(admin_sql "$DB_NAME" --batch --skip-column-names -e "SELECT COUNT(*) FROM users;")

if [[ "$USER_COUNT" != "0" ]]; then
    ok "er zijn al ${USER_COUNT} gebruikers, er wordt geen beheerder aangemaakt"
    ADMIN_PASS=""
else
    [[ -z "$ADMIN_PASS" ]] && ADMIN_PASS=$(php -r 'echo bin2hex(random_bytes(6));')
    HASH=$(ADMIN_PASS="$ADMIN_PASS" php -r 'echo password_hash(getenv("ADMIN_PASS"), PASSWORD_DEFAULT);')
    admin_sql "$DB_NAME" <<SQL
INSERT INTO users (username, email, password_hash, full_name, user_type, is_active)
VALUES ('${ADMIN_USER}', '${ADMIN_EMAIL}', '${HASH}', 'Beheerder', 'administrator', 1);
SQL
    ok "beheerder '${ADMIN_USER}' aangemaakt"
fi

if [[ "$WITH_SAMPLE_DATA" == "1" ]]; then
    TX_COUNT=$(admin_sql "$DB_NAME" --batch --skip-column-names -e "SELECT COUNT(*) FROM transactions;")
    if [[ "$TX_COUNT" != "0" ]]; then
        echo "  Er staan al ${TX_COUNT} transacties in de database; voorbeelddata overgeslagen."
    else
        admin_sql "$DB_NAME" < sql/sample_data.sql
        ok "130 voorbeeldtransacties toegevoegd"
    fi
fi

# --- klaar ---------------------------------------------------------------
cat <<EOF

$(printf '\033[1mKlaar.\033[0m') Start de app met:

    php -S localhost:8081 router.php

en open daarna http://localhost:8081

EOF

if [[ -n "$ADMIN_PASS" ]]; then
    cat <<EOF
Inloggen:   ${ADMIN_USER} / ${ADMIN_PASS}
            (noteer dit wachtwoord, het wordt niet nog een keer getoond)

EOF
fi
