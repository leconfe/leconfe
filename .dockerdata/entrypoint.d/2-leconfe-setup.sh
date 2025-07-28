#!/bin/sh
script_name="leconfe-setup"


echo '
-------------------------------------
▗▖   ▗▄▄▄▖ ▗▄▄▖ ▗▄▖ ▗▖  ▗▖▗▄▄▄▖▗▄▄▄▖
▐▌   ▐▌   ▐▌   ▐▌ ▐▌▐▛▚▖▐▌▐▌   ▐▌   
▐▌   ▐▛▀▀▘▐▌   ▐▌ ▐▌▐▌ ▝▜▌▐▛▀▀▘▐▛▀▀▘
▐▙▄▄▖▐▙▄▄▖▝▚▄▄▖▝▚▄▞▘▐▌  ▐▌▐▌   ▐▙▄▄▖
-------------------------------------'

echo "Relinking public storage..."
php "$APP_BASE_DIR/artisan" leconfe:relink

echo "Optimizing leconfe..."
php "$APP_BASE_DIR/artisan" config:cache

if [ "$APP_QUICK_SETUP" = "true" ]; then
	echo "Running Quick Setup"
	php "$APP_BASE_DIR/artisan" leconfe:quick-install --confirm
fi


PHP_OPCACHE_STATUS=$(php -r 'echo ini_get("opcache.enable");')

if [ "$PHP_OPCACHE_STATUS" = "1" ]; then
    PHP_OPCACHE_MESSAGE="✅ Enabled"
else
    PHP_OPCACHE_MESSAGE="❌ Disabled"
fi

echo '
-------------------------------------
ℹ️ Container Information
-------------------------------------'
echo "
OS:            $(. /etc/os-release; echo "${PRETTY_NAME}")
Docker user:   $(whoami)
Docker uid:    $(id -u)
Docker gid:    $(id -g)
OPcache:       $PHP_OPCACHE_MESSAGE
PHP Version:   $(php -r 'echo phpversion();')
Base Image Version: $(cat /etc/serversideup-php-version)
"

if [ "$PHP_OPCACHE_STATUS" = "0" ]; then
    echo "👉 [NOTICE]: Improve PHP performance by setting PHP_OPCACHE_ENABLE=1 (recommended for production)."
fi