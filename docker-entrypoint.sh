#!/bin/sh
set -e
PORT="${PORT:-8080}"
sed -ri "s/^Listen .*/Listen ${PORT}/" /etc/apache2/ports.conf
sed -ri "s/<VirtualHost \*:[0-9]+>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf
mkdir -p "${DATA_DIR:-/data}/uploads"
chown -R www-data:www-data "${DATA_DIR:-/data}"
exec apache2-foreground
