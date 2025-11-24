.PHONY: build install clean test help

# Detect OS
UNAME_S := $(shell uname -s)
ifeq ($(UNAME_S),Linux)
    EXT_SUFFIX = .so
    LIB_PREFIX = lib
endif
ifeq ($(UNAME_S),Darwin)
    EXT_SUFFIX = .dylib
    LIB_PREFIX = lib
endif

# PHP extension directory
PHP_EXT_DIR := $(shell php-config --extension-dir)
TARGET_DIR := target/release
EXTENSION_NAME := umadb_php

help: ## Show this help message
	@echo 'Usage: make [target]'
	@echo ''
	@echo 'Available targets:'
	@awk 'BEGIN {FS = ":.*?## "} /^[a-zA-Z_-]+:.*?## / {printf "  %-15s %s\n", $$1, $$2}' $(MAKEFILE_LIST)

build: ## Build the extension in release mode
	@echo "Building UmaDB PHP extension..."
	cargo build --release
	@echo "Build complete: $(TARGET_DIR)/$(LIB_PREFIX)$(EXTENSION_NAME)$(EXT_SUFFIX)"

build-dev: ## Build the extension in debug mode
	@echo "Building UmaDB PHP extension (debug)..."
	cargo build
	@echo "Build complete: target/debug/$(LIB_PREFIX)$(EXTENSION_NAME)$(EXT_SUFFIX)"

install: build ## Install the extension to PHP extension directory (requires sudo)
	@echo "Installing extension to $(PHP_EXT_DIR)..."
	sudo cp $(TARGET_DIR)/$(LIB_PREFIX)$(EXTENSION_NAME)$(EXT_SUFFIX) $(PHP_EXT_DIR)/$(EXTENSION_NAME).so
	@echo "Extension installed!"
	@echo ""
	@echo "Add the following line to your php.ini:"
	@echo "extension=$(EXTENSION_NAME).so"

clean: ## Clean build artifacts
	cargo clean
	rm -rf vendor/
	rm -f composer.lock

test: ## Run PHP tests
	@echo "Running PHP tests..."
	vendor/bin/phpunit tests/

test-rust: ## Run Rust tests
	cargo test

check: ## Run cargo check
	cargo check

clippy: ## Run clippy linter
	cargo clippy --all-targets --all-features -- -D warnings

fmt: ## Format Rust code
	cargo fmt

fmt-check: ## Check Rust code formatting
	cargo fmt -- --check

composer-install: ## Install PHP dependencies
	composer install

all: build composer-install ## Build everything and install dependencies

ci: fmt-check clippy test-rust ## Run CI checks
