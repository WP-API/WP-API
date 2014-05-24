# REST API
This is a project to create a JSON-based REST API for WordPress. This project is
run by Ryan McCue and is part of the WordPress 2013 GSoC projects.

[![Build Status](https://travis-ci.org/WP-API/WP-API.png?branch=master)](https://travis-ci.org/WP-API/WP-API)


## Documentation

Read the [plugin's documentation][docs].

[docs]: https://github.com/WP-API/WP-API/tree/master/docs


## Installation

### As a Plugin
Drop this directory in and activate it. You need to be using pretty permalinks
to use the plugin, as it uses custom rewrite rules to power the API.

### As Part of Core
**Note: These instructions will likely be broken while in development. Please
use the plugin method instead.**

Drop `wp-json.php` into your WordPress directory, and drop
`class-wp-json-server.php` into your `wp-includes/` directory. You'll need
working `PATH_INFO` on your server, but you don't need pretty permalinks
enabled.


## Quick Setup

Want to test out WP-API and work on it? Here's how you can set up your own
testing environment in a few easy steps:

1. Install [Vagrant](http://vagrantup.com/) and [VirtualBox](https://www.virtualbox.org/).
2. Clone [Chassis](https://github.com/sennza/Chassis):

   ```bash
   git clone --recursive git@github.com:sennza/Chassis.git api-tester
   vagrant plugin install vagrant-hostsupdater
   ```

3. Grab a copy of WP API:

   ```bash
   cd api-tester
   mkdir -p content/plugins content/themes
   cp -r wp/wp-content/themes/* content/themes
   git clone git@github.com:WP-API/WP-API.git content/plugins/json-rest-api
   ```

4. Start the virtual machine:

   ```bash
   vagrant up
   ```

5. Browse to http://vagrant.local/wp/wp-admin/plugins.php and activate the WP
API plugin

   ```
   Username: admin
   Password: password
   ```

6. Browse to http://vagrant.local/wp/wp-admin/options-permalink.php and set
the permalink structure to anything other than "Default"

7. Browse to http://vagrant.local/wp-json/ (or if the permalink structure is
still "Default," to http://vagrant.local/?json_route=/)


### Testing

For testing, you'll need a little bit more:

1. SSH into your Vagrant box, and install PHPUnit:

   ```bash
   vagrant ssh
   sudo apt-get install php-pear
   sudo pear config-set auto_discover 1
   sudo pear install pear.phpunit.de/PHPUnit
   ```

2. Clone WordPress development (including tests):

   ```bash
   git clone git://develop.git.wordpress.org/ /tmp/wordpress
   export WP_DEVELOP_DIR=/tmp/wordpress
   ```

3. Run the testing suite:

   ```bash
   cd /vagrant/content/plugins/json-rest-api
   phpunit
   ```


## Issue Tracking

All tickets for the project are being tracked on [GitHub][]. Previous issues can
be found on the [GSOC Trac][] issue tracker, however new issues should not be
filed there.

[GitHub]: https://github.com/WP-API/WP-API
[GSOC Trac]: https://gsoc.trac.wordpress.org/query?component=JSON+REST+API
