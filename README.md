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
docker-compose build
docker-compose --env-file .env.dev up -d
``````

The symfony_startup_script.sh automatically installs dependencies and runs an elastic search reindex process. 


## Debugging

Debugging is done using xdebug. Xdebug is an application that listens to requests coming from
your PHP application and sends debugging information about these to your IDE.

### 1. Install and configure XDebug
You can check if it's installed on your host computer by running `php -i | grep xdebug` .

You can opt to add the following configuration to your `/etc/php/php.ini` file in order to have Xdebug ready for connections every time you run a php server. This might help if you have issues getting XDebug to start properly.
```
[xdebug]
xdebug.mode=debug
xdebug.start_with_request=yes
```

### 2. Configure your IDE
- In PHPStorm, go to `Settings > PHP > Debug` and make sure xdebug is configured (default ports: 9003,9000).
- In the upper right corner, click the debugging dropdown and click "edit configurations" and add a new `PHP Remote Debug` configuration with the following settings
  - Check the "Filter debug connection by IDE key" and set the IDE key to `PHPSTORM`
  - Click the "..." next to the "server" field. Make sure the port is set to the port where your application is running and check "use path mappings". In the left hand column, select the root directory of your project. Type the path to the matching directory in your container on the right. Copy the name in the "name" field (defaults to "localhost") and run one of the following to make the serverName available in the container:
    ```
    docker exec -it dbbe-app-1 /bin/sh
    export PHP_IDE_CONFIG="serverName=localhost" 
    ```
    or make a docker-compose.override.yml (which adds variables to the default docker-copose.yml) with the following config and rerun docker-compose up -d

    ```
    services:
      app:
        environment:
          PHP_IDE_CONFIG: serverName=localhost
    ```


### 3. Configure your browser

Install "Xdebug helper" in your browser, go to its settings and set the IDE key to `PHPSTORM`. This will add the necessary cookies to your requests to be picked up by XDebug.

### 4. Start debugging

Launch the debugger in PHPStorm by clicking the green bug icon in the top right corner. Set breakpoints in your code and start a request to your application. PHPStorm should now stop at the breakpoints and you can inspect the state of your application.

## Contributing

Please see our [contributing guidelines](CONTRIBUTING.md).

## Acknowledgements

The development of the DBBE database has been funded by the The Special Research Fund of Ghent University. More details can be found on the [about the project](https://www.projectdbbe.ugent.be/about-the-project/) page.

Development in the most part done by [GhentCDH - Ghent University](https://www.ghentcdh.ugent.be/).
