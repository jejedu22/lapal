# Lapal

Application Symfony 4.4 de prise de commandes de pain : les boulangers et
leurs pains sont gérés par les administrateurs, les jours de distribution
sont planifiés, et chaque membre passe sa commande en ligne.

## Fonctionnalités

- gestion des boulangers et des pains proposés ;
- jours de distribution et commandes associées ;
- comptes utilisateurs (inscription, connexion, gestion des droits) ;
- paramètres de l'application (nom, logo…).

## Installation

Le déploiement recommandé utilise Docker derrière un reverse proxy Traefik :
voir [DOCKER.md](DOCKER.md).

En développement, sans Docker (PHP 7.4, Composer, MySQL/MariaDB) :

```bash
composer install
# créer .env.local avec au minimum DATABASE_URL et APP_SECRET
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate
symfony serve   # ou : php -S 127.0.0.1:8000 -t public
```

Le fichier `.env` versionné ne contient que des valeurs par défaut de
développement : ne jamais y mettre de secret de production.

## Contribuer

Voir [CONTRIBUTING.md](CONTRIBUTING.md). Pour signaler une faille de sécurité : [SECURITY.md](SECURITY.md).

## Licence

Copyright © Jérôme Sourdin et contributeurs.

Ce programme est un logiciel libre : vous pouvez le redistribuer et/ou le modifier selon les termes de la [GNU General Public License](LICENSE) telle que publiée par la Free Software Foundation, version 3 de la licence ou (à votre choix) toute version ultérieure.

Il est distribué dans l'espoir qu'il sera utile, mais **sans aucune garantie** ; voir le fichier [LICENSE](LICENSE) pour plus de détails.
