#!/bin/bash

# This script performs the following tasks:
# Checks if the vendor directory exists (dependencies already installed). If not, then:
# Installs missing dependencies via composer and pnpm.
# Starts the application.
if [ ! -d "./vendor" ]; then
    composer install
    pnpm install 
    cd assets/websites 
    ../../node_modules/bower/bin/bower --allow-root install 
    cd ../..
    pnpm dev

    # create the elastic search index
    php bin/console app:elasticsearch:index
fi

symfony server:start --no-tls --allow-http --allow-all-ip

sleep infinity
