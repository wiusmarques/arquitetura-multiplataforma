# Build multi-stage. O BFF não tem banco nem Kafka — só HTTP.
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json ./
# O composer 2.9+ recusa pacotes com security advisory aberto; laravel 11 e
# firebase/php-jwt estão com advisories SEM fix lançado, então o bloqueio é
# desativado no laboratório (consciente — em produção, política de audit).
RUN composer config --global policy.advisories.block false \
 && composer install --no-dev --no-interaction --prefer-dist --no-scripts --no-autoloader
COPY . .
RUN composer dump-autoload --optimize --no-dev

FROM php:8.3-cli-alpine
WORKDIR /app
COPY --from=vendor /app /app
RUN mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views \
    storage/logs bootstrap/cache \
 && addgroup -S app && adduser -S app -G app && chown -R app:app /app
USER app
EXPOSE 8000
HEALTHCHECK --interval=15s --timeout=5s --retries=5 --start-period=30s \
    CMD wget -q --spider http://127.0.0.1:8000/up || exit 1
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
