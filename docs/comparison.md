# WordPress JSON API Comparison

The purpose of this document is to compare the WordPress JSON REST API (WP
API) to the other WordPress JSON API projects.  Included in this comparison
are the [WordPress.com JSON REST API](http://developer.wordpress.com/docs/api/),
and the built-in XML-RPC API.

## Functionality

### Key
* **M**: Multisite only
* **P**: Available via (official) plugin
* **~**: Partially available

### Authentication

| Feature                  | WP API | WP.com JSON API | XML-RPC API |
|:-------------------------|:-------|:----------------|:-----------:|
| Cookie-based             | X      |                 |             |
| Basic authentication     | P      |                 | X           |
| OAuth1                   | X      |                 |             |
| OAuth2                   |        | X               |             |
| Custom authentication    | X      |                 | ~           |

Custom authentication can be used with WP API via the `determine_current_user`
filter. With the XML-RPC API, only one custom handler can be added at a time via
subclassing.

### General

| Feature                  | WP API | WP.com JSON API | XML-RPC API |
|:-------------------------|:-------|:----------------|:-----------:|
| Add custom routes        | X      |                 | X           |
| Modify responses         | X      |                 | X           |
| Support for other formats| X      |                 |             |
| Internally usable        | X      |                 |             |
| Plugin/theme support     | X      | ~               |             |
| Usable while offline     | X      |                 | X           |

WP API supports fully replacing JSON responses with an alternative format, such
as MsgPack, as well as replacing the HTTP layers with others. The API can also
be called internally as normal functions, without causing side effects (HTTP
headers, premature exit, etc).

WP API supports use in the browser by themes/plugins simply by enqueuing the
JS client. WP.com API requires writing custom code to communicate with the API.


### Site

| Feature                  | WP API | WP.com JSON API | XML-RPC API |
|:-------------------------|:-------|:----------------|:-----------:|
| Basic Site Information   | X      | X               | M           |
| Retrieve options data    |        | X               | ~           |
| Update options data      |        |                 | ~           |
| Retrieve post count      | X      | X               |             |
| List available routes    | X      | X               | X           |

The XML-RPC API only exposes a limited subset of the available options in
WordPress.

### Users

| Feature                  | WP API | WP.com JSON API | XML-RPC API |
|:-------------------------|:-------|:----------------|:-----------:|
| Create a user            | X      |                 |             |
| List all users           | X      | X               | X           |
| Retrieve a user          | X      |                 | X           |
| Retrieve current user    | X      | X               | X           |
| Edit a user              | X      |                 | X           |
| Delete a user            | X      |                 |             |

### Posts

| Feature                  | WP API | WP.com JSON API | XML-RPC API |
|:-------------------------|:-------|:----------------|:-----------:|
| Create a post            | X      | X               | X           |
| List all posts           | X      | X               | X           |
| Retrieve a post          | X      | X               | X           |
| Retrieve a post by slug  | ~      | X               |             |
| Edit a post              | X      | X               | X           |
| Delete a post            | X      | X               | X           |
| Create meta for a post   | X      | X               | X           |
| Retrieve meta for a post | X      | X               | X           |
| Edit meta for a post     | X      | X               | X           |
| Delete meta for a post   | X      | X               | X           |

WP API does not provide a specialised page-by-slug retrieval method, but allows
accessing via WP Query:

    GET /wp-json/posts?filter[name]=my-post-slug

### Comments

| Feature                  | WP API | WP.com JSON API | XML-RPC API |
|:-------------------------|:-------|:----------------|:-----------:|
| Create a comment         |        | X               | X           |
| Create a reply           |        | X               | X           |
| List all comments        |        | X               | X           |
| List comments for a post | X      | X               | X           |
| Retrieve a comment       | X      | X               | X           |
| Edit a comment           |        | X               | X           |
| Delete a comment         | X      | X               | X           |

### Post-Related Data

| Feature                  | WP API | WP.com JSON API | XML-RPC API |
|:-------------------------|:-------|:----------------|:-----------:|
| List all post types      | X      |                 | X           |
| Retrieve post type       | X      |                 | X           |
| List all post statuses   | X      |                 | X           |
| Retrieve post status     |        |                 |             |

### Taxonomies

| Feature                  | WP API | WP.com JSON API | XML-RPC API |
|:-------------------------|:-------|:---------------:|:-----------:|
| Create a term            |        | X               | X           |
| List all terms           | X      | X               | X           |
| Retrieve a term          | X      | X               | X           |
| Edit a term              |        | X               | X           |
| Delete a term            |        | X               | X           |
| List all taxonomies      | X      |                 | X           |
| Retrieve a taxonomy      | X      |                 | X           |

Taxonomies are defined in code, so no APIs can create, modify, or delete
taxonomies.

### Revisions

| Feature                  | WP API | WP.com JSON API | XML-RPC API |
|:-------------------------|:-------|:---------------:|:-----------:|
| Get revisions for a post | X      |                 | X           |
| Restore revision         |        |                 | X           |

### Media

| Feature                  | WP API | WP.com JSON API | XML-RPC API |
|:-------------------------|:-------|:---------------:|:-----------:|
| Create an attachment     | X      | X               | X           |
| List all attachments     | X      | X               | X           |
| Retrieve an attachment   | X      | X               | X           |
| Edit an attachment       | X      | X               | X           |
| Delete an attachment     | X      | X               | X           |

### Pages

| Feature                  | WP API | WP.com JSON API | XML-RPC API |
|:-------------------------|:-------|:---------------:|:-----------:|
| Create a page            | X      | X               | X           |
| List all pages           | X      | X               | X           |
| Retrieve a page          | X      | X               | X           |
| Retrieve a page by slug  | X      | X               |             |
| Edit a page              | X      | X               | X           |
| Delete a page            | X      | X               | X           |

## Formats

### Posts

See [read format](http://wp-api.github.io/comparison/post.html) vs
[edit format](http://wp-api.github.io/comparison/post-edit.html). The edit
format is triggered in both by appending `?context=edit` to the resource.

**Key differences:**

* WP.com API changes the format of the `content`/`excerpt` fields if in edit
  mode. WP API separates the fields into `content`/`content_raw` and
  `excerpt`/`excerpt_raw` respectively.

  With combined fields, clients cannot display the existing content and also
  allow editing without sending multiple requests.

* WP API links to all resources related to the current one, including the
  author endpoint and the related collection (e.g. `/posts` or `/media`). WP.com
  API contains a limited subset of links, and does not include author or
  collection links.

* WP.com API embeds all attachments in the post. WP API does not currently do
  this. Attachments belonging to the post can still be queried via
  `/media?filter[post_parent]=<id>`.

## Media

See [read format](http://wp-api.github.io/comparison/media.html) vs
[edit format](http://wp-api.github.io/comparison/media-edit.html). The edit
format is triggered in both by appending `?context=edit` to the resource.

**Key differences:**

* WP API does not expose the caption. This is a bug, and will be fixed in 1.1.

* WP API treats media as a full custom post type, and includes all post-related
  data. WP.com API treats media as a different type, and only gives a limited
  subset of all data back.

* WP.com API does not expose intermediate size URLs, as Photon is used for
  WP.com and Jetpack image resizing. WP API exposes all intermediate size URLs
  where possible.

* WP.com API does not link to any related resources. WP API links to all related
  resources, such as the parent, the collection and the author.

* WP.com API does not expose any authorship data, instead relying on clients to
  query for the post parent, and work out the authorship of the parent post.
  WP API treats this as normal post data, with its own author, and embeds the
  entire user object for the view context (as with normal posts).
