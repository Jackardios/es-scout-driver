.PHONY: up down wait test unit-test integration-test coverage style-check style-fix static-analysis ci test-matrix clean help

.DEFAULT_GOAL := help

# Colors
GREEN  := \033[92m
YELLOW := \033[93m
RED    := \033[91m
CYAN   := \033[36m
RESET  := \033[0m

# MySQL config
MYSQL_VERSION ?= 8.0
MYSQL_CONTAINER_NAME := es-scout-driver-mysql
MYSQL_HOST_PORT := 23306
MYSQL_DATABASE := test
MYSQL_USER := test
MYSQL_PASSWORD := test

# Elasticsearch config
ES_VERSION ?= 9.3.0
ES_CONTAINER_NAME := es-scout-driver-elasticsearch
ES_HOST_PORT := 29200
ES_IMAGE := elasticsearch

# Supported versions for matrix testing
ES_VERSIONS := 8.19.11 9.3.0
PHP_VERSIONS := 8.1 8.2 8.3 8.4

# Extra arguments for phpunit (use: make test ARGS="--filter=testName")
ARGS ?=

##@ Docker

up: ## Start MySQL and Elasticsearch containers
	@printf "$(YELLOW)→ Starting $(MYSQL_CONTAINER_NAME) container$(RESET)\n"
	@docker run --rm -d \
		--name $(MYSQL_CONTAINER_NAME) \
		-p $(MYSQL_HOST_PORT):3306 \
		-e MYSQL_RANDOM_ROOT_PASSWORD=yes \
		-e MYSQL_DATABASE=$(MYSQL_DATABASE) \
		-e MYSQL_USER=$(MYSQL_USER) \
		-e MYSQL_PASSWORD=$(MYSQL_PASSWORD) \
		mysql:$(MYSQL_VERSION) \
		--default-authentication-plugin=mysql_native_password
	@printf "$(GREEN)✔ $(MYSQL_CONTAINER_NAME) started$(RESET)\n"
	@printf "$(YELLOW)→ Starting $(ES_CONTAINER_NAME) container (ES $(ES_VERSION))$(RESET)\n"
	@docker run --rm -d \
		--name $(ES_CONTAINER_NAME) \
		-p $(ES_HOST_PORT):9200 \
		-e discovery.type=single-node \
		-e xpack.security.enabled=false \
		-e ES_JAVA_OPTS="-Xms512m -Xmx512m" \
		$(ES_IMAGE):$(ES_VERSION)
	@printf "$(GREEN)✔ $(ES_CONTAINER_NAME) started$(RESET)\n"

down: ## Stop all containers
	@printf "$(YELLOW)→ Stopping containers$(RESET)\n"
	@-docker stop $(MYSQL_CONTAINER_NAME) 2>/dev/null || true
	@-docker stop $(ES_CONTAINER_NAME) 2>/dev/null || true
	@printf "$(GREEN)✔ Containers stopped$(RESET)\n"

wait: ## Wait until containers are ready
	@printf "$(YELLOW)→ Waiting for $(MYSQL_CONTAINER_NAME)$(RESET)\n"
	@until docker exec $(MYSQL_CONTAINER_NAME) mysqladmin -u $(MYSQL_USER) -p$(MYSQL_PASSWORD) -h 127.0.0.1 ping 2>/dev/null; do \
		printf "$(RED)✘ $(MYSQL_CONTAINER_NAME) not ready, waiting...$(RESET)\n"; \
		sleep 3; \
	done
	@printf "$(GREEN)✔ $(MYSQL_CONTAINER_NAME) ready$(RESET)\n"
	@printf "$(YELLOW)→ Waiting for $(ES_CONTAINER_NAME)$(RESET)\n"
	@until curl -fsS "127.0.0.1:$(ES_HOST_PORT)/_cluster/health?wait_for_status=yellow&timeout=60s" >/dev/null 2>&1; do \
		printf "$(RED)✘ $(ES_CONTAINER_NAME) not ready, waiting...$(RESET)\n"; \
		sleep 3; \
	done
	@printf "$(GREEN)✔ $(ES_CONTAINER_NAME) ready$(RESET)\n"

clean: ## Remove all containers and volumes
	@printf "$(YELLOW)→ Cleaning up$(RESET)\n"
	@-docker stop $(MYSQL_CONTAINER_NAME) 2>/dev/null || true
	@-docker stop $(ES_CONTAINER_NAME) 2>/dev/null || true
	@-docker rm -f $(MYSQL_CONTAINER_NAME) 2>/dev/null || true
	@-docker rm -f $(ES_CONTAINER_NAME) 2>/dev/null || true
	@printf "$(GREEN)✔ Cleanup complete$(RESET)\n"

##@ Testing

test: ## Run all tests (ARGS="--filter=testName")
	@printf "$(YELLOW)→ Running all tests$(RESET)\n"
	@vendor/bin/phpunit --testdox --colors=always $(ARGS)
	@printf "$(GREEN)✔ Tests completed$(RESET)\n"

unit-test: ## Run unit tests only (ARGS="--filter=testName")
	@printf "$(YELLOW)→ Running unit tests$(RESET)\n"
	@vendor/bin/phpunit --testsuite=unit --testdox --colors=always $(ARGS)
	@printf "$(GREEN)✔ Unit tests completed$(RESET)\n"

integration-test: ## Run integration tests (ARGS="--filter=testName")
	@printf "$(YELLOW)→ Running integration tests$(RESET)\n"
	@vendor/bin/phpunit --testsuite=integration --testdox --colors=always $(ARGS)
	@printf "$(GREEN)✔ Integration tests completed$(RESET)\n"

coverage: ## Run tests with coverage (ARGS="--filter=testName")
	@printf "$(YELLOW)→ Running tests with coverage$(RESET)\n"
	@XDEBUG_MODE=coverage vendor/bin/phpunit --testdox --coverage-text --colors=always $(ARGS)
	@printf "$(GREEN)✔ Coverage report generated$(RESET)\n"

test-es8: ## Run tests with Elasticsearch 8.x
	@$(MAKE) down
	@ES_VERSION=8.19.11 $(MAKE) up wait test
	@$(MAKE) down

test-es9: ## Run tests with Elasticsearch 9.x
	@$(MAKE) down
	@ES_VERSION=9.3.0 $(MAKE) up wait test
	@$(MAKE) down

test-matrix: ## Run tests on all Elasticsearch versions
	@printf "$(CYAN)════════════════════════════════════════$(RESET)\n"
	@printf "$(CYAN)  Running test matrix$(RESET)\n"
	@printf "$(CYAN)════════════════════════════════════════$(RESET)\n"
	@for es_version in $(ES_VERSIONS); do \
		printf "\n$(YELLOW)▶ Testing with Elasticsearch $$es_version$(RESET)\n"; \
		$(MAKE) down 2>/dev/null || true; \
		ES_VERSION=$$es_version $(MAKE) up wait test || exit 1; \
		$(MAKE) down; \
		printf "$(GREEN)✔ ES $$es_version passed$(RESET)\n"; \
	done
	@printf "\n$(GREEN)════════════════════════════════════════$(RESET)\n"
	@printf "$(GREEN)  All matrix tests passed!$(RESET)\n"
	@printf "$(GREEN)════════════════════════════════════════$(RESET)\n"

##@ Code Quality

style-check: ## Check code style (dry-run)
	@printf "$(YELLOW)→ Checking code style$(RESET)\n"
	@vendor/bin/php-cs-fixer fix --dry-run --diff --verbose
	@printf "$(GREEN)✔ Code style OK$(RESET)\n"

style-fix: ## Fix code style
	@printf "$(YELLOW)→ Fixing code style$(RESET)\n"
	@vendor/bin/php-cs-fixer fix --verbose
	@printf "$(GREEN)✔ Code style fixed$(RESET)\n"

static-analysis: ## Run PHPStan static analysis
	@printf "$(YELLOW)→ Running static analysis$(RESET)\n"
	@vendor/bin/phpstan analyse --memory-limit=512M
	@printf "$(GREEN)✔ Static analysis passed$(RESET)\n"

##@ CI

ci: style-check static-analysis unit-test ## Run all CI checks (no Docker required)
	@printf "$(GREEN)════════════════════════════════════════$(RESET)\n"
	@printf "$(GREEN)  All CI checks passed!$(RESET)\n"
	@printf "$(GREEN)════════════════════════════════════════$(RESET)\n"

ci-full: style-check static-analysis test-matrix ## Run full CI with all ES versions
	@printf "$(GREEN)════════════════════════════════════════$(RESET)\n"
	@printf "$(GREEN)  Full CI passed!$(RESET)\n"
	@printf "$(GREEN)════════════════════════════════════════$(RESET)\n"

##@ Development

install: ## Install dependencies
	@composer install

update: ## Update dependencies
	@composer update

##@ Help

help: ## Show this help
	@awk 'BEGIN {FS = ":.*##"; printf "\n$(CYAN)Usage:$(RESET)\n  make $(YELLOW)<target>$(RESET)\n"} /^[a-zA-Z_-]+:.*?##/ { printf "  $(YELLOW)%-18s$(RESET) %s\n", $$1, $$2 } /^##@/ { printf "\n$(CYAN)%s$(RESET)\n", substr($$0, 5) }' $(MAKEFILE_LIST)
