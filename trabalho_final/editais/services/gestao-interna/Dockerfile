# Build multi-stage — mesmo padrão do proposta-service.
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json ./
# O composer 2.9+ recusa pacotes com security advisory aberto; laravel 11 e
# firebase/php-jwt estão com advisories SEM fix lançado, então o bloqueio é
# desativado no laboratório (consciente — em produção, política de audit).
RUN composer config --global policy.advisories.block false \
 && composer install --no-dev --no-interaction --prefer-dist --no-scripts \
    --no-autoloader --ignore-platform-req=ext-rdkafka --ignore-platform-req=ext-pdo_pgsql
COPY . .
RUN composer dump-autoload --optimize --no-dev \
    --ignore-platform-req=ext-rdkafka --ignore-platform-req=ext-pdo_pgsql

FROM php:8.3-cli-alpine
RUN apk add --no-cache librdkafka libpq \
 && apk add --no-cache --virtual .build librdkafka-dev libpq-dev $PHPIZE_DEPS \
 && docker-php-ext-install pdo_pgsql pcntl \
 && pecl install rdkafka \
 && docker-php-ext-enable rdkafka \
 && apk del .build
WORKDIR /app
COPY --from=vendor /app /app
RUN mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views \
    storage/logs bootstrap/cache \
 && addgroup -S app && adduser -S app -G app && chown -R app:app /app
USER app
EXPOSE 8080
HEALTHCHECK --interval=15s --timeout=5s --retries=5 --start-period=30s \
    CMD wget -q --spider http://127.0.0.1:8080/up || exit 1
CMD ["sh", "-c", "php artisan migrate --force && php artisan serve --host=0.0.0.0 --port=8080"]
