Posts
=====

Create a Post
-------------

	POST /posts
  
  Requires [authentication](http://wp-api.org/guides/authentication.html)

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

If the client is not authenticated, a 403 Forbidden response is given.

Retrieve Posts
--------------
The Posts endpoint returns a Post Collection containing a subset of the site's
posts.

	GET /posts

### Input
#### `filter`
The `filter` parameter controls the parameters used to query for posts.

**Note:** Only "public" query variables are available via the API, as not all
query variables are safe to expose. "Private" query variables are also available
when authenticated as a user with `edit_posts`. Other query variables can be
registered via the `query_vars` filter, or `json_query_vars` for API-specific
query variables.

Extended documentation on the query variables is available from
[the codex](http://codex.wordpress.org/Class_Reference/WP_Query).

The following query variables are available to the API:

* `m`
* `p`
* `posts`
* `w`
* `cat`
* `withcomments`
* `withoutcomments`
* `s`
* `search`
* `exact`
* `sentence`
* `calendar`
* `page`
* `paged`
* `more`
* `tb`
* `pb`
* `author`
* `order`
* `orderby`
* `year`
* `monthnum`
* `day`
* `hour`
* `minute`
* `second`
* `name`
* `category_name`
* `tag`
* `feed`
* `author_name`
* `static`
* `pagename`
* `page_id`
* `error`
* `comments_popup`
* `attachment`
* `attachment_id`
* `subpost`
* `subpost_id`
* `preview`
* `robots`
* `taxonomy`
* `term`
* `cpage`
* `post_type`

In addition, the following are available when authenticated as a user with
`edit_posts`:

* `offset`
* `posts_per_page`
* `posts_per_archive_page`
* `showposts`
* `nopaging`
* `post_type`
* `post_status`
* `category__in`
* `category__not_in`
* `category__and`
* `tag__in`
* `tag__not_in`
* `tag__and`
* `tag_slug__in`
* `tag_slug__and`
* `tag_id`
* `post_mime_type`
* `perm`
* `comments_per_page`
* `post__in`
* `post__not_in`
* `post_parent`
* `post_parent__in`
* `post_parent__not_in`

```
GET /posts?filter[posts_per_page]=8&filter[order]=ASC
```

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
  
Requires [authentication](http://wp-api.org/guides/authentication.html)

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

If the client is not authenticated, a 403 Forbidden response is sent.

Delete a Post
-------------

	DELETE /posts/<id>
  
Requires [authentication](http://wp-api.org/guides/authentication.html)

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

If the client is not authenticated, a 403 Forbidden status code will be returned.

Retrieve Revisions for a Post
------------------------

	GET /posts/<id>/revisions
  
Requires [authentication](http://wp-api.org/guides/authentication.html)

### Response
If successful, returns a 200 OK status code and revisions for the given post.

If the client is not authenticated, a 403 Forbidden status code will be returned.

  
Create Meta for a Post
------------------------

	POST /posts/<id>/meta
  
Requires [authentication](http://wp-api.org/guides/authentication.html)

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

If the client is not authenticated, a 403 Forbidden status code will be returned.

Retrieve Meta for a Post
------------------------

	GET /posts/<id>/meta

Requires [authentication](http://wp-api.org/guides/authentication.html)
  
### Response
The response is a Meta entity containing all the post_meta for the specified
Post if available.

Returns a 403 Forbidden status code if the client is not authenticated.

Retrieve a Meta for a Post
------------------------

	GET /posts/<id>/meta/<mid>
  
Requires [authentication](http://wp-api.org/guides/authentication.html)

### Response
The response a Meta entity containing the post_meta for the specified Meta and
Post if available.

Returns a 403 Forbidden status code if the client is not authenticated.

Edit a Meta for a Post
------------------------

	PUT /posts/<id>/meta/<mid>
  
Requires [authentication](http://wp-api.org/guides/authentication.html)

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

If the client is not authenticated, a 403 Forbidden status code is returned.

Delete a Meta for a Post
-------------

	DELETE /posts/<id>/meta/<mid>

Requires [authentication](http://wp-api.org/guides/authentication.html)

### Response
On successful deletion, a 200 OK status code will be returned, indicating
that the post_meta has been permanently deleted.

If the client is not authenticated, a 403 Forbidden status code is returned.

Media
=====


Create an Attachment
--------------------
The Create Attachment endpoint is used to create the raw data for an attachment.
This is a binary object (blob), such as image data or a video.

	POST /media
  
Requires [authentication](http://wp-api.org/guides/authentication.html)

### Input
The attachment creation endpoint can accept data in two forms.

The primary input method accepts raw data POSTed with the corresponding content
type set via the `Content-Type` HTTP header. This is the preferred submission
method.

The secondary input method accepts data POSTed via `multipart/form-data`, as per
[RFC 2388][]. The uploaded file should be submitted with the name field set to
"file", and the filename field set to the relevant filename for the file.

In addition, a `Content-MD5` header can be set with the MD5 hash of the file, to
enable the server to check for consistency errors. If the supplied hash does not
match the hash calculated on the server, a 412 Precondition Failed header will
be issued.

[RFC 2388]: http://tools.ietf.org/html/rfc2388

### Response
On a successful creation, a 201 Created status is given, indicating that the
attachment has been created. The attachment is available canonically from the
URL specified in the Location header.

The new Attachment entity is also returned in the body for convienience.

Returns a 403 Forbidden status code if the client is not authenticated.

Get Attachments
---------------
The Attachments endpoint returns an Attachment collection containing a subset of
the site's attachments.

This endpoint is an extended version of the Post retrieval endpoint.

	GET /media

### Input
#### `fields`
...

### Response
The response is an Attachment entity containing the requested Attachment if
available.


Users
=====


Create a User
-------------

	POST /users
  
Requires [authentication](http://wp-api.org/guides/authentication.html)

### Input
The supplied data should be a User object. This data can be submitted via a
regular HTTP multipart body, with User values set as values to the `data`
parameter, or through a direct JSON body.

That is, the following are equivalent:

Content-Type: application/x-www-form-urlencoded

  data[username]=newuser&data[name]=New%20User&data[password]=secret


Content-Type: application/json

  {"username":"newuser","name":"New User","password":"secret"}

### Response
On a successful creation, a 201 Created status is given, indicating that the
user has been created. The user is available canonically from the URL specified
in the Location header.

The new User entity is also returned in the body for convenience.

A 403 Forbidden status is returned if the client is not authenticated.

Retrieve Users
--------------
The Users endpoint returns a User Collection containing a subset of the site's
users.

	GET /users

Requires [authentication](http://wp-api.org/guides/authentication.html)

  
### Input
#### `filter`
The `filter` parameter controls the query parameters. It is essentially a subset
of the parameters available to [`WP_User_Query`](http://codex.wordpress.org/Class_Reference/WP_User_Query).

The parameter should be an array of the following key/value pairs:

* `number` - Number of users to retrieve, use `-1` for all users. Default
  is set by the site. (integer)
* `offset` - Number of posts to skip. Default is 0. (integer)
* `orderby` - Parameter to search by, as per [`WP_User_Query`](https://codex.wordpress.org/Class_Reference/WP_User_Query#Order_.26_Orderby_Parameters).
  Default is "user_login". (string)
* `order` - Order to sort by. Default is "ASC". (string, "ASC" or "DESC")
* `s` - Keyword to search for. (string)

### Response
The response is a User Collection document containing the requested Users if
available.

A 403 Forbidden status is returned if the client is not authenticated.


Retrieve a User
---------------

	GET /users/<id>
  
Requires [authentication](http://wp-api.org/guides/authentication.html)

### Input
#### `context`
The `context` parameter controls the format of the data to return. The following
contexts are available:

* `view`: The default context. Gives the normal User entity.
* `edit`: Context used for extra fields relevant to updating a user. Includes
  the `extra_capabilities` field; this field contains the capabilities assigned
  to the user themselves, rather than those inherited from their roles. Requires [authentication](http://wp-api.org/guides/authentication.html).
* `embed`: Context used when embedding the response inside another (e.g. post
  author). This is intended as a minimal subset of the user data to reduce
  response size. Excludes `roles` and `capabilities`.

Default is "view". (string)

### Response
The response is a User entity containing the requested User if available. The
fields available on the User depend on the `context` parameter.

A 403 Forbidden status is returned if the client is not authenticated.


Retrieve Current User
-------------

	GET /users/me

Requires [authentication](http://wp-api.org/guides/authentication.html)
  
This endpoint offers a permalink to get the current user, without needing to
know the user's ID.

### Input
#### `context`
The `context` parameter controls the format of the data to return. See the
Retrieve a User endpoint for available contexts.

Default is "view". (string)

### Response
If the client is currently logged in, a 302 Found status is given. The User is
available canonically from the URL specified in the Location header.

The User entity containing the current User is also returned in the body for
convenience. The fields available on the User depend on the `context` parameter.

If the client is not logged in, a 403 Forbidden status is given.


Edit a User
-----------

	PUT /users/<id>

Requires [authentication](http://wp-api.org/guides/authentication.html)
  
For compatibility reasons, this endpoint also accepts the POST and PATCH
methods. Both of these methods have the same behaviour as using PUT. It is
recommended to use PUT if available to fit with REST convention.

### Input
The supplied data should be a User object. This data can be submitted via a
regular HTTP multipart body, with User values set as values to the `data`
parameter, or through a direct JSON body. See the Create User endpoint for an
example.

### Response
On a successful update, a 200 OK status is given, indicating the user has been
updated. The updated User entity is returned in the body.

If the client is not logged in, a 403 Forbidden status is given.

Delete a User
-------------

	DELETE /users/<id>
  
Requires [authentication](http://wp-api.org/guides/authentication.html)
  
### Input
#### `force`
The `force` parameter controls whether the user is permanently deleted or not.
By default, this is set to false, indicating that the user will be sent to an
intermediate storage (such as the trash) allowing it to be restored later. If
set to true, the user will not be able to be restored.

Default is false. (boolean)

#### `reassign`
The `reassign` parameter controls whether the deleted user's content is
reassigned to a new User or not. If set to `null`, the deleted user's content
will not be reassigned.

Default is null. (integer)


### Response
On successful deletion, a 202 Accepted status code will be returned, indicating
that the user has been moved to the trash for permanent deletion at a
later date.

If force was set to true, a 200 OK status code will be returned instead,
indicating that the user has been permanently deleted.

If the client is not authenticated, a 403 Forbidden status is given.

Taxonomies
==========


Retrieve All Taxonomies
-----------------------
The Taxonomies endpoint returns a collection containing objects for each of the
site's registered taxonomies.

	GET /taxonomies


### Response
The response is a collection document containing all registered taxonomies.


Retrieve a Taxonomy
-------------------

	GET /taxonomies/<taxonomy>

### Response
The response is a Taxonomy entity containing the requested Taxonomy, if available.


Retrieve Terms for a Taxonomy
-----------------------------

	GET /taxonomies/<taxonomy>/terms

### Response
The response is a collection of taxonomy terms for the specified Taxonomy, if
available.

Retrieve a Taxonomy Term
------------------------

	GET /taxonomies/<taxonomy>/terms/<id>

### Response
The response is a Taxonomy entity object containing the Taxonomy with the
requested ID, if available.
