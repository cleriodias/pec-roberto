#!/bin/bash
set -euo pipefail

echo "[custom-start] iniciando script custom"

APP_PATH="/home/site/wwwroot"
PORT="${PORT:-8080}"
CUSTOM_SRC="/home/site/wwwroot/nginx.conf"
ALT_CUSTOM_SRC="/home/site/nginx.conf"
NGINX_DEFAULT="/etc/nginx/sites-available/default"
NGINX_ENABLED="/etc/nginx/sites-enabled/default"
STORAGE_PATH="${APP_STORAGE:-$APP_PATH/storage}"

echo "[custom-start] gerando script oryx em /opt/startup/startup.sh (porta ${PORT})"
/opt/oryx/oryx create-script -appPath "$APP_PATH" -output /opt/startup/startup.sh -bindPort "$PORT" -startupCommand 'php-fpm;'

if [ -f "$CUSTOM_SRC" ]; then
  echo "[custom-start] aplicando nginx custom de $CUSTOM_SRC para $NGINX_DEFAULT e $NGINX_ENABLED"
  cp "$CUSTOM_SRC" "$NGINX_DEFAULT"
  cp "$CUSTOM_SRC" "$NGINX_ENABLED"
elif [ -f "$ALT_CUSTOM_SRC" ]; then
  echo "[custom-start] aplicando nginx custom de $ALT_CUSTOM_SRC para $NGINX_DEFAULT e $NGINX_ENABLED"
  cp "$ALT_CUSTOM_SRC" "$NGINX_DEFAULT"
  cp "$ALT_CUSTOM_SRC" "$NGINX_ENABLED"
else
  echo "[custom-start] nginx.conf custom nao encontrado (consultados $CUSTOM_SRC e $ALT_CUSTOM_SRC)"
fi

echo "[custom-start] limpando caches da aplicacao"
rm -f "$APP_PATH/public/hot"

if [ -d "$APP_PATH/bootstrap/cache" ]; then
  find "$APP_PATH/bootstrap/cache" -maxdepth 1 -type f \
    \( -name 'config.php' -o -name 'events.php' -o -name 'packages.php' -o -name 'services.php' -o -name 'routes.php' -o -name 'routes-*.php' \) \
    -print -delete || true
fi

for cache_dir in "$STORAGE_PATH/framework/cache/data" "$STORAGE_PATH/framework/views"; do
  if [ -d "$cache_dir" ]; then
    find "$cache_dir" -mindepth 1 -maxdepth 1 -exec rm -rf {} +
  fi
done

if [ -f "$APP_PATH/artisan" ]; then
  echo "[custom-start] executando php artisan optimize:clear"
  php "$APP_PATH/artisan" optimize:clear || echo "[custom-start] aviso: optimize:clear falhou, seguindo inicializacao"
fi

echo "[custom-start] recarregando nginx"
service nginx reload

echo "[custom-start] iniciando /opt/startup/startup.sh"
exec /opt/startup/startup.sh
