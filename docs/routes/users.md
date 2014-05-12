Users
=====

Retrieve Users
--------------
The Users endpoint returns a User Collection containing a subset of the site's
users.

	GET /users

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


Create a User
-------------

	POST /users

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

The new User entity is also returned in the body for convienience.


Retrieve a User
---------------

	GET /users/<id>

### Input
#### `context`
The `context` parameter controls the format of the data to return. The following
contexts are available:

* `view`: The default context. Gives the normal User entity.
* `edit`: Context used for extra fields relevant to updating a user. Includes
  the `extra_capabilities` field; this field contains the capabilities assigned
  to the user themselves, rather than those inherited from their roles.
* `embed`: Context used when embedding the response inside another (e.g. post
  author). This is intended as a minimal subset of the user data to reduce
  response size. Excludes `roles` and `capabilities`.

Default is "view". (string)

### Response
The response is a User entity containing the requested User if available. The
fields available on the User depend on the `context` parameter.


Retrieve Current User
-------------

	GET /users/me

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

If the client is not logged in, a 401 Unauthorized status is given.


Edit a User
-----------

	PUT /users/<id>

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


Delete a User
-------------

	DELETE /users/<id>

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

