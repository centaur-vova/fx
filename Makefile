# --- Variables ---
PHP_BIN = php
SERVER_FILE = server.php

# --- Methods ---
.PHONY: install build run stop restart distclean watch test test-go test-go-race check help nats-sub
.PHONY: run-ws run-api run-ws-dev run-api-dev

help:
	@echo "Usage:"
	@echo "  make install      - Install composer and npm dependencies"
	@echo "  make build        - Build frontend assets"
	@echo "  make run          - Start the Swoole server"
	@echo "  make stop         - Stop the Swoole server"
	@echo "  make restart      - Restart the Swoole server"
	@echo "  make distclean    - Clean frontend build (public/dist)"
	@echo "  make watch        - Watch frontend changes"
	@echo "  make test         - Run PHPUnit tests"
	@echo "  make test-go      - Run Go tests"
	@echo "  make test-go-race - Run Go tests (race check enabled)"
	@echo "  make check        - Run full static analysis & quality gate (PHPStan, Lint, Rector)"
	@echo "  make nats-sub     - Subscribe to a NATS channel and show received messages"
	@echo "  make run-ws       - Start Go WebSocket proxy"
	@echo "  make run-ws-dev   - Start Go WebSocket proxy with pprof (development)"
	@echo "  make run-api      - Start Go API server (semaphore)"
	@echo "  make run-api-dev  - Start Go API server with pprof (development)"


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

distclean:
	rm -rf public/dist

test:
	./vendor/bin/phpunit --colors=always

test-go:
	cd go && go tool gotestsum --format testname -- -count=1 -failfast ./...

test-go-race:
	cd go && go tool gotestsum --format testname -- -count=1 -failfast -race ./...

check:
	composer check-all

nats-sub:
	@NATS_TOKEN=$$(sed -n 's/^NATS_TOKEN=//p' .env | tr -d '\r'); \
	nats sub "v1.ws.broadcast" --token="$$NATS_TOKEN" -s localhost:4222

run-ws:
	cd go && go run cmd/ws/main.go

run-ws-dev:
	cd go && go run -tags dev ./cmd/ws

run-api:
	cd go && go run cmd/api/main.go

run-api-dev:
	cd go && go run -tags dev ./cmd/api