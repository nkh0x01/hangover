# Marketplace (ქართული წარმოება) — developer commands
#
# Scoped to the marketplace stack only. Does NOT interfere with the scooter
# platform's docker-compose (different project name and ports).

SHELL := /bin/bash
COMPOSE_FILE ?= docker-compose.yml
COMPOSE ?= docker compose -p marketplace -f $(COMPOSE_FILE)
BE      ?= $(COMPOSE) exec api
ARTISAN ?= $(BE) php artisan

.DEFAULT_GOAL := help

.PHONY: help up down restart logs ps shell install fresh migrate seed \
        pint stan test test-coverage horizon scheduler queue cache-clear \
        scout-import npm-install npm-build

help: ## Show this help
	@awk 'BEGIN {FS = ":.*##"; printf "\nUsage:\n  make \033[36m<target>\033[0m\n\nTargets:\n"} \
		/^[a-zA-Z_-]+:.*?##/ { printf "  \033[36m%-20s\033[0m %s\n", $$1, $$2 }' $(MAKEFILE_LIST)

# -------------------- Docker --------------------

up: ## Bring up the marketplace stack
	$(COMPOSE) up -d --build

down: ## Stop the marketplace stack
	$(COMPOSE) down

restart: ## Restart all services
	$(COMPOSE) restart

logs: ## Tail all service logs
	$(COMPOSE) logs -f --tail=200

ps: ## Show running services
	$(COMPOSE) ps

shell: ## Open a shell in the api container
	$(BE) bash

# -------------------- Backend (Laravel) --------------------

install: ## First-time setup: deps, key, migrate, seed, scout import
	$(BE) composer install --no-interaction --prefer-dist
	$(ARTISAN) key:generate --force
	$(ARTISAN) storage:link
	$(ARTISAN) migrate --force
	$(ARTISAN) db:seed --force
	$(ARTISAN) horizon:install || true
	$(ARTISAN) scout:import "App\\Modules\\Catalog\\Models\\Product" || true
	$(BE) npm install
	$(BE) npm run build
	@echo "Marketplace ready: http://localhost:8001"

fresh: ## Drop, re-migrate and re-seed (destructive!)
	$(ARTISAN) migrate:fresh --seed --force
	$(ARTISAN) scout:flush "App\\Modules\\Catalog\\Models\\Product" || true
	$(ARTISAN) scout:import "App\\Modules\\Catalog\\Models\\Product" || true

migrate: ## Run pending migrations
	$(ARTISAN) migrate --force

seed: ## Run database seeders
	$(ARTISAN) db:seed --force

cache-clear: ## Clear all Laravel caches
	$(ARTISAN) optimize:clear

scout-import: ## Re-index products into Meilisearch
	$(ARTISAN) scout:flush "App\\Modules\\Catalog\\Models\\Product"
	$(ARTISAN) scout:import "App\\Modules\\Catalog\\Models\\Product"

horizon: ## Run Horizon in the foreground
	$(ARTISAN) horizon

scheduler: ## Run the scheduler in the foreground
	$(ARTISAN) schedule:work

queue: ## Run a generic queue worker
	$(ARTISAN) queue:work --queue=default

npm-install: ## Install frontend deps
	$(BE) npm install

npm-build: ## Build frontend assets
	$(BE) npm run build

# -------------------- Quality --------------------

pint: ## Run Laravel Pint formatter
	$(BE) ./vendor/bin/pint

stan: ## Run PHPStan / Larastan
	$(BE) ./vendor/bin/phpstan analyse --memory-limit=1G

test: ## Run Pest test suite
	$(BE) ./vendor/bin/pest --colors=always

test-coverage: ## Run Pest with coverage
	$(BE) ./vendor/bin/pest --coverage --min=60
