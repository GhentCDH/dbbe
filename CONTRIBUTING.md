# DBBE Contributing Guide

We're really excited that you are interested in contributing to DBBE. Please take a moment to read through our [Code of Conduct](CODE_OF_CONDUCT.md) first. All contributions (participation in discussions, issues, pull requests, ...) are welcome. Unfortunately, we cannot make commitments that issues will be resolved or pull requests will be merged swiftly, especially for new features.

Documentation is currently severely lacking. Please contact <https://github.ugent.be/pdpotter> to get started.

## Requirements

Docker (>= 19.03) with compose plugin

## Download code

```sh
git clone git@github.com:GhentCDH/dbbe.git
cd dbbe
```

## Create docker volumes

### Host mount

```sh
docker volume create dbbe-database
docker volume create dbbe-elasticsearch
docker volume create dbbe-keycloak-database
```

### volumes folder

```sh
mkdir volumes
mkdir volumes/database
mkdir volumes/elasticsearch
mkdir volumes/keycloak-database
docker volume create --driver local --opt type=none --opt device=$PWD/volumes/database --opt o=bind dbbe-database
docker volume create --driver local --opt type=none --opt device=$PWD/volumes/elasticsearch --opt o=bind dbbe-elasticsearch
docker volume create --driver local --opt type=none --opt device=$PWD/volumes/keycloak-database --opt o=bind dbbe-keycloak-database
```

## Configure

Create a `.env.dev.secret` file with following contents:

```text
SITEKEY=<recaptcha_sitekey>
SECRETKEY=<recaptcha_secretkey>
```

## Install dependencies

Uncomment the line below `First time: install dependencies` in `compose.dev.yaml` and comment the line below that one.

## Start development environment

```sh
docker compose --env-file .env.dev --env-file .env.dev.secret -f compose.dev.yaml up
```

## Add data

A database dump can be downloaded from <https://doi.org/10.5281/zenodo.7682523>.

Alternatively, if using a data dump from the production server, use `tar xvzf file.sql.tar.gz` to extract the sql file and `sed -i 's/db_dbbe_prod/db_dbbe/g' file.sql` to change the owner of schemas and tables.

The sql file can be imported in the dev database using `psql -h 127.0.0.1 -p 15432 db_dbbe -U db_dbbe < file.sql`.

## Index search pages

```sh
root@...:/app# php bin/console app:elasticsearch:index
```

## Run the front-end in dev mode

```sh
root@...:/app# pnpm encore dev --watch
```

## Build the front-end in production mode

```sh
root@...:/app# pnpm encore production
```
