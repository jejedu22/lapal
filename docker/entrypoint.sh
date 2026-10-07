#!/bin/sh
set -e

console() {
    runuser -u www-data -- php bin/console --no-interaction "$@"
}

if [ "$1" = "apache2-foreground" ]; then
    # Les volumes peuvent arriver avec un propriétaire différent
    mkdir -p var/cache var/log var/sessions public/uploads/logo
    chown -R www-data:www-data var public/uploads

    console cache:clear --no-warmup
    console cache:warmup
    console assets:install public

    if [ "${RUN_MIGRATIONS:-1}" = "1" ]; then
        # Base créée avant Symfony 7.4 : reprise de l'historique des migrations (ne fait rien ensuite)
        console app:migrations:reprise
        console doctrine:migrations:migrate --allow-no-migration
    fi
fi

exec docker-php-entrypoint "$@"
