DC      = docker compose
PHP     = $(DC) exec php
CONSOLE = $(PHP) php bin/console

.DEFAULT_GOAL := help
.PHONY: help up down build install migrate test sh logs cc

help: ## Affiche cette aide
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-10s\033[0m %s\n", $$1, $$2}'

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

test: ## Lance la suite de tests (base penderie_test)
	$(CONSOLE) doctrine:database:create --if-not-exists --env=test
	$(CONSOLE) doctrine:migrations:migrate --no-interaction --allow-no-migration --env=test
	$(PHP) php bin/phpunit

sh: ## Ouvre un shell dans le conteneur php
	$(PHP) bash

logs: ## Affiche les logs des conteneurs
	$(DC) logs -f

cc: ## Vide le cache Symfony
	$(CONSOLE) cache:clear
