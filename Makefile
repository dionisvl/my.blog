include .env

COMPOSE_DEV=docker compose -f compose.yml -f compose.override.dev.yaml

init: docker-down-clear \
	composer-install \
	migrates

up:
	$(COMPOSE_DEV) up -d
up-prod:
	docker compose -f compose.yml -f compose.override.prod.yaml up -d
restart-symfony-dev:
	$(COMPOSE_DEV) up -d --force-recreate symfony
rebuild:
	$(COMPOSE_DEV) down -t 0 && $(COMPOSE_DEV) up --build
down:
	$(COMPOSE_DEV) down -t 0 --remove-orphans
build:
	$(COMPOSE_DEV) up --build -d

astro-build-local:
	$(COMPOSE_DEV) stop astro-frontend
	$(COMPOSE_DEV) run --rm \
		--env-from-file ./frontend-astro/.env \
		-p 4321:4321 \
		astro-frontend \
		sh -c "npm install -g corepack && corepack enable pnpm && pnpm install --frozen-lockfile && pnpm build && node dist/server/entry.mjs"

docker-down-clear:
	$(COMPOSE_DEV) down -v --remove-orphans

composer-install:
	$(COMPOSE_DEV) exec symfony composer install

db-drop:
	$(COMPOSE_DEV) exec symfony bin/console doctrine:schema:drop --force --full-database

migrates:
	$(COMPOSE_DEV) exec symfony bin/console doctrine:migrations:migrate --no-interaction


bash:
	$(COMPOSE_DEV) exec symfony /bin/bash
sh:
	$(COMPOSE_DEV) exec symfony /bin/sh

routes:
	$(COMPOSE_DEV) exec symfony bin/console debug:router

# Testing
test:
	$(COMPOSE_DEV) exec -e APP_ENV=test -e APP_DEBUG=1 -e DATABASE_URL='sqlite:///%kernel.project_dir%/var/data_test.db' symfony ./vendor/bin/phpunit -c phpunit.dist.xml

test-filter:
	$(COMPOSE_DEV) exec -e APP_ENV=test -e APP_DEBUG=1 -e DATABASE_URL='sqlite:///%kernel.project_dir%/var/data_test.db' symfony ./vendor/bin/phpunit -c phpunit.dist.xml --filter $(FILTER)

test-coverage:
	$(COMPOSE_DEV) exec -e APP_ENV=test -e APP_DEBUG=1 -e DATABASE_URL='sqlite:///%kernel.project_dir%/var/data_test.db' symfony ./vendor/bin/phpunit -c phpunit.dist.xml --coverage-html coverage

# Go API tests (run natively on host; testcontainers starts its own postgres)
test-go:
	cd api-go && GOTOOLCHAIN=auto go test ./...

# Code quality commands
rector:
	$(COMPOSE_DEV) exec symfony ./vendor/bin/rector process --dry-run --config rector.php

rector-fix:
	$(COMPOSE_DEV) exec symfony vendor/bin/rector process --config rector.php --ansi

phpstan:
	@if [ "$$(git rev-parse --abbrev-ref HEAD)" = "master" ]; then \
			git diff --name-only --diff-filter=ACM HEAD~1 | grep "^api-symfony.*\.php$$" | sed 's|^api-symfony/||' | xargs -r $(COMPOSE_DEV) exec -T symfony vendor/bin/phpstan analyse --memory-limit=2048M; \
		else \
			git diff --name-only --diff-filter=ACM origin/master | grep "^api-symfony.*\.php$$" | sed 's|^api-symfony/||' | xargs -r $(COMPOSE_DEV) exec -T symfony vendor/bin/phpstan analyse --memory-limit=2048M; \
		fi

phpstan-symfony-full:
	$(COMPOSE_DEV) exec symfony vendor/bin/phpstan analyse --memory-limit=2048M

cs-fix:
	$(COMPOSE_DEV) exec symfony ./vendor/bin/php-cs-fixer fix

cache-clear:
	$(COMPOSE_DEV) exec symfony php bin/console cache:clear
	$(COMPOSE_DEV) exec symfony sh -c "rm -rf var/cache/dev/* && php -r 'opcache_reset();'"
	$(COMPOSE_DEV) exec symfony php bin/console cache:warmup
	$(COMPOSE_DEV) restart symfony

aphorizm:
	$(COMPOSE_DEV) exec symfony php bin/console app:seed-aphorisms

# Monitoring Stack
monitoring-up:
	docker compose -f compose.yml -f .devops/monitoring/docker-compose.monitoring.yml -f .devops/monitoring/docker-compose.monitoring.traefik-dev.yml up -d

monitoring-up-prod:
	docker compose -f compose.yml -f compose.override.prod.yaml -f .devops/monitoring/docker-compose.monitoring.yml -f .devops/monitoring/docker-compose.monitoring.traefik-prod.yml up -d

monitoring-down:
	docker compose -f compose.yml -f .devops/monitoring/docker-compose.monitoring.yml down
