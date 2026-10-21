# Lapal avec Docker (derrière Traefik)

## Architecture

```
Internet ──443──▶ traefik (dépôt jejedu22/traefik, réseau "proxy")
                      │  Host(`LAPAL_HOST`) + TLS Let's Encrypt + middleware default@file
                      ▼
                lapal-app  (PHP 8.4 + Apache, port 80, réseaux "proxy" et "lapal")
                      │
                      ▼
                lapal-db   (MariaDB 10.11, réseau "lapal" uniquement)
```

| Fichier | Rôle |
|---|---|
| `Dockerfile` | Image multi-étapes : extensions PHP, dépendances Composer (`--no-dev`), image finale Apache |
| `docker/entrypoint.sh` | Au démarrage : droits, cache Symfony, `assets:install`, reprise de l'historique puis migrations Doctrine |
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
docker compose --env-file .env.docker restart app   # reprend l'historique puis joue les migrations manquantes
```

Et pour le logo déjà uploadé :

```bash
docker compose --env-file .env.docker cp ./uploads/logo/. app:/var/www/html/public/uploads/logo/
docker compose --env-file .env.docker exec app chown -R www-data:www-data public/uploads
```

## Créer le premier utilisateur (base vide)

`/register` est réservé aux utilisateurs connectés, il faut donc créer le premier compte à la main :

```bash
docker compose --env-file .env.docker exec app php bin/console security:hash-password
# copier le hash obtenu, puis :
docker compose --env-file .env.docker exec db sh -c \
  'mariadb -u"$MARIADB_USER" -p"$MARIADB_PASSWORD" "$MARIADB_DATABASE" -e \
   "INSERT INTO user (username, roles, password) VALUES (\"admin\", \"[]\", \"<HASH>\")"'
```

## Passage de Symfony 4.4 à Symfony 7.4

- **Historique des migrations** : Doctrine Migrations 3 n'utilise plus la table `migration_versions`
  mais `doctrine_migration_versions`, avec le nom complet des classes. Au démarrage, l'entrypoint lance
  `app:migrations:reprise`, qui recopie l'historique puis supprime l'ancienne table ; sans cela,
  toutes les migrations seraient rejouées. La commande ne fait rien quand il n'y a plus d'ancienne table.
  Avec `RUN_MIGRATIONS=0`, la lancer à la main avant `doctrine:migrations:migrate`.
- **Sauvegarde** : faire un dump de la base avant le premier démarrage de la nouvelle image.
- `doctrine:schema:validate` signale les colonnes `commande.nom`, `commande.prenom` et `pain.nom`
  (en utf8mb4 dans des tables en utf8mb3, héritage des premières migrations). C'est sans conséquence ;
  ne pas lancer `doctrine:schema:update --force`, qui les repasserait en utf8mb3.

## Tests

```bash
composer install
php bin/phpunit
```

Les tests utilisent une base SQLite jetable (`var/test.db`, voir `.env.test`) : aucun serveur requis.

## Remarques

- **Symfony 7.4 LTS** (maintenu jusqu'en novembre 2028, correctifs de sécurité jusqu'en novembre 2029),
  Doctrine ORM 3 / DBAL 4, PHP 8.4.
- **Correctifs PHP** : l'image part du tag flottant `php:8.4-apache` et le compose force son
  téléchargement (`pull: true`) : reconstruire l'image suffit à prendre le dernier correctif de sécurité.
  La construction échoue si PHP est plus ancien que `PHP_MIN_VERSION` (8.4.27, failles critiques
  CVE-2026-109618 / CVE-2026-109619). Vérifier la version en service : `docker exec lapal-app php -v`.
- `TRUSTED_PROXIES` couvre les réseaux privés Docker pour que Symfony prenne en compte
  `X-Forwarded-Proto` envoyé par Traefik (cookies `secure`, URLs en https).
- La base n'est jamais exposée sur le réseau `proxy` ni sur un port de l'hôte.
