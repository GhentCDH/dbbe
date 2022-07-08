# DBBE

A Vue / Symfony / Elasticsearch project.

## Setting up a development environment

### Prerequirements

* Install Git (<https://git-scm.com/downloads>).

* Install Visual Studio Code (<https://code.visualstudio.com/Download>).

  * Recommended Extensions:
    * ESLint
    * PHP IntelliSense
    * PHP Extension Pack
    * Remote SSH
    * Twig
    * Vetur

* Install Chrome Extensions:
  * Vue.js devtools
  * Elasticvue

* On Windows: create and configure an ssh keypair (<https://my.gapinthevoid.com/2020/10/getting-ssh-agent-passthrough-working.html>)
  * in a Powershell

  ```PowerShell
  ssh-keygen
  ```

  * In an administrative Powershell

  ```PowerShell
  Set-Service ssh-agent -StartupType Automatic
  Start-Service ssh-agent
  ```

  * in a Powershell

  ```PowerShell
  ssh-add -l
  ssh-add C:\Users\<username>\.ssh\id_rsa
  ```

### Install Virtual Machine

* Install Vagrant (<https://www.vagrantup.com/downloads>).

* Install Vagrant plugins

```sh
vagrant plugin install vagrant-reload
vagrant plugin install vagrant-hostmanager
```

* Install VirtualBox (<https://www.virtualbox.org/wiki/Downloads>).

* Download a default Vagrant config

```sh
git clone git@github.ugent.be:GhentCDH/vagrant_default.git dbbe
```

Required changes to `Vagrantfile`:

```ruby
  # Configure VM Ram usage and cpus
  config.vm.provider "virtualbox" do |v|
    v.memory = 4096
    v.cpus = 4
  end

  # hostname
  config.vm.hostname = "dbbe.local"
```

On Windows: disable syncing and fixate ssh port:

```ruby
  # config.vm.synced_folder "./src", "/home/vagrant/src"

  # fix ssh port
  # r = Random.new
  # ssh_port = r.rand(1000...5000)
  config.vm.network :forwarded_port, guest: 22, host: 2222, id: 'ssh', auto_correct: true
```

### Set up Virtual Machine

```sh
vagrant up
```

On Windows: configure Visual Studio Code for editing on the virtual server (<https://medium.com/@lopezgand/connect-visual-studio-code-with-vagrant-in-your-local-machine-24903fb4a9de>). From now on, you can work in the Visual Studio Code Terminal (commands execute on the virtual machine).

On Windows: get install scripts

```
$ vagrant ssh
vagrant@dbbe:~$ git clone git@github.ugent.be:GhentCDH/debian-install.git install
vagrant@dbbe:~$ logout
$ vagrant provision
```

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
vagrant@dbbe:~/dbbe2$ git clone git@github.ugent.be:GhentCDH/dbbe2.git
vagrant@dbbe:~$ cd

# copy db dump
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
