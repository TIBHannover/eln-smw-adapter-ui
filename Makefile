-include .env
export

ifeq (,$(wildcard ./build/))
    $(shell git submodule update --init --remote)
endif

EXTENSION      = ELNSMWAdapterUI

# docker images
MW_VERSION    ?= 1.43
PHP_VERSION   ?= 8.3
DB_TYPE       ?= mysql
DB_IMAGE      ?= "mysql:8"

# composer
COMPOSER_EXT  ?= true

include build/Makefile

.PHONY: composer-phan
composer-phan: .init ## Run Phan static analysis
	$(compose-exec-wiki) bash -c "cd $(EXTENSION_FOLDER) && composer phan $(COMPOSER_PARAMS)"

.PHONY: composer-phan-update-baseline
composer-phan-update-baseline: .init ## Re-generate baseline for the current MW_VERSION and fix indentation for PHPCS
	-$(compose-exec-wiki) bash -c "cd $(EXTENSION_FOLDER) && composer phan -- --save-baseline=.phan/baseline-$(MW_VERSION).php"
	$(compose) cp wiki:$(EXTENSION_FOLDER)/.phan/baseline-$(MW_VERSION).php /tmp/baseline-$(MW_VERSION).php
	unexpand --first-only -t 4 /tmp/baseline-$(MW_VERSION).php > .phan/baseline-$(MW_VERSION).php && rm /tmp/baseline-$(MW_VERSION).php
