.PHONY: install
install:
	docker-compose exec --workdir /var/www/html/modules/multisafepay app composer install
	docker-compose exec app php bin/console prestashop:module install multisafepay
	docker-compose exec app rm -rf /var/www/html/var/cache/dev
	docker-compose exec app rm -rf /var/www/html/var/cache/prod

.PHONY: phpcs
phpcs:
	docker-compose exec --workdir /var/www/html/modules/multisafepay app vendor/bin/phpcs -s --standard=phpcs.xml .

.PHONY: phpunit
phpunit:
	docker-compose exec --workdir /var/www/html/modules/multisafepay app vendor/bin/phpunit --testsuite prestashop-unit-tests

.PHONY: phpcbf
phpcbf:
	docker-compose exec --workdir /var/www/html/modules/multisafepay app vendor/bin/phpcbf --standard=phpcs.xml .

.PHONY: phpstan
phpstan:
	docker-compose exec -e _PS_ROOT_DIR_=./../../ --workdir /var/www/html/modules/multisafepay app vendor/bin/phpstan analyse --configuration=tests/phpstan/phpstan.neon
