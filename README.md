# REST API
This is a project to create a JSON-based REST API for WordPress. This project is
run by Ryan McCue and is part of the WordPress 2013 GSoC projects.


## Documentation
Read the [plugin's documentation][docs].

[docs]: https://github.com/rmccue/WP-API/tree/master/docs


## Installation
### As a Plugin
Drop this directory in and activate it. You need to be using pretty permalinks
to use the plugin, as it uses custom rewrite rules to power the API. You'll also
need to flush your rewrite rules. This can be done by simply visiting the
Permalinks settings page in the admin.

### As Part of Core
**Note: These instructions will likely be broken while in development. Please
use the plugin method instead.**

Drop `wp-json.php` into your WordPress directory, and drop
`class-wp-json-server.php` into your `wp-includes/` directory. You'll need
working `PATH_INFO` on your server, but you don't need pretty permalinks
enabled.


## Issue Tracking
All tickets for the project are being tracked on the [GSoC Trac][]. Make sure
you use the JSON REST API component.

[GSoC Trac]: https://gsoc.trac.wordpress.org/query?component=JSON+REST+API
