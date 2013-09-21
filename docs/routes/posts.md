Posts
=====

Retrieve Posts
--------------
The Posts endpoint returns a Post Collection containing a subset of the site's
posts.

	GET /posts

### Input
#### `filter`
The `filter` parameter controls the query parameters. It is essentially a subset
of the parameters available to [`WP_Query`](http://codex.wordpress.org/Class_Reference/WP_Query).

The parameter should be an array of the following key/value pairs:

* `post_status` - Comma-separated list of [status
  values](http://codex.wordpress.org/Class_Reference/WP_Query#Status_Parameters).
  Default is "publish". (string)
* `numberposts` - Number of posts to retrieve, use `-1` for all posts. Default
  is set by the site. (integer)
* `offset` - Number of posts to skip. Default is 0. (integer)
* `orderby` - Parameter to search by, as per [WP Query](http://codex.wordpress.org/Class_Reference/WP_Query#Order_.26_Orderby_Parameters).
  Default is "date". (string)
* `order` - Order to sort by. Default is "DESC". (string, "ASC" or "DESC")
* `s` - Keyword to search for. (string)


#### `fields`
...


#### `type`
The `type` parameter specifies the post type to retrieve. Default is "post".
(string)


### Response
The response is a Post Collection document containing the requested Posts if
available.


Create a Post
-------------

	POST /posts

### Input
The supplied data should be a Post object. This data can be submitted via a
regular HTTP multipart body, with Post values set as values to the `data`
parameter, or through a direct JSON body.

That is, the following are equivalent:

Content-Type: application/x-www-form-urlencoded

	data[post_title]=Hello%20World!&data[post_content]=Content


Content-Type: application/json

	{"post_title":"Hello World!","post_content":"Content"}

### Response
On a successful creation, a 201 Created status is given, indicating that the
post has been created. The post is available canonically from the URL specified
in the Location header.

The new Post entity is also returned in the body for convienience.


Retrieve a Post
---------------

	GET /posts/<id>

### Input
#### `fields`
...

### Response
The response is a Post entity containing the requested Post if available.


Edit a Post
-----------

	PUT /posts/<id>

For compatibility reasons, this endpoint also accepts the POST and PATCH
methods. Both of these methods have the same behaviour as using PUT. It is
recommended to use PUT if available to fit with REST convention.

### Input
The supplied data should be a Post object. This data can be submitted via a
regular HTTP multipart body, with Post values set as values to the `data`
parameter, or through a direct JSON body. See the Create Post endpoint for an
example.

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