# WordPress JSON API Comparison

The purpose of this document is to compare the WordPress JSON REST API (WP
API) to the other WordPress JSON API projects.  Included in this comparison
are the [WordPress.com JSON REST API](http://developer.wordpress.com/docs/api/),
and...

## Authentication

| Feature                  | WP API | WP.com JSON API  |
|:-------------------------|:-------|:----------------:|
| Cookie-based             | X      |                  |
| OAuth1                   | X      |                  |
| OAuth2                   |        | X                |

## Site

| Feature                  | WP API | WP.com JSON API |
|:-------------------------|:-------|:---------------:|
| Basic Site Information   | X      | X               |


## Users

| Feature                  | WP API  | WP.com JSON API |
|:-------------------------|:--------|:---------------:|
| Create a user            | X       |                 |
| List all users           | X       | X               |
| Retrieve a user          | X       |                 |
| Retrieve current user    | X       | X               |
| Edit a user              | X       |                 |
| Delete a user            | X       |                 |

## Posts

| Feature                  | WP API | WP.com JSON API |
|:-------------------------|:-------|:---------------:|
| Create a post            | X      | X               |
| List all posts           | X      | X               |
| Retrieve a post          | X      | X               |
| Edit a post              | X      | X               |
| Delete a post            | X      | X               |
| Create meta for a post   | X      | X               |
| Retrieve meta for a post | X      | X               |
| Edit meta for a post     | X      | X               |
| Delete meta for a post   | X      | X               |

## Taxonomies

| Feature                  | WP API | WP.com JSON API |
|:-------------------------|:-------|:---------------:|
| Create a term            |        | X               |
| List all terms           | X      | X               |
| Retrieve a term          | X      | X               |
| Edit a term              |        | X               |
| Delete a term            |        | X               |
| Create a taxonomy        |        |                 |
| List all taxonomies      | X      |                 |
| Retrieve a taxonomy      | X      |                 |
| Edit a taxonomy          |        |                 |
| Delete a taxonomy        |        |                 |

## Media

| Feature                  | WP API | WP.com JSON API |
|:-------------------------|:-------|:---------------:|
| Create an attachment     | X      | X               |
| List all attachments     | X      | X               |
| Retrieve an attachment   | X      | X               |
| Edit an attachment       | X      | X               |
| Delete an attachment     | X      | X               |
