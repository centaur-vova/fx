# --- Variables ---
PHP_BIN = php
SERVER_FILE = server.php
# Dev overrides: connect App (Swoole) to Docker NATS / API (Balancer) from host
APP_NATS_HOST = localhost
APP_NATS_PORT = 4222
APP_API_URL = http://localhost:8090

# --- Methods ---
.PHONY: install build app stop restart distclean watch test test-php test-go test-go-race check help nats-sub dev
.PHONY: lint-go
.PHONY: fix fix-php
.PHONY: logs logs-ws logs-balancer logs-api
.PHONY: swagger-api
.PHONY: if-density
.PHONY: bench

help:
	@echo "Usage:"
	@echo "  make install       - 📦 Install composer and npm dependencies"
	@echo "  make build         - 🏗️ Build frontend assets"
	@echo "  make app           - 🚀 Start Swoole PHP application"
	@echo "  make stop          - 🛑 Stop all PHP processes (host)"
	@echo "  make restart       - 🔄 Restart the Swoole server"
	@echo "  make distclean     - 🧹 Clean frontend build (public/dist)"
	@echo "  make watch         - 👀 Watch frontend changes"
	@echo "  make test          - 🧪 Run PHPUnit tests"
	@echo "  make test-go       - 🧪 Run Go tests"
	@echo "  make test-go-race  - 🏎️ Run Go tests (race check enabled)"
	@echo "  make check         - ✅ Run full static analysis & quality gate"
	@echo "  make nats-sub      - 📡 Subscribe to a NATS channel"
	@echo "  make dev           - 🐎 Start local development environment (2 API instances)"
	@echo "  make logs          - 📋 Show all dev service logs"
	@echo "  make logs-ws       - 📋 Show WebSocket proxy logs"
	@echo "  make logs-balancer - 📋 Show balancer logs"
	@echo "  make logs-api      - 📋 Show API instance logs"
	@echo "  make swagger-api   - 📋 Generate Swagger docs (API)"
	@echo "  make if-density    - 📊 Calculate PHP IRD (If/Row Density)"
	@echo "  make bench         - 🏎️ Run WebSocket benchmark (Go)"

install:
	cp .env.example .env
	composer install
	npm install

build:
	npm run build

app:
	$(PHP_BIN) $(SERVER_FILE) --api-url=$(APP_API_URL) \
		--nats-host=$(APP_NATS_HOST) --nats-port=$(APP_NATS_PORT)

# Kill all PHP processes (be careful if you have other PHP projects running)
stop:
	@echo "Stopping server..."
	@killall $(PHP_BIN) || true

restart: stop app

distclean:
	rm -rf public/dist

test: test-php test-go

test-php:
	./vendor/bin/phpunit --colors=always

test-go:
	cd go && go tool gotestsum --format testname -- -count=1 -failfast ./...

test-go-race:
	cd go && go tool gotestsum --format testname -- -count=1 -failfast -race ./...

check:
	composer check-all
	make lint-go

fix: fix-php lint-go

fix-php:
	composer fix-all

nats-sub:
	@NATS_TOKEN=$$(sed -n 's/^NATS_TOKEN=//p' .env | tr -d '\r'); \
	nats sub "v1.ws.broadcast" --token="$$NATS_TOKEN" -s localhost:4222

dev:
	docker compose -f docker-compose.dev.yaml up

# Logs
logs:
	docker compose -f docker-compose.dev.yaml logs
logs-ws:
	docker compose -f docker-compose.dev.yaml logs ws
logs-balancer:
	docker compose -f docker-compose.dev.yaml logs balancer
logs-api:
	docker compose -f docker-compose.dev.yaml logs api

# Swagger/OpenAPI
swagger-api:
	swag init -d ./go -g cmd/api/main.go -o go/cmd/api/docs

# Go linter
lint-go:
	cd go && golangci-lint run --timeout=5m

if-density:
	@php tools/if-density.php app

bench:
	cd go && go run cmd/bench/main.go