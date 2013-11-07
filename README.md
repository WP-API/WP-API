# REST API
This is a project to create a JSON-based REST API for WordPress. This project is
run by Ryan McCue and is part of the WordPress 2013 GSoC projects.


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


## Issue Tracking
All tickets for the project are being tracked on the [GSoC Trac][]. Make sure
you use the JSON REST API component.

[GSoC Trac]: https://gsoc.trac.wordpress.org/query?component=JSON+REST+API

## RoyBoy789 Additions
I have added in a /widgets route with will handle /widgets/id where id is the sidebar id  
I have added in a POST for /post/comments to add new comments  
I have added in a /users route with POST (to add new) and /users/id for a single profile page
All code used in my [AngularJS Wordpress Theme](http://www.roysivan.com/angular-wordpress-theme)
