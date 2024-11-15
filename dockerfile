############################################
# Base Image
############################################

# Learn more about the Server Side Up PHP Docker Images at:
# https://serversideup.net/open-source/docker-php/
FROM serversideup/php:8.1-fpm-nginx-alpine as base

# Switch to root so we can do root things
USER root

COPY ./.dockerdata/entrypoint.d /etc/entrypoint.d

# Install the intl extension with root permissions
RUN install-php-extensions intl bcmath gd exif


############################################
# Production Image
############################################
FROM base as release
COPY --chown=www-data:www-data . /var/www/html

ENV SSL_MODE=mixed

USER www-data

RUN touch /var/www/html/.env
