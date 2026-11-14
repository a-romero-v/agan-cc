# Thin wrapper around Docker Compose and the Composer scripts.
# PHP and Composer run only inside the container.

COMPOSE = docker compose
EXEC    = $(COMPOSE) exec php

.DEFAULT_GOAL := help
.PHONY: help setup up down shell db-reset qa test behat analyse deptrac cs-fix openapi

help: ## List the available targets
	@grep -E '^[a-zA-Z_-]+:.*## ' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*## "}; {printf "  %-10s %s\n", $$1, $$2}'

setup: ## Build the image, install the dependencies, reset the dev and test databases, start the containers
	$(COMPOSE) build
	$(COMPOSE) run --rm --no-deps php composer install
	$(COMPOSE) up -d --wait php
	$(EXEC) composer app:db:reset
	$(EXEC) composer app:db:reset:test
	$(COMPOSE) up -d --wait

up: ## Start the containers
	$(COMPOSE) up -d --wait

down: ## Stop the containers
	$(COMPOSE) down

shell: ## Open a shell in the php container
	$(EXEC) sh

db-reset: ## Reset the dev and test databases and restart the worker
	$(EXEC) composer app:db:reset
	$(EXEC) composer app:db:reset:test
	$(COMPOSE) restart worker

qa: ## Run all checks (must be green before a task counts as done)
	$(EXEC) composer app:qa

test: ## Run PHPUnit
	$(EXEC) composer app:test

behat: ## Run the Behat acceptance tests
	$(EXEC) composer app:behat

analyse: ## Run PHPStan
	$(EXEC) composer app:analyse

deptrac: ## Check the architecture rules
	$(EXEC) composer app:deptrac

cs-fix: ## Fix the code style
	$(EXEC) composer app:cs:fix

openapi: ## Regenerate docs/openapi.yaml from api-contract/
	$(EXEC) composer app:openapi:build
