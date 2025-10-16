# =============================
# Stage 1: Composer (dependencias)
# =============================
FROM composer:2 AS vendor
WORKDIR /app

# Copiamos definiciones y resolvemos dependencias sin dev
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --no-scripts

# Copiamos el resto del código y optimizamos autoload
COPY . .
RUN composer dump-autoload --optimize

# =============================
# Stage 2: Runtime (PHP CLI)
# =============================
FROM php:8.3-cli

# Paquetes necesarios y extensión pdo_pgsql
RUN apt-get update \
    && apt-get install -y --no-install-recommends libpq-dev git unzip \
    && docker-php-ext-install pdo pdo_pgsql \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app

# Copiamos la app con vendor ya resuelto
COPY --from=vendor /app /app

# Variables por defecto (se pueden sobreescribir con env/compose)
ENV APP_ENV=production \
    APP_DEBUG=false \
    APP_URL=http://localhost:8081 \
    DB_CONNECTION=pgsql \
    DB_HOST=teams-db \
    DB_PORT=5432 \
    DB_DATABASE=teams \
    DB_USERNAME=teams \
    DB_PASSWORD=teams123 \
    FRONT_ORIGIN=https://basketmarcador.online

# Permisos de escritura (storage y cache)
RUN mkdir -p storage bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

EXPOSE 8081

# Comando de inicio:
# - Crea .env si no existe (desde .env.example)
# - Genera APP_KEY si falta
# - Cachea config
# - Migra la BD (idempotente)
# - Arranca el micro en :8081
CMD sh -lc "\
  [ -f .env ] || cp .env.example .env; \
  grep -q '^APP_KEY=' .env && grep -q 'APP_KEY=base64:' .env || php artisan key:generate --force; \
  php artisan config:cache; \
  php artisan migrate --force || true; \
  php -d variables_order=EGPCS artisan serve --host=0.0.0.0 --port=8081 \
"
