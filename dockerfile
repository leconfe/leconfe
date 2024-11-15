FROM serversideup/php:8.1-fpm-nginx-alpine

# Switch to root so we can do root things
USER root

COPY ./entrypoint.d /etc/entrypoint.d

# Install the intl extension with root permissions
RUN install-php-extensions intl bcmath gd exif

# Drop back to our unprivileged user
USER www-data
COPY --chown=www-data:www-data ./leconfe /var/www/html/

RUN touch /var/www/html/.env
