# DBBE

A Vue / Symfony / Elasticsearch project.

## [Set up a development environment](https://github.ugent.be/GhentCDH/Documentation/blob/main/DevelopmentEnvironment.md)

## Install the requirements for this project in the Vagrant Virtual Machine

```sh
$ vagrant ssh
vagrant@dbbe:~$ cd /home/vagrant/install
vagrant@dbbe:~/install$ sudo ./php7.4-fpm.sh
vagrant@dbbe:~/install$ sudo ./postgres12.sh
vagrant@dbbe:~/install$ sudo ./elasticsearch7.sh
vagrant@dbbe:~/install$ sudo ./nodejs.sh
vagrant@dbbe:~/install$ sudo npm install -g yarn
vagrant@dbbe:~/install$ sudo npm install -g bower
vagrant@dbbe:~/install$ cd

vagrant@dbbe:~$ wget -O composer-setup.php https://getcomposer.org/installer
vagrant@dbbe:~$ sudo php composer-setup.php --install-dir=/usr/local/bin --filename=composer
vagrant@dbbe:~$ rm composer-setup.php

vagrant@dbbe:~$ curl -1sLf 'https://dl.cloudsmith.io/public/symfony/stable/setup.deb.sh' | sudo -E bash
vagrant@dbbe:~$ sudo apt install symfony-cli

vagrant@dbbe:~$ git clone git@github.ugent.be:GhentCDH/dbbe2.git
vagrant@dbbe:~$ cd dbbe2
# Following command doesn't work in Visual Studio Code ssh Terminal
# You will have to execute it in an ssh terminal in PowerShell
vagrant@dbbe:~/dbbe2$ git submodule update --init
vagrant@dbbe:~$ cd
```

Download the database dump from <https://data.ghentcdh.ugent.be/apps/files/?dir=/Summer%20of%20Code/dbbe&fileid=600874> and place it in the project folder (/home/vagrant/dbbe2).

```sh
vagrant@dbbe:~$ sudo -u postgres createuser --interactive --pwprompt
Enter name of role to add: dbbe
Enter password for new role: dbbe
Enter it again: dbbe
Shall the new role be a superuser? (y/n) n
Shall the new role be allowed to create databases? (y/n) n
Shall the new role be allowed to create more new roles? (y/n) n
vagrant@dbbe:~$ sudo -u postgres createdb -O dbbe dbbe
vagrant@dbbe:~$ sudo -u postgres psql dbbe < db_dump.sql
vagrant@dbbe:~$ sudo -u postgres psql dbbe < dbbe2/change_owner_after_reimport.sql
```

Create a .env file in the base of the newly created folder with following contents:

```text
APP_ENV=dev
#APP_DEBUG=false
APP_SECRET=output van hexdump -vn16 -e'4/4 "%08X" 1 "\n"' /dev/urandom

DATABASE_URL='postgresql://dbbe:dbbe@localhost:5432/dbbe?serverVersion=12.10'

MAILER_DSN='smtp://dbbe%40ugent.be@smtp.ugent.be:25'

ELASTIC_HOSTS='[]'
ELASTIC_INDEX_PREFIX='dbbe'

SITEKEY=ask
SECRETKEY=ask

```

```sh
vagrant@dbbe:~$ cd /home/vagrant/dbbe2
vagrant@dbbe:~/dbbe2$ composer install
vagrant@dbbe:~/dbbe2$ yarn install
vagrant@dbbe:~/dbbe2$ cd assets/websites
vagrant@dbbe:~/dbbe2/assets/websites$ bower install
```

Index search pages

```sh
vagrant@dbbe:~/dbbe2$ php bin/console app:elasticsearch:index
```

Start the back-end dev server

```sh
vagrant@dbbe:~/dbbe2$ symfony server:start --no-tls
# You can now visit the web application at http://dbbe.local:8000
```

Run the front-end in dev mode

```sh
vagrant@dbbe:~/dbbe2$ yarn encore dev --watch
```

Build the front-end in production mode

```sh
vagrant@dbbe:~/dbbe2$ yarn encore production
```
