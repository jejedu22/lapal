# syntax=docker/dockerfile:1

# Symfony 7.4 LTS : PHP 8.2 minimum (les tests tournent en PHP 8.3)
ARG PHP_VERSION=8.3

########################################
# Image de base : PHP + Apache + extensions
########################################
FROM php:${PHP_VERSION}-apache AS base

COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/

RUN set -eux; \
    install-php-extensions pdo_mysql intl opcache zip; \
    apt-get update; \
    apt-get install -y --no-install-recommends locales; \
    # setlocale(LC_TIME, 'fr_FR.UTF8', ...) est utilisé dans App\Entity\JourDistrib
    sed -i 's/^# *\(fr_FR.UTF-8\)/\1/' /etc/locale.gen; \
    locale-gen; \
    rm -rf /var/lib/apt/lists/*; \
    a2enmod rewrite headers

COPY docker/php/php.ini $PHP_INI_DIR/conf.d/zz-lapal.ini
COPY docker/apache/vhost.conf /etc/apache2/sites-available/000-default.conf
COPY docker/apache/mpm_prefork.conf /etc/apache2/mods-available/mpm_prefork.conf

WORKDIR /var/www/html

########################################
# Installation des dépendances Composer
########################################
FROM base AS vendor

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

ENV COMPOSER_ALLOW_SUPERUSER=1

COPY composer.json composer.lock symfony.lock ./

# Plugins autorisés (symfony/flex, symfony/runtime : ce dernier génère vendor/autoload_runtime.php) ;
# les "auto-scripts" (cache, assets) sont joués au démarrage par l'entrypoint.
RUN composer install \
        --no-dev --no-scripts --no-progress --no-interaction \
        --prefer-dist --optimize-autoloader

COPY . .
RUN composer dump-autoload --no-dev --optimize --classmap-authoritative --no-scripts

########################################
# Image finale
########################################
FROM base AS prod

ENV APP_ENV=prod \
    APP_DEBUG=0

COPY --from=vendor --chown=www-data:www-data /var/www/html /var/www/html
COPY --chmod=755 docker/entrypoint.sh /usr/local/bin/lapal-entrypoint

# Dossiers écrits par l'application (montés en volumes dans le compose)
RUN mkdir -p var/cache var/log var/sessions public/uploads/logo \
    && chown -R www-data:www-data var public/uploads

EXPOSE 80

HEALTHCHECK --interval=30s --timeout=5s --start-period=30s --retries=3 \
    CMD curl -fsS -o /dev/null http://localhost/login || exit 1

ENTRYPOINT ["lapal-entrypoint"]
CMD ["apache2-foreground"]
