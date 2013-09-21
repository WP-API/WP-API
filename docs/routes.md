Notes
=====

### Inputting data as an "array"
Endpoints may allow passing in an array of data, typically used for querying
entities. URL semantics do not specify how to pass this, however the convention
used by PHP and this API is to pass them with the array name concatenated with
the key name in square brackets.

For example:

	filter[post_status]=draft&filter[s]=foo


### JSON data input
Some posts allow directly passing JSON data (usually an entity) via the request
body. These should be specified with a Content-Type header of `application/json`
although individual endpoints may prefer more specific types.

If your client platform does not support native JSON encoding, the data can be
submitted via a regular HTTP multipart body, with properties set as values to
the `data` parameter.

That is, the following are equivalent:

Content-Type: application/x-www-form-urlencoded

	data[post_title]=Hello%20World!&data[post_content]=Content


Content-Type: application/json

	{"post_title":"Hello World!","post_content":"Content"}


### HTTP method compatibility
Due to their relatively new nature, some methods such as PATCH may not be
supported by client software. To emulate support for this, a `_method` parameter
may be passed via the URL with the value set to a valid HTTP method (DELETE,
GET, HEAD, PATCH, POST, PUT, DELETE). Note that this must be passed via the URL
and cannot be passed in the HTTP body.
