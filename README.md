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

* Create and configure an ssh keypair (<https://support.atlassian.com/bitbucket-cloud/docs/set-up-an-ssh-key/>)

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

Recommended changes to `Vagrantfile`:

```ruby
  # Configure VM Ram usage and cpus
  config.vm.provider "virtualbox" do |v|
    v.memory = 4096
    v.cpus = 4
  end

  # hostname
  config.vm.hostname = "dbbe.local"
```

On Windows: disable syncing:

```ruby
  # config.vm.synced_folder "./src", "/home/vagrant/src"
```

### Set up Virtual Machine

```sh
vagrant up
```

On Windows: add install scripts and rerun provision:

```sh
$ vagrant ssh
vagrant@dbbe:~$ git clone git@github.ugent.be:lw/debian-install.git install
vagrant@dbbe:~$ logout
$ vagrant provision
```

```sh
$ vagrant ssh
vagrant@dbbe:~$ cd /home/vagrant/install
vagrant@dbbe:~$ sudo ./php7.4-fpm.sh
vagrant@dbbe:~$ sudo ./elasticsearch7.sh
vagrant@dbbe:~$ sudo ./nodejs.sh
vagrant@dbbe:~$ sudo npm install -g yarn

vagrant@dbbe:~$ wget -O composer-setup.php https://getcomposer.org/installer
vagrant@dbbe:~$ sudo php composer-setup.php --install-dir=/usr/local/bin --filename=composer
vagrant@dbbe:~$ rm composer-setup.php


vagrant@dbbe:~$ cd /home/vagrant
vagrant@dbbe:~$ git clone git@github.ugent.be:GhentCDH/dbbe2.git
```

Configure Visual Studio Code for editing on the virtual server (<https://medium.com/@lopezgand/connect-visual-studio-code-with-vagrant-in-your-local-machine-24903fb4a9de>).

```sh
vagrant@dbbe:~$ cd /home/vagrant/dbbe2
vagrant@dbbe:~$ composer
vagrant@dbbe:~$ yarn install
```
