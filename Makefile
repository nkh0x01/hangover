# Hangover Mobility Platform — developer commands
#
# Targets are grouped: docker / backend / mobile / quality / db.
# Run `make help` for a quick overview.

SHELL := /bin/bash
COMPOSE ?= docker compose
BE      ?= $(COMPOSE) exec api
ARTISAN ?= $(BE) php artisan

.DEFAULT_GOAL := help

.PHONY: help up down restart logs ps shell install fresh migrate seed \
        pint stan test test-coverage horizon reverb queue cache-clear \
        mobile-bootstrap mobile-gen mobile-analyze mobile-test

help: ## Show this help
	@awk 'BEGIN {FS = ":.*##"; printf "\nUsage:\n  make \033[36m<target>\033[0m\n\nTargets:\n"} \
		/^[a-zA-Z_-]+:.*?##/ { printf "  \033[36m%-20s\033[0m %s\n", $$1, $$2 }' $(MAKEFILE_LIST)

# -------------------- Docker --------------------

up: ## Bring up the local stack (detached)
	$(COMPOSE) up -d --build

down: ## Stop the stack
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

install: ## Install backend deps and run first-time setup
	$(BE) composer install --no-interaction --prefer-dist
	$(ARTISAN) key:generate --force
	$(ARTISAN) storage:link
	$(ARTISAN) migrate --force
	$(ARTISAN) db:seed --force
	$(ARTISAN) horizon:install || true
	$(ARTISAN) reverb:install || true
	@echo "Backend ready: http://localhost:8000"

fresh: ## Drop, re-migrate and re-seed (destructive!)
	$(ARTISAN) migrate:fresh --seed --force

migrate: ## Run pending migrations
	$(ARTISAN) migrate --force

seed: ## Run database seeders
	$(ARTISAN) db:seed --force

cache-clear: ## Clear all Laravel caches
	$(ARTISAN) optimize:clear

horizon: ## Run Horizon in the foreground (interactive)
	$(ARTISAN) horizon

reverb: ## Run Reverb WS server in the foreground (interactive)
	$(ARTISAN) reverb:start --debug

queue: ## Run a generic queue worker (debug only)
	$(ARTISAN) queue:work --queue=realtime,default,low

# -------------------- Quality --------------------

pint: ## Run Laravel Pint formatter
	$(BE) ./vendor/bin/pint

stan: ## Run PHPStan / Larastan
	$(BE) ./vendor/bin/phpstan analyse --memory-limit=1G

test: ## Run Pest test suite
	$(BE) ./vendor/bin/pest --colors=always

test-coverage: ## Run Pest with coverage (xdebug or pcov required)
	$(BE) ./vendor/bin/pest --coverage --min=70

# -------------------- Mobile --------------------

mobile-bootstrap: ## melos bootstrap (resolve workspace deps)
	cd mobile && melos bootstrap

mobile-gen: ## Run codegen across all packages and apps
	cd mobile && melos run gen

mobile-analyze: ## Run flutter analyze across the workspace
	cd mobile && melos run analyze

mobile-test: ## Run flutter test across the workspace
	cd mobile && melos run test
