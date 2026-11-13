# Thin wrapper around Docker Compose and the Composer scripts.
# PHP and Composer run only inside the container.

COMPOSE = docker compose
EXEC    = $(COMPOSE) exec php

.DEFAULT_GOAL := help
.PHONY: help setup up down shell qa test analyse deptrac cs-fix

help: ## List the available targets
	@grep -E '^[a-zA-Z_-]+:.*## ' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*## "}; {printf "  %-10s %s\n", $$1, $$2}'

setup: ## Build the image, start the containers and install the dependencies
	$(COMPOSE) build
	$(COMPOSE) up -d --wait
	$(EXEC) composer install

up: ## Start the containers
	$(COMPOSE) up -d --wait

down: ## Stop the containers
	$(COMPOSE) down

shell: ## Open a shell in the php container
	$(EXEC) sh

qa: ## Run all checks (must be green before a task counts as done)
	$(EXEC) composer app:qa

test: ## Run PHPUnit
	$(EXEC) composer app:test

analyse: ## Run PHPStan
	$(EXEC) composer app:analyse

deptrac: ## Check the architecture rules
	$(EXEC) composer app:deptrac

cs-fix: ## Fix the code style
	$(EXEC) composer app:cs:fix
