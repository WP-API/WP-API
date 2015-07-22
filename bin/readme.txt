=== WordPress REST API (Version 2) ===
Contributors: rmccue, rachelbaker
Tags: json, rest, api, rest-api
Requires at least: 4.3-alpha
Tested up to: 4.3-beta
Stable tag: {{TAG}}
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Access your site's data through an easy-to-use HTTP REST API. (Version 2)

== Description ==
WordPress is moving towards becoming a fully-fledged application framework, and we need new APIs. This project was born to create an easy-to-use, easy-to-understand and well-tested framework for creating these APIs, plus creating APIs for core.

This plugin provides an easy to use REST API, available via HTTP. Grab your site's data in simple JSON format, including users, posts, taxonomies and more. Retrieving or updating data is as simple as sending a HTTP request.

Want to get your site's posts? Simply send a `GET` request to `/wp-json/wp/v2/posts`. Update user with ID 4? Send a `PUT` request to `/wp-json/wp/v2/users/4`. Get all posts with the search term "awesome"? `GET /wp-json/wp/v2/posts?filter[s]=awesome`. It's that easy.

WP API exposes a simple yet easy interface to WP Query, the posts API, post meta API, users API, revisions API and many more. Chances are, if you can do it with WordPress, WP API will let you do it.

WP API also includes an easy-to-use Javascript API based on Backbone models, allowing plugin and theme developers to get up and running without needing to know anything about the details of getting connected.

Check out [our documentation][docs] for information on what's available in the API and how to use it. We've also got documentation on extending the API with extra data for plugin and theme developers!

All tickets for the project are being tracked on [GitHub][]. You can also take a look at the [recent updates][] for the project.

[docs]: http://v2.wp-api.org/
[GitHub]: https://github.com/WP-API/WP-API
[recent updates]: http://make.wp-api.org/

== Installation ==

Drop this directory in and activate it.

For full-flavoured API support, you'll need to be using pretty permalinks to use the plugin, as it uses custom rewrite rules to power the API.

== Changelog ==

= Version 2.0 Beta 1 =

Partial rewrite and evolution of the REST API to prepare for core integration.

For versions 0.x through 1.x, see the [legacy plugin changelog](https://wordpress.org/plugins/json-rest-api/changelog/).
