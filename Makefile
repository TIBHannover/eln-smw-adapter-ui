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
