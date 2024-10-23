# DBBE

This repository contains the source code for the [DBBE database](https://www.dbbe.ugent.be/).

The DBBE database consists of a Symphony back-end connected to a PostgreSQL database and Elasticsearch search engine. The search and edit pages consist of Vue.js applications.

## Getting started

cp .env.dev 
docker compose -f compose.dev.yaml --env-file .env.dev up --build 


## Contributing

Please see our [contributing guidelines](CONTRIBUTING.md).

## Acknowledgements

The development of the DBBE database has been funded by the The Special Research Fund of Ghent University. More details can be found on the [about the project](https://www.projectdbbe.ugent.be/about-the-project/) page.

Development in the most part done by [GhentCDH - Ghent University](https://www.ghentcdh.ugent.be/).



