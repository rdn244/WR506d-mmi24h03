# Penderie

Application de gestion de penderie / dressing.

## Stack

- Symfony 7.4 (webapp) — PHP 8.5-FPM
- MariaDB 12.3 (LTS) via Doctrine ORM
- Nginx, Adminer
- Docker Compose

## Démarrage

```bash
docker compose up        # ou : make up
```

Au premier démarrage, le conteneur `php` crée `.env` à partir de `.env.example`,
installe les dépendances Composer et exécute les migrations.

| Service     | URL                    |
|-------------|------------------------|
| Application | http://localhost:8080  |
| Adminer     | http://localhost:8081 (serveur `database`) |
| MariaDB     | `localhost:3307` depuis l'hôte |

Les ports et identifiants se modifient dans `.env`.

## Commandes

| Commande       | Rôle                                          |
|----------------|-----------------------------------------------|
| `make up`      | Démarre les conteneurs                        |
| `make down`    | Arrête les conteneurs                         |
| `make install` | Installe les dépendances et migre la base     |
| `make migrate` | Exécute les migrations Doctrine               |
| `make test`    | Lance PHPUnit (base `penderie_test`)          |
| `make sh`      | Shell dans le conteneur php                   |
| `make logs`    | Logs des conteneurs                           |
| `make cc`      | Vide le cache Symfony                         |

## Git flow

- `main` : production — `develop` : intégration
- Nouvelles fonctionnalités : `feature/<nom>` depuis `develop`, fusionnées dans `develop`
- Préfixes : `feature/`, `release/`, `hotfix/`
- Commits au format conventionnel, en français (`feat:`, `fix:`, `chore:`, `docs:`…)
