#!/bin/bash
set -euo pipefail

echo "[custom-start] iniciando script custom"

APP_PATH="/home/site/wwwroot"
PORT="${PORT:-8080}"
CUSTOM_SRC="/home/site/wwwroot/nginx.conf"
ALT_CUSTOM_SRC="/home/site/nginx.conf"
NGINX_DEFAULT="/etc/nginx/sites-available/default"
NGINX_ENABLED="/etc/nginx/sites-enabled/default"

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

echo "[custom-start] iniciando /opt/startup/startup.sh"
exec /opt/startup/startup.sh
