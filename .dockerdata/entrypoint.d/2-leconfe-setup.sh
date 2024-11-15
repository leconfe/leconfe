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