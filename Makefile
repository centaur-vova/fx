.PHONY: install build run stop help

help:
	@echo "Usage:"
	@echo "  make install  - Install composer and npm dependencies"
	@echo "  make build    - Build frontend assets"
	@echo "  make run      - Start the Swoole server"
	@echo "  make watch    - Watch frontend changes"

install:
	composer install
	npm install

build:
	npm run build

run:
	php server.php

watch:
	npm run watch
