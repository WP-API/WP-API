Notes
=====

### Inputting data as an "array"
Endpoints may allow passing in an array of data, typically used for querying
entities. URL semantics do not specify how to pass this, however the convention
used by PHP and this API is to pass them with the array name concatenated with
the key name in square brackets.

For example, a map of key to value (associative arrays) would look like:

	filter[post_status]=draft&filter[s]=foo

This is equivalent to the following PHP representation:

```php
$filter = array(
	'post_status' => 'draft',
	's' => 'foo'
);
```

Or in Javascript:

```js
var filter = {
	'post_status': 'draft',
	's': 'foo',
};
```

For lists (numeric arrays), the same syntax is used but with empty key values:

	type[]=post&type[]=page

This is equivalent to the following PHP representation:

```php
$type = array(
	'post',
	'page'
);
```

Or in Javascript:

```js
var type = [
	'post',
	'page'
];
```

These can be combined; for example, using `post__in`:

	filter[post__in][]=1&filter[post__in][]=4&filter[post__in][]=5

This is equivalent to the following PHP representation:

```php
$filter = array(
	'post__in' => array(
		1,
		4,
		5
	)
);
```

Or in Javascript:

```js
var filter = {
	'post__in': [
		1,
		4,
		5
	]
}
```


### JSON data input
Some posts allow directly passing JSON data (usually an entity) via the request
body. These should be specified with a Content-Type header of `application/json`
although individual endpoints may prefer more specific types.

If your client platform does not support native JSON encoding, the data can be
submitted via a regular HTTP multipart body, with properties set as values to
the `data` parameter.

That is, the following are equivalent:

Content-Type: application/x-www-form-urlencoded

	data[title]=Hello%20World!&data[content_raw]=Content&data[excerpt_raw]=Excerpt


Content-Type: application/json

	{"title":"Hello World!","content_raw":"Content","excerpt_raw":"Excerpt"}


### HTTP method compatibility
Due to their relatively new nature, some methods such as PATCH may not be
supported by client software. To emulate support for this, a `_method` parameter
may be passed via the URL with the value set to a valid HTTP method (DELETE,
GET, HEAD, PATCH, POST, PUT, DELETE). Note that this must be passed via the URL
and cannot be passed in the HTTP body.
