# DBBE Contributing Guide

We're really excited that you are interested in contributing to DBBE. Please take a moment to read through our [Code of Conduct](CODE_OF_CONDUCT.md) first. All contributions (participation in discussions, issues, pull requests, ...) are welcome. Unfortunately, we cannot make commitments that issues will be resolved or pull requests will be merged swiftly, especially for new features.

Documentation is currently severely lacking. Please contact <https://github.ugent.be/pdpotter> to get started.

## Requirements

Apache, PHP (8.0), Composer, PostgreSQL (12.10), Elasticsearch (7)

## Additional development requirements

Nodejs, Yarn, Bower

## Download code

```sh
vagrant@dbbe:~$ git clone git@github.ugent.be:GhentCDH/dbbe2.git
vagrant@dbbe:~$ cd dbbe2
```

## Configure

Create a .env file in the base of the newly created folder with following contents:

```text
APP_ENV=<dev,qas or prod>
APP_SECRET=output of hexdump -vn16 -e'4/4 "%08X" 1 "\n"' /dev/urandom

DATABASE_URL='postgresql://<db_user>:<db_password>@<db_host>:<db_port>/<db_name>?serverVersion=12.10'

MAILER_DSN='smtp://dbbe%40ugent.be@smtp.ugent.be:25'

ELASTIC_HOSTS='[{"host": "<elasticsearch_host>", "port": <elasticsearch_port>}]'
ELASTIC_INDEX_PREFIX='<elasticsearch_prefix>'

SITEKEY=<recaptcha_sitekey>
SECRETKEY=<recaptcha_secretkey>
```

## Install dependencies

```sh
vagrant@dbbe:~$ cd /home/vagrant/dbbe2
vagrant@dbbe:~/dbbe2$ composer install
vagrant@dbbe:~/dbbe2$ yarn install
vagrant@dbbe:~/dbbe2$ cd assets/websites
vagrant@dbbe:~/dbbe2/assets/websites$ bower install
```

## Index search pages

```sh
vagrant@dbbe:~/dbbe2$ php bin/console app:elasticsearch:index
```

## Start the back-end dev server

```sh
vagrant@dbbe:~/dbbe2$ symfony server:start --no-tls
```

## Run the front-end in dev mode

```sh
vagrant@dbbe:~/dbbe2$ yarn encore dev --watch
```

## Build the front-end in production mode

```sh
vagrant@dbbe:~/dbbe2$ yarn encore production
```
