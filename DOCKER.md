# Lapal avec Docker (derrière Traefik)

## Architecture

```
Internet ──443──▶ traefik (dépôt jejedu22/traefik, réseau "proxy")
                      │  Host(`LAPAL_HOST`) + TLS Let's Encrypt + middleware default@file
                      ▼
                lapal-app  (PHP 7.4 + Apache, port 80, réseaux "proxy" et "lapal")
                      │
                      ▼
                lapal-db   (MariaDB 10.11, réseau "lapal" uniquement)
```

| Fichier | Rôle |
|---|---|
| `Dockerfile` | Image multi-étapes : extensions PHP, dépendances Composer (`--no-dev`), image finale Apache |
| `docker/entrypoint.sh` | Au démarrage : droits, cache Symfony, `assets:install`, migrations Doctrine |
| `docker/apache/vhost.conf` | DocumentRoot `public/`, `.htaccess` de `symfony/apache-pack` |
| `docker/php/php.ini` | Fuseau Europe/Paris, OPcache, taille d'upload |
| `docker-compose.yml` | Services `app` + `db`, volumes, labels Traefik |
| `.env.docker.example` | Variables à renseigner (copier en `.env.docker`, non commité) |

Volumes persistants : `db` (données MariaDB), `uploads` (logo), `sessions`, `logs`.

## Démarrage

Prérequis : le Traefik du dépôt `traefik` tourne (il crée le réseau externe `proxy`)
et le DNS de `LAPAL_HOST` pointe vers le serveur.

```bash
cp .env.docker.example .env.docker   # puis renseigner les valeurs
docker compose --env-file .env.docker up -d --build
docker compose --env-file .env.docker logs -f app
```

> Le fichier `.env` existant est celui de Symfony (valeurs par défaut de dev) ;
> les variables définies dans le compose sont prioritaires sur lui.

## Reprendre une base existante

```bash
docker compose --env-file .env.docker exec -T db \
  sh -c 'mariadb -u"$MARIADB_USER" -p"$MARIADB_PASSWORD" "$MARIADB_DATABASE"' < dump.sql
docker compose --env-file .env.docker restart app   # rejoue les migrations manquantes
```

Et pour le logo déjà uploadé :

```bash
docker compose --env-file .env.docker cp ./uploads/logo/. app:/var/www/html/public/uploads/logo/
docker compose --env-file .env.docker exec app chown -R www-data:www-data public/uploads
```

## Créer le premier utilisateur (base vide)

`/register` est réservé aux utilisateurs connectés, il faut donc créer le premier compte à la main :

```bash
docker compose --env-file .env.docker exec app php bin/console security:encode-password
# copier le hash obtenu, puis :
docker compose --env-file .env.docker exec db sh -c \
  'mariadb -u"$MARIADB_USER" -p"$MARIADB_PASSWORD" "$MARIADB_DATABASE" -e \
   "INSERT INTO user (username, roles, password) VALUES (\"admin\", \"[]\", \"<HASH>\")"'
```

## Remarques

- **PHP 7.4** : imposé par le `composer.lock` (Symfony 4.4.7, doctrine/orm 2.7…). Il est en fin de vie ;
  une montée en PHP 8 passe par un `composer update` (et idéalement Symfony 5.4/6.4).
- Le lock contient des plugins Composer 1 (`symfony/flex` 1.6, `ocramius/package-versions` 1.4) :
  l'image installe avec Composer 2 en `--no-plugins --ignore-platform-req=composer-plugin-api`.
- `TRUSTED_PROXIES` couvre les réseaux privés Docker pour que Symfony prenne en compte
  `X-Forwarded-Proto` envoyé par Traefik (cookies `secure`, URLs en https).
- La base n'est jamais exposée sur le réseau `proxy` ni sur un port de l'hôte.
