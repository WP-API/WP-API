# WP REST API v2.0 (formerly known as WP-API)

Access your WordPress site's data through an easy-to-use HTTP REST API.

**Development is no longer taking place in this repository**.

- For support requests, use the
  [WordPress forums](https://wordpress.org/support/).
- For bugs and patches, use
  [WordPress core Trac](https://core.trac.wordpress.org).
  Be sure to include full details and reproduction steps about the issue you are
  experiencing, and ideally a patch with unit tests.

The **"develop"** branch is version 2 which represents the last "beta" versions of the
[plugin](https://wordpress.org/plugins/rest-api/).
[Read the documentation](https://developer.wordpress.org/rest-api/)
to introduce yourself to endpoints, internal patterns, and implementation details.

The **"master"** branch represents the **legacy** version of the REST API.

## About

WordPress is moving towards becoming a fully-fledged application framework, and
we need new APIs. This project was born to create an easy-to-use,
easy-to-understand and well-tested framework for creating these APIs, plus
creating APIs for core.

This plugin provides an easy to use REST API, available via HTTP. Grab your
site's data in simple JSON format, including users, posts, taxonomies and more.
Retrieving or updating data is as simple as sending a HTTP request.

Want to get your site's posts? Simply send a `GET` request to `/wp-json/wp/v2/posts`.
Update user with ID 4? Send a `PUT` request to `/wp-json/wp/v2/users/4`. Get the page
with slug "about-me"? `GET /wp-json/wp/v2/pages?slug=about-me`. Get all posts with
the search term "awesome"? `GET /wp-json/wp/v2/posts?search=awesome`. It's that easy.

The WordPress REST API exposes a simple yet easy interface to WP Query, the posts
API, post meta API, users API, revisions API and many more. Chances are, if you
can do it with WordPress, the API will let you do it.

The REST API also includes an easy-to-use JavaScript API based on Backbone models,
allowing plugin and theme developers to get up and running without needing to
know anything about the details of getting connected.

Check out [our documentation][docs] for information on what's available in the
API and how to use it. We've also got documentation on extending the API with
extra data for plugin and theme developers!

The API code in this plugin is currently integrated into core WordPress starting in
[4.7](https://wordpress.org/news/2016/12/vaughan/).

**Development is no longer taking place in this repository**.

- For support requests, use the
  [WordPress forums](https://wordpress.org/support/).
- For bugs and patches, use
  [WordPress core Trac](https://core.trac.wordpress.org).
  Be sure to include full details and reproduction steps about the issue you are
  experiencing, and ideally a patch with unit tests.

## Quick Setup

Want to test out the WP REST API?  The easiest way is just to install a
recent version of WordPress
([4.7](https://wordpress.org/news/2016/12/vaughan/) or later).

### Testing

You can also set up a development environment to work on the API code.

See the
[instructions for running the WordPress PHPUnit test suite](https://make.wordpress.org/core/handbook/testing/automated-testing/phpunit/)
to get started.

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

[docs]: https://developer.wordpress.org/rest-api/
[contributing]: CONTRIBUTING.md
[hackerone]: https://hackerone.com/wp-api
