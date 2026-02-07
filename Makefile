# --- Variables ---
PHP_BIN = php
SERVER_FILE = server.php

# --- Methods ---
.PHONY: install build run stop restart watch test check help

help:
	@echo "Usage:"
	@echo "  make install  - Install composer and npm dependencies"
	@echo "  make build    - Build frontend assets"
	@echo "  make run      - Start the Swoole server"
	@echo "  make stop     - Stop the Swoole server"
	@echo "  make restart  - Restart the Swoole server"
	@echo "  make watch    - Watch frontend changes"
	@echo "  make test     - Run PHPUnit tests"
	@echo "  make check    - Run full static analysis & quality gate (PHPStan, Lint, Rector)"


install:
	cp .env.example .env
	composer install
	npm install

build:
	npm run build

run:
	$(PHP_BIN) $(SERVER_FILE)

# Kill all PHP processes (be careful if you have other PHP projects running)
stop:
	@echo "Stopping server..."
	@killall $(PHP_BIN) || true

restart: stop run

watch:
	npm run watch

test:
	./vendor/bin/phpunit --colors=always

check:
	composer check-all