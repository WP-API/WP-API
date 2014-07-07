Posts
=====

Create a Post
-------------

	POST /posts

### Input
The `data` parameter consists of the elements of the Post object to be
created.  This data can be submitted via a regular HTTP multipart body, with
the Post keys and values set to the `data` parameter, or through a direct JSON
body.

That is, the following are equivalent:

Content-Type: application/x-www-form-urlencoded

	data[title]=Hello%20World!&data[content_raw]=Content&data[excerpt_raw]=Excerpt


Content-Type: application/json

	{"title":"Hello World!","content_raw":"Content","excerpt_raw":"Excerpt"}

The `data` parameter should be an object containing the following key value
pairs:

* `title` - Title of the post. (string) __*required*__
* `content_raw` - Full text of the post. (string) __*required*__
* `excerpt_raw` - Text for excerpt of the post. (string) *optional*
* `name` - Slug of the post. (string) *optional*
* `status` - Post status of the post: `draft`, `publish`, `pending`, `future`,
  `private`, or any custom registered status.  If providing a status of
  `future`, you must specify a `date` in order for the post to be published as
  expected.  Default is `draft`. (string) *optional*
* `type` - Post type of the post: `post`, `page`, `link`, `nav_menu_item`, or
  a any custom registered type.  Default is `post`. (string) *optional*
