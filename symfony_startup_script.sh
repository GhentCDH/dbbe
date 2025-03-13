#!/bin/bash

# This script performs the following tasks:
# Checks if the vendor directory exists (dependencies already installed). If not, then:
# Installs missing dependencies via composer and pnpm.
# Starts the application.
if [ ! -d "./vendor" ]; then

    # create the elastic search index
    php bin/console app:elasticsearch:index
fi

composer install
corepack prepare pnpm@10.0.0 --activate
corepack enable
corepack use pnpm@10.0.0
pnpm install

symfony server:start --no-tls --allow-http --allow-all-ip &
pnpm dev

sleep infinity
