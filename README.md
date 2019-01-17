HTAdmin
=======

HTAdmin is a simple ~~.htaccess and .htpasswd~~ htpasswd file editor implemented
in PHP and Perl with a nice frontend (based on bootstrap). It's intended to ~~secure a
folder of plain html files with multiple users~~ simply update an htpasswd
file. The admin has to create a user, but every user can change his password by
himself using a self service area. It is also possible to send a password
reset mail.

It comes with a preconfigured Vagrant / Puppet VM, so you don't have to install a LAMP stack locally for testing.

After cloning this repository, do:

```
git submodule update --init
```

## Perl

- Apache 2

As the user *www-data*, you need to install the CPAN Perl module
**Apache::Htpasswd**. Create these folders first:

```
sudo mkdir /var/www/perl5 /var/www/.cpan
sudo chown www-data:www-data /var/www/perl5 /var/www/.cpan
```

Start a CPAN shell and configure the module (CPAN is also a module) — use the defaults:

```
sudo -u www-data perl -MCPAN -e shell
```

When the configuration is done, install **Apache::Htpasswd**:

```
$ install module Apache::Htpasswd
```

## Vagrant

You find the application in `sites/html/htadmin`.

![Screenshot](screenshot.png "Screenshot")

Just install vagrant and virtual box and type

`vagrant up`
 
to start the vm. After startup point your browser to:

<http://localhost/htadmin/>

Standard access: admin / admin, make sure to change that in your `...config/config.ini`. You have to enter a hashed password, there is a tool for its generation included in the webapp:

<http://localhost/htadmin/adminpwd.php>

the .htaccess and .htpasswd files are configured for this folder:

<http://localhost/test/>

Uses the following libraries:

<https://github.com/PHPMailer/PHPMailer>


Enjoy!