* `date` - Date and time the post was, or should be, published in local time.
  Date should be an RFC3339 timestamp](http://tools.ietf.org/html/rfc3339).
  Example: 2014-01-01T12:20:52Z.  Default is the local date and time. (string)
  *optional*
* `date_gmt` - Date and time the post was, or should be, published in UTC time.
  Date should be an [RFC3339 timestamp](http://tools.ietf.org/html/rfc3339).
  Example: 201401-01T12:20:52Z.  Default is the current GMT date and time.
  (string) *optional*
* `author` - Author of the post.  Author can be provided as a string of the
  author's ID or as the User object of the author.  Default is current user.
  (object \| string) *optional*
* `password` - Password for protecting the post.  Default is empty string.
  (string) *optional*
* `post_parent` - Post ID of the post parent.  Default is 0. (integer)
  *optional*
* `post_format` - Format of the post.  Default is `standard`. (string)
  *optional*
* `menu_order` - The order in which posts specified as the `page` type should
  appear in supported menus.  Default 0. (integer) *optional*
* `comment_status` - Comment status for the post: `open` or `closed`.
  Indicates whether users can submit comments to the post.  Default is the
  option 'default_comment_status', or 'closed'. (string) *optional*
* `ping_status` - Ping status for the post: `open` or `closed`.  Indicates
  whether users can submit pingbacks or trackbacks to the post.  Default is the
  option 'default_ping_status'. (string) *optional*
* `sticky` - Sticky status for the post: `true` or `false`.  Default is
  `false`.  (boolean) *optional*
* `post_meta` - Post meta entries of the post.  Post meta should be an array
  of one or more Meta objects for each post meta entry.  See the Create Meta
  for a Post endpoint for the key value pairs.  (array) *optional*


### Response
On a successful creation, a 201 Created status is given, indicating that the
post has been created. The post is available canonically from the URL specified
in the Location header.

The new Post entity is also returned in the body for convienience.


Retrieve Posts
--------------
The Posts endpoint returns a Post Collection containing a subset of the site's
posts.

	GET /posts

### Input
#### `filter`
The `filter` parameter controls the query parameters.  It is essentially a subset of the parameters available to [`WP_Query`](http://codex.wordpress.org/Class_Reference/WP_Query).

The parameter should be an array of the following key/value pairs:

* `post_status` - Comma-separated list of [status
  values](http://codex.wordpress.org/Class_Reference/WP_Query#Status_Parameters).
  Default is "publish". (string)
* `numberposts` - Number of posts to retrieve, use `-1` for all posts. Default
  is set by the site. (integer)
* `offset` - Number of posts to skip. Default is 0. (integer)
* `orderby` - Parameter to search by, as per [WP Query](http://codex.wordpress.org/Class_Reference/WP_Query#Order_.26_Orderby_Parameters).  Default is
  "date". (string)
* `order` - Order to sort by. Default is "DESC". (string, "ASC" or "DESC")
* `s` - Keyword to search for. (string)


#### `context`
The `context` parameter controls the format of the data to return. See the
Retrieve a Post endpoint for available contexts.

Default is "view". (string)


#### `type`
The `type` parameter specifies the post type to retrieve. This can either be a
string or an array of types.

Note that arrays are specified using the `[]` URL syntax. e.g.

```
GET /posts?type[]=post&type[]=page
```

Default is "post". (string)


### Response
The response is a Post Collection document containing the requested Posts if
available.


Retrieve a Post
---------------

	GET /posts/<id>

### Input
#### `context`
The `context` parameter controls the format of the data to return.  The
following contexts are available:

* `view`: The default context. Gives the normal User entity.
* `edit`: Context used for extra fields relevant to updating a user. Includes
  the `title_raw`, `content_raw`, `guid_raw` and `post_meta` fields, suitable
  for editing the post.
* `parent`: Context used when embedding the response inside another (e.g. post
  author). This is intended as a minimal subset of the user data to reduce
  response size. Returns the `parent` field as an ID, rather than an embedded
  post, to ensure we don't traverse the entire post hierarchy.

### Response
The response is a Post entity containing the requested Post if available. The
fields available on the Post depend on the `context` parameter.


Edit a Post
-----------

	PUT /posts/<id>

For compatibility reasons, this endpoint also accepts the POST and PATCH
methods. Both of these methods have the same behaviour as using PUT. It is
recommended to use PUT if available to fit with REST convention.

### Input
The `data` parameter consists of Post ID and the elements of the Post object
to be modified.  This data can be submitted via a regular HTTP multipart body,
with the Post keys and values set to the `data` parameter, or through a direct
JSON body.  See the Create Post endpoint for an example.

The `data` parameter should be an object containing the following key value
pairs:

* `ID` - Unique ID of the post. (integer) __*required*__
* `title` - Title of the post. (string) __*required*__
* `content_raw` - Full text of the post. (string) __*required*__
* `excerpt_raw` - Text for excerpt of the post. (string) *optional*
* `name` - Slug of the post. (string) *optional*
* `status` - Post status of the post: `draft`, `publish`, `pending`, `future`,
  `private`, or any custom registered status.  If providing a status of
  `future`, you must specify a `date` in order for the post to be published as
  expected.  Default is `draft`. (string) *optional*
* `type` - Post type of the post: `post`, `page`, `link`, `nav_menu_item`, or
  a any custom registered type.  Default is `post`. (string) *optional*
* `date` - Date and time the post was, or should be, published in local time.
  Date should be an RFC3339 timestamp](http://tools.ietf.org/html/rfc3339).
  Example: 2014-01-01T12:20:52Z.  Default is the local date and time. (string)
  *optional*
* `date_gmt` - Date and time the post was, or should be, published in UTC time.
  Date should be an [RFC3339 timestamp](http://tools.ietf.org/html/rfc3339).
  Example: 201401-01T12:20:52Z.  Default is the current GMT date and time.
  (string) *optional*
* `author` - Author of the post.  Author can be provided as a string of the
  author's ID or as the User object of the author.  Default is current user.
  (object \| string) *optional*
* `password` - Password for protecting the post.  Default is empty string.
  (string) *optional*
* `post_parent` - Post ID of the post parent.  Default is 0. (integer)
  *optional*
* `post_format` - Format of the post.  Default is `standard`. (string)
  *optional*
* `menu_order` - The order in which posts specified as the `page` type should
  appear in supported menus.  Default 0. (integer) *optional*
* `comment_status` - Comment status for the post: `open` or `closed`.
  Indicates whether users can submit comments to the post.  Default is the
  option 'default_comment_status', or 'closed'. (string) *optional*
* `ping_status` - Ping status for the post: `open` or `closed`.  Indicates
  whether users can submit pingbacks or trackbacks to the post.  Default is the
  option 'default_ping_status'. (string) *optional*
* `sticky` - Sticky status for the post: `true` or `false`.  Default is
  `false`.  (boolean) *optional*
* `post_meta` - Post meta entries of the post.  Post meta should be an array
  of one or more Meta objects for each post meta entry.  See the Edit Meta
  for a Post endpoint for the key value pairs.  (array) *optional*


### Response
On a successful update, a 200 OK status is given, indicating the post has been
updated. The updated Post entity is returned in the body.


Delete a Post
-------------

	DELETE /posts/<id>

### Input
#### `force`
The `force` parameter controls whether the post is permanently deleted or not.
By default, this is set to false, indicating that the post will be sent to an
intermediate storage (such as the trash) allowing it to be restored later. If
set to true, the post will not be able to be restored by the user.

Default is false. (boolean)

### Response
On successful deletion, a 202 Accepted status code will be returned, indicating
that the post has been moved to the trash for permanent deletion at a
later date.

If force was set to true, a 200 OK status code will be returned instead,
indicating that the post has been permanently deleted.


Create Meta for a Post
------------------------

	POST /posts/<id>/meta

### Input
The supplied data should be a Meta object. This data can be submitted via a
regular HTTP multipart body, with the Meta key and value set with the `data`
parameter, or through a direct JSON body.

The `data` parameter should be an object containing the following key value
pairs:

* `key` - The post meta key to be created. (string) *required*
* `value` - The post meta value for the key provided. (string) *required*

### Response
On a successful creation, a 201 Created status is given, indicating that the
Meta has been created.  The post meta is available canonically from the URL
specified in the Location header.

The new Meta entity is also returned in the body for convienience.


Retrieve Meta for a Post
------------------------

	GET /posts/<id>/meta

### Response
The response is a Meta entity containing all the post_meta for the specified
Post if available.


Retrieve a Meta for a Post
------------------------

	GET /posts/<id>/meta/<mid>

### Response
The response a Meta entity containing the post_meta for the specified Meta and
Post if available.


Edit a Meta for a Post
------------------------

	PUT /posts/<id>/meta/<mid>

### Input
The supplied data should be a Meta object. This data can be submitted via a
regular HTTP multipart body, with the Meta key and value set with the `data`
parameter, or through a direct JSON body.

The `data` parameter should be an array containing the following key value pairs:

* `key` - The post meta key to be updated. (string) *required*
* `value` - The post meta value for the key provided. (string) *required*

### Response
On a successful update, a 200 OK status is given, indicating the post_meta has
been updated. The updated Meta entity is returned in the body.


Delete a Meta for a Post
-------------

	DELETE /posts/<id>/meta/<mid>


### Response
On successful deletion, a 200 OK status code will be returned, indicating
that the post_meta has been permanently deleted.
