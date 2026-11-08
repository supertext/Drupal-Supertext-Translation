# Drupal demo site with TMGMT and the Supertext AI translator (tmgmt_supertext_ai).
# The module itself is released on drupal.org (maintained by MD Systems); this image
# installs it with Composer like any Drupal site would.
# Build context is the REPOSITORY ROOT (Railway: Dockerfile path `demo/Dockerfile`):
#
#   docker build -f demo/Dockerfile -t drupal-supertext-demo .
#
# Runtime variables: DATABASE_URL (or MYSQL* parts), DEMO_ADMIN_*, DEMO_EDITOR_*,
# SUPERTEXT_API_KEY, SUPERTEXT_ENVIRONMENT — see docs/DEVELOPER.md.

FROM php:8.3-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends libicu-dev libzip-dev libpng-dev libjpeg62-turbo-dev libwebp-dev libfreetype6-dev unzip git mariadb-client \
    && docker-php-ext-configure gd --with-jpeg --with-webp --with-freetype \
    && docker-php-ext-install -j"$(nproc)" intl zip gd pdo_mysql opcache \
    && (a2dismod -f mpm_event mpm_worker || true) && a2enmod mpm_prefork rewrite headers \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY demo/docker/php.ini /usr/local/etc/php/conf.d/drupal-demo.ini

WORKDIR /opt/drupal
ENV COMPOSER_ALLOW_SUPERUSER=1 COMPOSER_MEMORY_LIMIT=-1

# Drupal core, Drush, TMGMT and the Supertext translator, versions fixed in
# demo/composer.json (update with `composer update` and commit the lock file).
COPY demo/composer.json demo/composer.lock* ./
RUN composer install --no-dev --no-interaction --no-progress --optimize-autoloader \
    && composer clear-cache \
    && mkdir -p web/sites/default/files /opt/drupal/private config/sync

COPY demo/docker/settings.php web/sites/default/settings.php
COPY demo/docker/setup.php /opt/drupal/demo/setup.php
COPY demo/docker/apache.conf /etc/apache2/sites-available/000-default.conf
COPY demo/docker/entrypoint.sh /usr/local/bin/demo-entrypoint
# Only $PORT (apache.conf): the default "Listen 80" would clash when several demos share
# a host network.
# Railway's health check: a static file, answered without booting Drupal.
RUN echo ok > web/healthz \
    && : > /etc/apache2/ports.conf \
    && chmod +x /usr/local/bin/demo-entrypoint \
    && chown -R www-data:www-data web/sites/default/files /opt/drupal/private

ENV PORT=8080 PATH=/opt/drupal/vendor/bin:$PATH
EXPOSE 8080
CMD ["demo-entrypoint"]
