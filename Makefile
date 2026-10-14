DC      = docker compose
# Sans Docker (PHP installé sur la machine) : make qualite PHP=
PHP     = $(DC) exec php
CONSOLE = $(PHP) php bin/console
# Sans terminal interactif : utilisable depuis Cypress (cy.exec) et les scripts
PHP_T   = $(DC) exec -T php

.DEFAULT_GOAL := help
.PHONY: help up down build install migrate test sh logs cc phpstan lint securite qualite base-test test-unitaires test-integration test-fonctionnels couverture sauvegarde restauration fixtures e2e e2e-ouvrir tests

help: ## Affiche cette aide
	@grep -E '^[a-zA-Z0-9_-]+:.*?## .*$$' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-18s\033[0m %s\n", $$1, $$2}'

.env:
	cp .env.example .env

up: .env ## Démarre les conteneurs (build si nécessaire)
	$(DC) up -d --build --wait

down: ## Arrête les conteneurs
	$(DC) down

build: ## Reconstruit les images
	$(DC) build --pull

install: ## Installe les dépendances et migre la base
	$(PHP) composer install --no-interaction
	$(CONSOLE) doctrine:migrations:migrate --no-interaction --allow-no-migration

migrate: ## Exécute les migrations Doctrine
	$(CONSOLE) doctrine:migrations:migrate --no-interaction --allow-no-migration

base-test:
	$(PHP_T) php bin/console doctrine:database:create --if-not-exists --env=test
	$(PHP_T) php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration --env=test

test: base-test ## Lance les 3 suites PHPUnit (unitaires, intégration, fonctionnels)
	$(PHP_T) php bin/phpunit

test-unitaires: ## Tests unitaires : classes isolées, sans noyau ni base
	$(PHP_T) php bin/phpunit --testsuite unitaires

test-integration: base-test ## Tests d'intégration : vrais services et base penderie_test
	$(PHP_T) php bin/phpunit --testsuite integration

test-fonctionnels: base-test ## Tests fonctionnels : parcours HTTP complets
	$(PHP_T) php bin/phpunit --testsuite fonctionnels

couverture: base-test ## Couverture du code métier (texte, et HTML dans var/couverture/html)
	$(PHP_T) php bin/phpunit --coverage-text --coverage-html var/couverture/html

sh: ## Ouvre un shell dans le conteneur php
	$(PHP) bash

logs: ## Affiche les logs des conteneurs
	$(DC) logs -f

cc: ## Vide le cache Symfony
	$(CONSOLE) cache:clear

phpstan: ## Analyse statique PHPStan (niveau 6)
	$(CONSOLE) cache:warmup --quiet
	$(PHP) vendor/bin/phpstan analyse --memory-limit=1G

lint: ## Vérifie Twig, YAML, le conteneur Symfony et composer.json
	$(CONSOLE) lint:twig templates
	$(CONSOLE) lint:yaml config --parse-tags
	$(CONSOLE) lint:container
	$(PHP) composer validate --no-check-publish

securite: ## Cherche les vulnérabilités connues (Composer et importmap)
	$(PHP) composer audit
	$(CONSOLE) importmap:audit

qualite: lint phpstan securite ## Lance tous les contrôles de qualité

sauvegarde: ## Sauvegarde la base de développement dans var/sauvegardes
	@mkdir -p var/sauvegardes
	$(DC) exec -T database sh -c 'mariadb-dump -uroot -p"$$MARIADB_ROOT_PASSWORD" --single-transaction --routines --databases "$$MARIADB_DATABASE"' > var/sauvegardes/penderie-$$(date +%Y%m%d-%H%M%S).sql
	@echo "Sauvegarde : $$(ls -t var/sauvegardes/*.sql | head -1)"

restauration: ## Restaure la dernière sauvegarde (ou FICHIER=var/sauvegardes/…)
	$(DC) exec -T database sh -c 'mariadb -uroot -p"$$MARIADB_ROOT_PASSWORD"' < $(or $(FICHIER),$$(ls -t var/sauvegardes/*.sql | head -1))
	@echo "Base restaurée depuis $(or $(FICHIER),$$(ls -t var/sauvegardes/*.sql | head -1))"

fixtures: ## Charge les fixtures dans la base de dev (efface ses données : make sauvegarde avant)
	$(PHP_T) php bin/console doctrine:fixtures:load --no-interaction

e2e: ## Tests Cypress sur localhost:8080 ; la base de dev est sauvegardée puis restaurée
	@$(MAKE) --no-print-directory sauvegarde
	@npx cypress run; statut=$$?; $(MAKE) --no-print-directory restauration; exit $$statut

e2e-ouvrir: ## Ouvre Cypress pour la démonstration (base sauvegardée puis restaurée)
	@$(MAKE) --no-print-directory sauvegarde
	@npx cypress open --e2e --browser electron; $(MAKE) --no-print-directory restauration

tests: qualite test e2e ## Toute la campagne : qualité, PHPUnit et Cypress
