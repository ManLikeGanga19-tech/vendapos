.PHONY: up down build shell psql redis

# --- Docker ---
up:
	docker compose up -d

down:
	docker compose down

build:
	docker compose build

shell:
	docker compose exec app bash

psql:
	docker compose exec postgres psql -U vendapos

redis:
	docker compose exec redis redis-cli

# --- Platform app ---
platform-install:
	docker compose exec app composer install -d apps/platform

platform-migrate:
	docker compose exec app php apps/platform/artisan migrate

platform-test:
	docker compose exec app bash -c "cd apps/platform && vendor/bin/pest"

platform-lint:
	docker compose exec app bash -c "cd apps/platform && vendor/bin/pint --test"

platform-lint-fix:
	docker compose exec app bash -c "cd apps/platform && vendor/bin/pint"

platform-analyse:
	docker compose exec app bash -c "cd apps/platform && vendor/bin/phpstan analyse"

platform-artisan:
	docker compose exec app php apps/platform/artisan $(cmd)

# --- Terminal app ---
terminal-install:
	docker compose exec app composer install -d apps/pos-terminal

terminal-test:
	docker compose exec app bash -c "cd apps/pos-terminal && vendor/bin/pest"

terminal-lint:
	docker compose exec app bash -c "cd apps/pos-terminal && vendor/bin/pint --test"

# --- Core package ---
core-install:
	docker compose exec app composer install -d packages/core

core-test:
	docker compose exec app bash -c "cd packages/core && vendor/bin/pest"

# --- All ---
install: platform-install terminal-install core-install

test: core-test platform-test terminal-test

lint: platform-lint terminal-lint

analyse: platform-analyse
