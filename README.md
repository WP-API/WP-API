# WP REST API v2.0 (WP-API)

Access your WordPress site's data through an easy-to-use HTTP REST API.

[![Build Status](https://travis-ci.org/WP-API/WP-API.svg?branch=develop)](https://travis-ci.org/WP-API/WP-API)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/WP-API/WP-API/badges/quality-score.png?b=develop)](https://scrutinizer-ci.com/g/WP-API/WP-API/?branch=develop)
[![codecov.io](http://codecov.io/github/WP-API/WP-API/coverage.svg?branch=develop)](http://codecov.io/github/WP-API/WP-API?branch=develop)

## WARNING

The **"develop"** branch is undergoing substantial changes and is **NOT COMPLETE OR STABLE**. [Read the in-progress documentation](http://v2.wp-api.org/) to introduce yourself to endpoints, internal patterns, and implementation details.

The **"master"** branch represents a **BETA** of our next version release.

The latest **stable** version is available from the [WordPress Plugin Directory](https://wordpress.org/plugins/rest-api/).

## About

WordPress is moving towards becoming a fully-fledged application framework, and
we need new APIs. This project was born to create an easy-to-use,
easy-to-understand and well-tested framework for creating these APIs, plus
creating APIs for core.

This plugin provides an easy to use REST API, available via HTTP. Grab your
site's data in simple JSON format, including users, posts, taxonomies and more.
Retrieving or updating data is as simple as sending a HTTP request.

Want to get your site's posts? Simply send a `GET` request to `/wp-json/wp/v2/posts`.
Update user with ID 4? Send a `POST` request to `/wp-json/wp/v2/users/4`. Get all
posts with the search term "awesome"? `GET /wp-json/wp/v2/posts?filter[s]=awesome`.
It's that easy.

WP API exposes a simple yet easy interface to WP Query, the posts API, post meta
API, users API, revisions API and many more. Chances are, if you can do it with
WordPress, WP API will let you do it.

WP API also includes an easy-to-use JavaScript API based on Backbone models,
allowing plugin and theme developers to get up and running without needing to
know anything about the details of getting connected.

Check out [our documentation][docs] for information on what's available in the
API and how to use it. We've also got documentation on extending the API with
extra data for plugin and theme developers!

There's no fixed timeline for integration into core at this time, but getting closer!


## Installation

Drop this directory in and activate it. You need to be using pretty permalinks
to use the plugin, as it uses custom rewrite rules to power the API.

Also, be sure to use the Subversion `trunk` branch of WordPress Core as there are potentially recent commits to Core that the REST API relies on. See the [WordPress.org website](https://wordpress.org/download/svn/) for simple instructions.

## Issue Tracking

All tickets for the project are being tracked on [GitHub][]. You can also take a
look at the [recent updates][] for the project.

## Contributing

Want to get involved? Check out [Contributing.md][contributing] for details on submitting fixes and new features.

## Security

We take the security of the API extremely seriously. If you think you've found
a security issue with the API (whether information disclosure, privilege
escalation, or another issue), we'd appreciate responsible disclosure as soon as
possible.

To report a security issue, you can either email `security[at]wordpress.org`, or
[file an issue on HackerOne][hackerone]. We will attempt to give an initial
response to security issues within 48 hours at most, however keep in mind that
the team is distributed across various timezones, and delays may occur as we
discuss internally.

(Please note: For testing, you should install a copy of the project and
WordPress on your own server. **Do not test on servers you do not own.**)

## License

[GPLv2+](http://www.gnu.org/licenses/gpl-2.0.html)

[docs]: http://v2.wp-api.org/
[GitHub]: https://github.com/WP-API/WP-API/issues
[contributing]: CONTRIBUTING.md
[recent updates]: https://make.wordpress.org/core/tag/json-api/
[hackerone]: https://hackerone.com/wp-api
