.PHONY: build up down restart logs migrate test test-coverage smoke sanity checksums verify-checksums lint clean

# Build all Docker images
build:
	docker compose build

# Start all services
up:
	docker compose up -d

# Stop all services
down:
	docker compose down

# Restart all services
restart: down up

# View logs
logs:
	docker compose logs -f

# Run database migrations
migrate:
	docker compose exec backend php bin/migrate.php

# Run unit tests
test:
	docker compose exec backend vendor/bin/phpunit --testsuite Unit

# Run tests with coverage
test-coverage:
	docker compose exec backend vendor/bin/phpunit --testsuite Unit --coverage-html coverage/html --coverage-clover coverage/clover.xml

# Run smoke tests
smoke:
	bash scripts/smoke-test.sh

# Run sanity tests
sanity:
	bash scripts/sanity-test.sh

# Run all tests
test-all: test smoke sanity

# Generate SHA-256 checksums
checksums:
	bash scripts/generate-checksums.sh

# Verify checksums
verify-checksums:
	bash scripts/verify-checksums.sh

# Run static analysis
lint:
	docker compose exec backend vendor/bin/phpstan analyse src --level=8
	docker compose exec backend vendor/bin/phpcs --standard=PSR12 src/

# Clean up
clean:
	docker compose down -v
	rm -rf backend/vendor frontend/vendor
	rm -rf backend/coverage
	rm -rf backend/.phpunit.cache

# Full setup from scratch
setup: build up
	@echo "Waiting for services to be ready..."
	@sleep 10
	$(MAKE) migrate
	@echo "Setup complete. Frontend: http://localhost:8081, Backend: http://localhost:8080"
