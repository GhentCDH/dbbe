ARG PHP_VERSION=8.1
ARG NODE_VERSION=18
ARG NODE_PLATFORM=bookworm-slim
# based on:
# - https://adambrodziak.pl/dockerfile-good-practices-for-node-and-npm
# - https://pnpm.io/docker

# ----------------------------------------------------------
# FRONTEND_BUILDER
# ----------------------------------------------------------
FROM node:${NODE_VERSION}-${NODE_PLATFORM} as frontend_builder
ENV PNPM_HOME="/pnpm"
ENV PATH="$PNPM_HOME:$PATH"
RUN corepack enable

WORKDIR "/app"
COPY --link package.json pnpm-lock.yaml webpack.config.js ./
COPY --link config ./config
COPY --link assets ./assets
COPY --link copy_build_files.sh ./copy_build_files.sh
RUN --mount=type=cache,id=pnpm,target=/pnpm/store set -eux; \
    pnpm install --frozen-lockfile; \
    # Install website dependencies using bower
    cd assets/websites; \
    ../../node_modules/bower/bin/bower --allow-root install; \
    cd ../..; \
    pnpm encore production;

# ----------------------------------------------------------
# BASE-DEV
# ----------------------------------------------------------
FROM webdevops/php-apache-dev:${PHP_VERSION} AS base-dev

# Install packages
RUN set -eux; \
    apt-get update -qq; \
    apt-get install -qq -y curl git apt-transport-https gnupg software-properties-common;

# Install NodeJs 18
RUN set -eux; \
    curl -sL https://deb.nodesource.com/setup_18.x | bash - ; \
    apt-get update -qq; \
    apt-get install -qq -y nodejs;

ENV PNPM_HOME="/pnpm"
ENV PATH="$PNPM_HOME:$PATH"
RUN corepack enable

# Install Symfony Cli
RUN set -eux; \
    curl -1sLf 'https://dl.cloudsmith.io/public/symfony/stable/setup.deb.sh' |  bash; \
    apt-get update -qq; \
    apt-get install -qq -y symfony-cli

# https://getcomposer.org/doc/03-cli.md#composer-allow-superuser
ENV COMPOSER_ALLOW_SUPERUSER=1

# ----------------------------------------------------------
# DEV
# ----------------------------------------------------------
FROM base-dev AS dev
ENV APP_ENV=dev

WORKDIR "/app"

# ----------------------------------------------------------
# BASE-PRD
# php-fpm runs as user application:application
# ----------------------------------------------------------
FROM webdevops/php-apache:${PHP_VERSION} AS base-prd
WORKDIR "/app"
USER application

# Backend dependencies
COPY --chown=application:application --link composer.json ./composer.json
COPY --chown=application:application --link composer.lock ./composer.lock
RUN composer install --no-scripts

# Backend code
COPY --chown=application:application --link bin ./bin
COPY --chown=application:application --link config ./config
COPY --chown=application:application --link src ./src
COPY --chown=application:application --link templates ./templates
COPY --chown=application:application --link public/index.php ./public/index.php

# Frontend: copy from frontend_builder
COPY --chown=application:application --link --from=frontend_builder /app/public/build ./public/build

# ----------------------------------------------------------
# PRD
# ----------------------------------------------------------
FROM base-prd AS prd
