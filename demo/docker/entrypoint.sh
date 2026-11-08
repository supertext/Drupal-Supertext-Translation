#!/bin/sh
# Demo start: database, Drupal install (once), modules, languages, demo accounts,
# Supertext translator and content, then Apache. Safe to run on every start.
set -e
cd /opt/drupal

# Railway's MySQL service exposes its connection as separate variables.
if [ -z "$DATABASE_URL" ] && [ -n "$MYSQLHOST" ]; then
    export DATABASE_URL="mysql://${MYSQLUSER:-root}:${MYSQLPASSWORD:-}@${MYSQLHOST}:${MYSQLPORT:-3306}/${MYSQLDATABASE:-drupal}"
fi
if [ -z "$DATABASE_URL" ]; then
    echo "DATABASE_URL (or MYSQLHOST etc.) is not set" >&2
    exit 1
fi
if [ -z "$DEMO_ADMIN_EMAIL" ] || [ -z "$DEMO_ADMIN_PASSWORD" ]; then
    echo "DEMO_ADMIN_EMAIL and DEMO_ADMIN_PASSWORD must be set" >&2
    exit 1
fi

# Apache's "Listen" uses the PORT variable; it must be visible to Apache.
echo "export PORT=${PORT:-8080}" >> /etc/apache2/envvars

echo "Waiting for the database..."
i=0
until php -r '$u = parse_url(getenv("DATABASE_URL")); new PDO(sprintf("mysql:host=%s;port=%d", $u["host"], $u["port"] ?? 3306), urldecode($u["user"] ?? ""), urldecode($u["pass"] ?? ""));' >/dev/null 2>&1; do
    i=$((i + 1))
    if [ "$i" -ge 60 ]; then
        echo "Database not reachable after 120 s" >&2
        exit 1
    fi
    sleep 2
done
# Several demos share one MySQL server: each gets its own database.
php -r '$u = parse_url(getenv("DATABASE_URL")); $db = getenv("DRUPAL_DB_NAME") ?: (ltrim($u["path"] ?? "", "/") ?: "drupal"); $p = new PDO(sprintf("mysql:host=%s;port=%d", $u["host"], $u["port"] ?? 3306), urldecode($u["user"] ?? ""), urldecode($u["pass"] ?? "")); $p->exec("CREATE DATABASE IF NOT EXISTS `" . str_replace("`", "", $db) . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");'

if ! drush status --field=bootstrap 2>/dev/null | grep -q Successful; then
    echo "Installing Drupal..."
    drush site:install standard -y --site-name="Supertext Demo (Drupal + TMGMT)" \
        --account-name=admin --account-mail="$DEMO_ADMIN_EMAIL" --account-pass="$DEMO_ADMIN_PASSWORD" \
        --site-mail="$DEMO_ADMIN_EMAIL"
fi

# Since Drupal 11.2 the content types come from recipes, not the install profile.
if [ "$(drush php:eval 'echo \Drupal\node\Entity\NodeType::load("page") ? "yes" : "no";' 2>/dev/null)" != "yes" ]; then
    for recipe in basic_html_format_editor page_content_type article_content_type content_editor_role; do
        drush recipe "core/recipes/$recipe" -y
    done
fi

drush pm:install -y language content_translation tmgmt tmgmt_content tmgmt_supertext_ai
drush php:script /opt/drupal/demo/setup.php
drush updatedb -y
drush cache:rebuild

chown -R www-data:www-data web/sites/default/files /opt/drupal/private

# Exactly one Apache MPM: mod_php needs prefork. On some hosts (Railway) another MPM
# ends up enabled, and Apache refuses to start with "More than one MPM loaded".
rm -f /etc/apache2/mods-enabled/mpm_event.* /etc/apache2/mods-enabled/mpm_worker.*
[ -e /etc/apache2/mods-enabled/mpm_prefork.load ] || a2enmod -q mpm_prefork

echo "Drupal demo ready"
exec apache2-foreground
