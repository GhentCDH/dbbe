# DBBE

This repository contains the source code for the [DBBE database](https://www.dbbe.ugent.be/).

The DBBE database consists of a Symphony back-end connected to a PostgreSQL database and Elasticsearch search engine. The search and edit pages consist of Vue.js applications.

## Getting started

First download a dbbe database dump and place it in the `docker_data/dbbe_db/initdb` folder. SQL or bash scripts in this folder are executed only the first time the container is started. To rerun the import, delete the data directory

Next run the following command to run the docker services:

* PHP Symfony
* Elasticsearch
* DBBE postgres database
* Keycloak authentication service
* Keycloak postgres database

``````
docker compose -f compose.dev.yaml --env-file .env.dev up --build
``````

Open a bash shell inside the container running php 

``````
#build the asset files to serve a css file
pnpm install --frozen-lockfile
cd assets/websites/
../../node_modules/bower/bin/bower --allow-root install
cd ../..
pnpm encore production

#build the elasticsearch indexes
php bin/console app:elasticsearch:index
``````

## Contributing

Please see our [contributing guidelines](CONTRIBUTING.md).

## Acknowledgements

The development of the DBBE database has been funded by the The Special Research Fund of Ghent University. More details can be found on the [about the project](https://www.projectdbbe.ugent.be/about-the-project/) page.

Development in the most part done by [GhentCDH - Ghent University](https://www.ghentcdh.ugent.be/).



