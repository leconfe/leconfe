FROM serversideup/php:8.4-fpm-nginx

ENV PHP_OPCACHE_ENABLE=1
ENV AUTORUN_ENABLED=true

COPY --chown=www-data:www-data . /var/www/html

RUN composer install --no-dev --no-interaction --optimize-autoloader && \
    npm install && npm run build && \
    php artisan storage:link

EXPOSE 80
