include .env

init: docker-down-clear \
	composer-install \
	migrates

up:
	docker compose -f compose.yml -f compose.override.dev.yaml up -d
up-prod:
	docker compose -f compose.yml -f compose.override.prod.yaml up -d
restart-symfony-dev:
	docker compose -f compose.yml -f compose.override.dev.yaml up -d --force-recreate symfony
rebuild:
	docker compose down -t 0 && docker compose up --build
down:
	docker compose down -t 0 --remove-orphans
build:
	docker compose up --build -d

docker-down-clear:
	docker compose down -v --remove-orphans

composer-install:
	docker compose exec symfony composer install

db-drop:
	docker compose exec symfony bin/console doctrine:schema:drop --force --full-database

migrates:
	docker compose exec symfony bin/console doctrine:migrations:migrate --no-interaction


bash:
	docker compose exec symfony /bin/bash
sh:
	docker compose exec symfony /bin/sh

routes:
	docker compose exec api bin/console debug:router

# Testing
test:
	docker compose exec -e APP_ENV=test -e APP_DEBUG=1 symfony ./vendor/bin/phpunit

test-filter:
	docker compose exec -e APP_ENV=test -e APP_DEBUG=1 symfony ./vendor/bin/phpunit --filter $(FILTER)

test-coverage:
	docker compose exec -e APP_ENV=test -e APP_DEBUG=1 symfony ./vendor/bin/phpunit --coverage-html coverage

# Code quality commands
rector:
	docker compose exec symfony ./vendor/bin/rector process --dry-run --config tools/rector.php

rector-fix:
	docker compose exec symfony vendor/bin/rector --ansi

phpstan:
	@if [ "$$(git rev-parse --abbrev-ref HEAD)" = "master" ]; then \
		git diff --name-only --diff-filter=ACM HEAD~1 | grep "^api-symfony.*\.php$$" | sed 's|^api-symfony/||' | xargs -r docker compose exec -T symfony vendor/bin/phpstan analyse --memory-limit=2048M; \
	else \
		git diff --name-only --diff-filter=ACM origin/master | grep "^api-symfony.*\.php$$" | sed 's|^api-symfony/||' | xargs -r docker compose exec -T symfony vendor/bin/phpstan analyse --memory-limit=2048M; \
	fi

cs-fix:
	docker compose exec symfony ./vendor/bin/php-cs-fixer fix

cache-clear:
	docker compose exec symfony php bin/console cache:clear
	docker compose exec symfony sh -c "rm -rf var/cache/dev/* && php -r 'opcache_reset();'"
	docker compose exec symfony php bin/console cache:warmup
    #docker compose restart symfony

aphorizm:
	docker compose exec symfony php bin/console app:seed-aphorisms

# Monitoring Stack
monitoring-up:
	docker compose -f compose.yml -f .devops/monitoring/docker-compose.monitoring.yml -f .devops/monitoring/docker-compose.monitoring.traefik-dev.yml up -d

monitoring-up-prod:
	docker compose -f compose.yml -f compose.override.prod.yaml -f .devops/monitoring/docker-compose.monitoring.yml -f .devops/monitoring/docker-compose.monitoring.traefik-prod.yml up -d

monitoring-down:
	docker compose -f compose.yml -f .devops/monitoring/docker-compose.monitoring.yml down
