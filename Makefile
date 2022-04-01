##
## ----------------------------------------------------------------------------
##   MAGEPHI
## ----------------------------------------------------------------------------
##

# Disable PHP restart by Box when compiling in Github Actions
export PHP_NO_RESTART := $(shell if [ ! -z "$${GITHUB_ACTIONS}" ]; then echo "--no-restart"; else echo ""; fi)

box: ## Compiles the project into a PHAR archive
	composer dump-env prod
	box validate --verbose --ansi
	box compile --verbose --ansi $(PHP_NO_RESTART)
	box info --verbose --ansi
	rm .env.local.php
.PHONY: box

install: ## Install built version
	rm -rf ${HOME}/.magephi/cache/* ${HOME}/.magephi/logs/*
	mv -f ./build/magephi.phar /usr/local/bin/magephi
.PHONY: install

php-cs-fixer: ## Fixes code style in all PHP files
	./vendor/bin/php-cs-fixer fix --verbose
.PHONY: php-cs-fixer

phpstan: ## Executes a static analysis at the higher level on all PHP files
	./vendor/bin/phpstan analyze src --level=max --memory-limit=1G --verbose
.PHONY: phpstan

help:
	@grep -E '(^[a-zA-Z_-]+:.*?##.*$$)|(^##)' $(MAKEFILE_LIST) \
		| awk 'BEGIN {FS = ":.*?## "}; {printf "\033[32m%-30s\033[0m %s\n", $$1, $$2}' \
		| sed -e 's/\[32m##/[33m/'
.DEFAULT_GOAL := help
