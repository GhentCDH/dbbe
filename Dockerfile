ARG PHP_VERSION=8.3
ARG NODE_VERSION=20
ARG NODE_PLATFORM=slim
# based on:
# - https://adambrodziak.pl/dockerfile-good-practices-for-node-and-npm
# - https://pnpm.io/docker

# ----------------------------------------------------------
# FRONTEND_BUILDER
# ----------------------------------------------------------
FROM node:${NODE_VERSION}-${NODE_PLATFORM} as frontend_builder

# Install git
RUN set -eux; \
    apt-get update -qq; \
    apt-get install -qq -y git;

ENV PNPM_HOME="/pnpm"
ENV PATH="$PNPM_HOME:$PATH"
RUN corepack prepare pnpm@10.0.0 --activate
RUN corepack enable

WORKDIR "/app"
COPY --link package.json pnpm-lock.yaml ./
COPY --link config ./config
COPY --link assets ./assets
COPY --link public ./public
COPY --link vite.config.js ./vite.config.js

COPY --link copy_build_files.sh ./copy_build_files.sh
RUN --mount=type=cache,id=pnpm,target=/pnpm/store set -eux; \
    pnpm install --frozen-lockfile; \
    cd assets/websites; \
    ../../node_modules/bower/bin/bower --allow-root install; \
    cd ../..; \
    pnpm run build;

# ----------------------------------------------------------
# BASE-DEV
# ----------------------------------------------------------
FROM webdevops/php-apache-dev:${PHP_VERSION} AS base-dev

# Install packages
RUN set -eux; \
    apt-get update -qq; \
    apt-get install -qq -y curl git apt-transport-https gnupg software-properties-common;

# Install NodeJs 20
RUN set -eux; \
    curl -sL https://deb.nodesource.com/setup_20.x | bash - ; \
    apt-get update -qq; \
    apt-get install -qq -y nodejs;

ENV PNPM_HOME="/pnpm"
ENV PATH="$PNPM_HOME:$PATH"
RUN corepack enable && corepack prepare pnpm@10.0.0 --activate

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
# php-fpm runs as user 1000:1000
# ----------------------------------------------------------
FROM webdevops/php-apache:${PHP_VERSION} AS base-prd
WORKDIR "/app"
USER application

# Create data folder with correct permissions (for images and page-images)
RUN mkdir -p /app/data

# Backend dependencies
COPY --chown=1000:1000 --link composer.json ./composer.json
COPY --chown=1000:1000 --link composer.lock ./composer.lock
RUN composer install --no-scripts
RUN composer dump-autoload

# Backend code
COPY --chown=1000:1000 --link bin ./bin
COPY --chown=1000:1000 --link config ./config
COPY --chown=1000:1000 --link src ./src
COPY --chown=1000:1000 --link templates ./templates


# Frontend: copy from frontend_builder
COPY --chown=1000:1000 --link --from=frontend_builder /app/public/build ./public/build
COPY --chown=1000:1000 --link public/index.php ./public/index.php
COPY --chown=1000:1000 --link public/.htaccess ./public/.htaccess
USER root

# ----------------------------------------------------------
# PRD
# ----------------------------------------------------------
FROM base-prd AS prd
# required for elasticsearch indexing operations
ENV PHP_MEMORY_LIMIT=1024M
ENV WEB_DOCUMENT_ROOT="/app/public"
