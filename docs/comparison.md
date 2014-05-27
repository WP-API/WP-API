# WordPress JSON API Comparison

The purpose of this document is to compare the WordPress JSON REST API (WP
API) to the other WordPress JSON API projects.  Included in this comparison
are the [WordPress.com JSON REST API](http://developer.wordpress.com/docs/api/),
and the built-in XML-RPC API.

## Key
* **M**: Multisite only
* **P**: Available via (official) plugin
* **~**: Partially available

## Authentication

| Feature                  | WP API | WP.com JSON API | XML-RPC API |
|:-------------------------|:-------|:----------------|:-----------:|
| Cookie-based             | X      |                 |             |
| Basic authentication     | P      |                 | X           |
| OAuth1                   | X      |                 |             |
| OAuth2                   |        | X               |             |

## Site

| Feature                  | WP API | WP.com JSON API | XML-RPC API |
|:-------------------------|:-------|:----------------|:-----------:|
| Basic Site Information   | X      | X               | M           |
| Retrieve options data    |        | X               | X           |
| Update options data      |        |                 | X           |
| Retrieve post count      | X      | X               |             |
| List available routes    | X      | ~               | X           |


## Users

| Feature                  | WP API | WP.com JSON API | XML-RPC API |
|:-------------------------|:-------|:----------------|:-----------:|
| Create a user            | X      |                 |             |
| List all users           | X      | X               | X           |
| Retrieve a user          | X      |                 | X           |
| Retrieve current user    | X      | X               | X           |
| Edit a user              | X      |                 | X           |
| Delete a user            | X      |                 |             |

## Posts

| Feature                  | WP API | WP.com JSON API | XML-RPC API |
|:-------------------------|:-------|:----------------|:-----------:|
| Create a post            | X      | X               | X           |
| List all posts           | X      | X               | X           |
| Retrieve a post          | X      | X               | X           |
| Edit a post              | X      | X               | X           |
| Delete a post            | X      | X               | X           |
| Create meta for a post   | X      | X               | X           |
| Retrieve meta for a post | X      | X               | X           |
| Edit meta for a post     | X      | X               | X           |
| Delete meta for a post   | X      | X               | X           |

## Comments

| Feature                  | WP API | WP.com JSON API | XML-RPC API |
|:-------------------------|:-------|:----------------|:-----------:|
| Create a comment         |        | X               | X           |
| Create a reply           |        | X               | X           |
| List all comments        |        | X               | X           |
| List comments for a post | X      | X               | X           |
| Retrieve a comment       | X      | X               | X           |
| Edit a comment           |        | X               | X           |
| Delete a comment         | X      | X               | X           |

# Post-Related Data

| Feature                  | WP API | WP.com JSON API | XML-RPC API |
|:-------------------------|:-------|:----------------|:-----------:|
| List all post types      | X      |                 | X           |
| Retrieve post type       | X      |                 | X           |
| List all post statuses   | X      |                 | X           |
| Retrieve post status     |        |                 |             |

## Taxonomies

| Feature                  | WP API | WP.com JSON API | XML-RPC API |
|:-------------------------|:-------|:---------------:|:-----------:|
| Create a term            |        | X               | X           |
| List all terms           | X      | X               | X           |
| Retrieve a term          | X      | X               | X           |
| Edit a term              |        | X               | X           |
| Delete a term            |        | X               | X           |
| Create a taxonomy        |        |                 |             |
| List all taxonomies      | X      |                 | X           |
| Retrieve a taxonomy      | X      |                 | X           |
| Edit a taxonomy          |        |                 |             |
| Delete a taxonomy        |        |                 |             |

## Media

| Feature                  | WP API | WP.com JSON API | XML-RPC API |
|:-------------------------|:-------|:---------------:|:-----------:|
| Create an attachment     | X      | X               | X           |
| List all attachments     | X      | X               | X           |
| Retrieve an attachment   | X      | X               | X           |
| Edit an attachment       | X      | X               | X           |
| Delete an attachment     | X      | X               | X           |
