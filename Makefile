# --- Variables ---
PHP_BIN = php
SERVER_FILE = server.php

# --- Methods ---
.PHONY: install build run stop restart watch help

help:
	@echo "Usage:"
	@echo "  make install  - Install composer and npm dependencies"
	@echo "  make build    - Build frontend assets"
	@echo "  make run      - Start the Swoole server"
	@echo "  make stop     - Stop the Swoole server"
	@echo "  make restart  - Restart the Swoole server"
	@echo "  make watch    - Watch frontend changes"

install:
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
