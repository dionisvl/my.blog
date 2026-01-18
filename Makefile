include .env

init: docker-down-clear \
	composer-install \
	npm-i npx-mix \
	migrate

up-dev:
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
	docker compose exec laravel composer install

db-drop:
	docker compose exec symfony bin/console doctrine:schema:drop --force --full-database

migrate:
	docker compose exec laravel php artisan migrate

migrates:
	docker compose exec symfony bin/console doctrine:migrations:migrate --no-interaction


bash:
	docker compose exec symfony /bin/bash
sh:
	docker compose exec symfony /bin/sh

npm-i:
	cd app-laravel/api-laravel
	node npm i
npx-mix:
	cd app-laravel/api-laravel
	npx mix

# HOST=185.255.132.6 PORT=2222 BUILD_NUMBER=1 KEY=provisioning/files/deploy_rsa make deploy
deploy:
	ssh deploy@${HOST} -p ${PORT} -i ${KEY} 'rm -rf site_${BUILD_NUMBER}'
	ssh deploy@${HOST} -p ${PORT} -i ${KEY} 'mkdir site_${BUILD_NUMBER}'
	scp -P ${PORT} -i ${KEY} docker-compose.yml deploy@${HOST}:site_${BUILD_NUMBER}/docker-compose.yml
	ssh deploy@${HOST} -p ${PORT} -i ${KEY} 'cd site_${BUILD_NUMBER} && echo "COMPOSE_PROJECT_NAME=phpqa" >> .env'
	ssh deploy@${HOST} -p ${PORT} -i ${KEY} 'cd site_${BUILD_NUMBER} && docker compose up --build --remove-orphans -d'
	ssh deploy@${HOST} -p ${PORT} -i ${KEY} 'rm -f site'
	ssh deploy@${HOST} -p ${PORT} -i ${KEY} 'ln -sr site_${BUILD_NUMBER} site'

rollback:
	ssh deploy@${HOST} -p ${PORT} 'cd site_${BUILD_NUMBER} && docker compose pull'
	ssh deploy@${HOST} -p ${PORT} 'cd site_${BUILD_NUMBER} && docker compose up --build --remove-orphans -d'
	ssh deploy@${HOST} -p ${PORT} 'rm -f site'
	ssh deploy@${HOST} -p ${PORT} 'ln -sr site_${BUILD_NUMBER} site'

a:
	sudo chmod 777 -R ${APP_STORAGE_LOCATION}

routes:
	docker compose exec laravel php artisan route:list

# Testing commands
test:
	docker compose exec laravel ./vendor/bin/phpunit

test-symfony:
	docker compose exec -e APP_ENV=test -e APP_DEBUG=1 symfony ./vendor/bin/phpunit

test-filter:
	docker compose exec laravel ./vendor/bin/phpunit --filter $(FILTER)

test-coverage:
	docker compose exec laravel ./vendor/bin/phpunit --coverage-html coverage

# Code quality commands
rector:
	docker compose exec laravel ./vendor/bin/rector process --dry-run

rector-fix:
	docker compose exec laravel ./vendor/bin/rector process

phpstan:
	@if [ "$$(git rev-parse --abbrev-ref HEAD)" = "master" ]; then \
		git diff --name-only --diff-filter=ACM HEAD~1 | grep "\.php$$" | xargs -r docker compose exec -T laravel vendor/bin/phpstan analyse --memory-limit=2048M; \
	else \
		git diff --name-only --diff-filter=ACM origin/master | grep "\.php$$" | xargs -r docker compose exec -T laravel vendor/bin/phpstan analyse --memory-limit=2048M; \
	fi

cs-fix:
	docker compose exec laravel ./vendor/bin/php-cs-fixer fix

cache-clear:
	docker compose exec symfony php bin/console cache:clear
	docker compose exec symfony sh -c "rm -rf var/cache/dev/* && php -r 'opcache_reset();'"
	docker compose exec symfony php bin/console cache:warmup

aphorizm:
	docker compose exec symfony php bin/console app:seed-aphorisms
