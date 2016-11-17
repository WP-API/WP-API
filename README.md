# WP REST API v2.0 (WP-API)

Access your WordPress site's data through an easy-to-use HTTP REST API.

[![Build Status](https://travis-ci.org/WP-API/WP-API.svg?branch=develop)](https://travis-ci.org/WP-API/WP-API)
[![codecov.io](http://codecov.io/github/WP-API/WP-API/coverage.svg?branch=develop)](http://codecov.io/github/WP-API/WP-API?branch=develop)

The **"develop"** branch is version 2 which is "beta" but stable and recommended for production. [Read the  documentation](http://v2.wp-api.org/)
to introduce yourself to endpoints, internal patterns, and implementation details.

The **"master"** branch represents the **legacy** version of the REST API.

The latest **stable** version is also available from the [WordPress Plugin Directory](https://wordpress.org/plugins/rest-api/).

## About

WordPress is moving towards becoming a fully-fledged application framework, and
we need new APIs. This project was born to create an easy-to-use,
easy-to-understand and well-tested framework for creating these APIs, plus
creating APIs for core.

This plugin provides an easy to use REST API, available via HTTP. Grab your
site's data in simple JSON format, including users, posts, taxonomies and more.
Retrieving or updating data is as simple as sending a HTTP request.

Want to get your site's posts? Simply send a `GET` request to `/wp-json/wp/v2/posts`.
Update user with ID 4? Send a `PUT` request to `/wp-json/wp/v2/users/4`. Get all
posts with the search term "awesome"? `GET /wp-json/wp/v2/posts?filter[s]=awesome`.
It's that easy.

The WordPress REST API exposes a simple yet easy interface to WP Query, the posts
API, post meta API, users API, revisions API and many more. Chances are, if you
can do it with WordPress, the API will let you do it.

The REST API also includes an easy-to-use JavaScript API based on Backbone models,
allowing plugin and theme developers to get up and running without needing to
know anything about the details of getting connected.

Check out [our documentation][docs] for information on what's available in the
API and how to use it. We've also got documentation on extending the API with
extra data for plugin and theme developers!

The API code in this plugin is currently integrated into core WordPress in the
trunk (latest) and 4.7 beta versions.

**Development is no longer taking place in this repository** - see
[WordPress core Trac](https://core.trac.wordpress.org)
instead.


## Quick Setup

Want to test out the WP REST API?  The easiest way is just to install a
recent development version of WordPress (for starters, try
[4.7 beta 4](https://wordpress.org/news/2016/11/wordpress-4-7-beta-4/).

### Testing

You can also set up a development environment to work on the API code.

See the
[instructions for running the WordPress PHPUnit test suite](https://make.wordpress.org/core/handbook/testing/automated-testing/phpunit/)
to get started.

Here is another way to set up a development environment using a virtual
machine:

1. Install [Vagrant](http://vagrantup.com/) and [VirtualBox](https://www.virtualbox.org/).
2. Clone [Chassis](https://github.com/Chassis/Chassis):

   ```bash
   git clone --recursive git@github.com:Chassis/Chassis.git api-tester
   ```

3. Clone the [Tester extension](https://github.com/Chassis/Tester) for Chassis:

   ```bash
   # From your base directory, api-tester if following the steps from before
   git clone --recursive https://github.com/Chassis/Tester.git extensions/tester
   ```

4. Update the `wpdevel` submodule in Chassis to latest on master from [WordPress Git Mirror](https://make.wordpress.org/core/2014/01/15/git-mirrors-for-wordpress/):

   ```bash
   # From your base directory, api-tester if following the steps from before
   cd extensions/tester/wpdevel
   git checkout master
   git pull
   cd ../../..
   ```

5. Start the virtual machine:

   ```bash
   vagrant up
   ```

6. Set the permalink structure to something other than the default, in order to
   enable the http://vagrant.local/wp-json/ endpoint URL (if you skip this
   step, it can be accessed at http://vagrant.local/?json_route=/):

   ```bash
   vagrant ssh -c "cd /vagrant && wp rewrite structure '/%postname%/'"
   ```

7. Log in to the virtual machine and run the testing suite:

   ```bash
   vagrant ssh
   cd /vagrant/extensions/tester/wpdevel/
   phpunit --filter REST
   ```

   **TODO: This is broken: PHP Fatal error:  Class 'DOMDocument' not found ...**

   **TODO: How to keep `wpdevel/src/` and `/vagrant/wp/` in sync?**

   You can also execute the tests in the context of the VM without SSHing
   into the virtual machine (this is equivalent to the above):

   ```bash
   vagrant ssh -c 'cd /vagrant/extensions/tester/wpdevel/ && phpunit --filter REST'
   ```

You're done! You should now have a WordPress site available at
http://vagrant.local; you can access the API via http://vagrant.local/wp-json/

To access the admin interface, visit http://vagrant.local/wp/wp-admin and log
in with the credentials below:

   ```
   Username: admin
   Password: password
   ```

## Issue Tracking

All tickets for the project are being tracked on
[WordPress core Trac](https://core.trac.wordpress.org).

Some previous issues can be found on the
[issue tracker for this repository](/WP-API/WP-API/issues);
however, now that development of the API has moved to core Trac, new issues
**should not be filed here**.

## Contributing

Want to get involved? Check out [Contributing.md][contributing] for details on
submitting fixes and new features.

## Security

We take the security of the API extremely seriously. If you think you've found
a security issue with the API (whether information disclosure, privilege
escalation, or another issue), we'd appreciate responsible disclosure as soon
as possible.

To report a security issue, you can either email `security[at]wordpress.org`,
or [file an issue on HackerOne][hackerone]. We will attempt to give an initial
response to security issues within 48 hours at most, however keep in mind that
the team is distributed across various timezones, and delays may occur as we
discuss internally.

(Please note: For testing, you should install a copy of the project and
WordPress on your own server. **Do not test on servers you do not own.**)

## License

[GPLv2+](http://www.gnu.org/licenses/gpl-2.0.html)

[docs]: http://v2.wp-api.org/
[contributing]: CONTRIBUTING.md
[hackerone]: https://hackerone.com/wp-api
