# syntax=docker/dockerfile:1
#
# linkVideo em produção (Coolify): nginx + PHP-FPM 8.4 numa imagem só.
# Os vídeos e capas ficam no volume montado em /var/www/html/storage/app.

############################################
# 1) CSS/JS (Vite + Tailwind)
############################################
FROM node:22-alpine AS assets
WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund

COPY vite.config.js ./
COPY resources ./resources
# O Tailwind lê as classes dos enums (app.css: @source '../../app/Enums/*.php').
COPY app/Enums ./app/Enums
RUN npm run build

############################################
# 2) Aplicação
############################################
FROM serversideup/php:8.4-fpm-nginx-v5.0.1 AS app

USER root
# gd + exif: versão leve das capas (app/Support/CoverImage.php).
RUN install-php-extensions gd exif intl

COPY --chmod=644 docker/nginx/linkvideo-media.conf /etc/nginx/server-opts.d/linkvideo-media.conf

USER www-data
WORKDIR /var/www/html

# Dependências primeiro, para aproveitar o cache entre builds.
COPY --chown=www-data:www-data composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-scripts --no-autoloader --prefer-dist

COPY --chown=www-data:www-data . .
COPY --chown=www-data:www-data --from=assets /app/public/build ./public/build

RUN composer dump-autoload --optimize --classmap-authoritative --no-dev \
    && mkdir -p storage/app/private storage/app/public \
        storage/framework/cache/data storage/framework/sessions storage/framework/views \
        storage/logs bootstrap/cache

ENV PHP_OPCACHE_ENABLE=1 \
    PHP_DATE_TIMEZONE=America/Sao_Paulo \
    # Partes do upload são de 8 MB (VIDEO_CHUNK_MB); a capa vai até 5 MB.
    PHP_UPLOAD_MAX_FILE_SIZE=32M \
    PHP_POST_MAX_SIZE=32M \
    NGINX_CLIENT_MAX_BODY_SIZE=32M \
    PHP_MAX_EXECUTION_TIME=300 \
    PHP_FPM_PM_MAX_CHILDREN=30 \
    # O vídeo sai direto pelo nginx (X-Accel-Redirect) depois que o PHP confere o acesso.
    MEDIA_X_ACCEL_MAPPING=/var/www/html/storage/app/private/=/_linkvideo_media/ \
    LOG_CHANNEL=stderr \
    # A cada deploy: migrations, storage:link e caches de config/rotas/views.
    AUTORUN_ENABLED=true \
    AUTORUN_LARAVEL_MIGRATION_TIMEOUT=120
