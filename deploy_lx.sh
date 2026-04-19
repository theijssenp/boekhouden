#!/bin/bash
# Deploy boekhouden vanaf /tmp/boekhouden_deploy naar /var/www/boekhouden
# Vooraf: push_to_lx.sh draaien om bestanden klaar te zetten
# Gebruik: sudo deploy-boekhouden
set -e

SRC="/tmp/boekhouden_deploy"
DEST="/var/www/boekhouden"

if [ "$(id -u)" -ne 0 ]; then
    echo "Dit script moet als root gedraaid worden (sudo)."
    exit 1
fi

if [ ! -d "$SRC" ]; then
    echo "Geen bestanden gevonden in $SRC."
    echo "Draai eerst push_to_lx.sh om bestanden klaar te zetten."
    exit 1
fi

echo "=== Boekhouden Deploy ==="

# Kopieer bestanden, behoud config.php op doel
rsync -a --exclude='config.php' "$SRC/" "$DEST/"

# Eigenaarschap corrigeren
chown -R www-data:www-data "$DEST"

# Database migraties - haal credentials uit config.php
DB_USER=$(php -r "require '$DEST/php/config.php'; echo DB_USER;")
DB_PASS=$(php -r "require '$DEST/php/config.php'; echo DB_PASS;")
DB_NAME=$(php -r "require '$DEST/php/config.php'; echo DB_NAME;")

for migration in "$SRC"/sql/migration_*.sql; do
    [ -f "$migration" ] || continue
    echo "Migratie: $(basename $migration)"
    mysql -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" < "$migration"
done

# Opruimen
rm -rf "$SRC"

echo "=== Klaar ==="