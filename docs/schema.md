Introduction
============
The API is designed around two types of responses: entities, and collections.
Entities are JSON objects representing internal objects, both abstract and
WordPress objects. Collections are JSON arrays of Entities.

This document is for clients and providers wanting to ensure full compliance
with the specification.


Defintions
==========
The key words "MUST", "MUST NOT", "REQUIRED", "SHALL", "SHALL NOT", "SHOULD",
"SHOULD NOT", "RECOMMENDED", "MAY", and "OPTIONAL" in this document are to be
interpreted as described in [RFC2119][].

* Provider: A site making the API available for use
* Consumer: An application accessing and interacting with the API
* slug: A URL-friendly human-readable identifier, usually derived from the title
		of the entity.

[RFC2119]: http://tools.ietf.org/html/rfc2119


### ABNF
Augmented Backus-Naur Form (ABNF) is to be interpreted as described in
[RFC5234][]. In addition, the following basic rules are used to describe basic
parsing constructs above the standard JSON parsing rules.

	token = 1*<any OCTET except CTLs> ; DQUOTE must be escaped with "\"

Note that as per ABNF, literal strings are case insensitive. That is:

	example-field = "id"
	example-field = "ID"

Providers SHOULD use the capitalisation as per this specification to ensure
maximum compatibility with consumers. Consumers SHOULD ignore the case of
literal strings when parsing data.

[RFC5234]: http://tools.ietf.org/html/rfc5234


Entities
========

Index
-----
The Index entity is a JSON object with site properties. The following properties
are defined for the Index entity object.

### `name`
The `name` field is a string with the site's name.

### `description`
The `description` field is a string with the site's description.

### `URL`
The `URL` field is a string with the URL to the site itself.

### `routes`
The `routes` field is an object with keys as a route and the values as a route
descriptor.

The route is a string giving the URL template for the route, relative to the API
root. The template contains URL parts separated by forward slashes, with each
URL part either a static string, or a route variable encased in angle brackets.

	route            = ( "/"
					 / *( "/" ( token / route-variable ) ) )
	route-variable   = "<" token ">"

These routes can be converted into URLs by replacing all route variables with
their relevant values, then concatenating the relative URL to the API base.

The route descriptor is an object with the following defined properties.

* `supports`: A JSON array of supported HTTP methods (verbs). Possible values
  are "HEAD", "GET", "POST", "PUT", "PATCH", "DELETE"
* `accepts_json`: A boolean indicating whether data can be passed directly via a
  POST request body. Default for missing properties is false.
* `meta`: An Entity Meta entity. Typical `links` values consist of a `self` link
  pointing to the route's full URL.

### `meta`
The `meta` field is a Entity Meta entity with metadata relating to the entity
representation.

Typical `links` values for the meta object consist of a `help` key with the
value indicating a human-readable documentation page about the API.

### Example

	{
		"name": "My WordPress Site",
		"description": "Just another WordPress site",
		"URL": "http:\/\/example.com",
		"routes": {
			"\/": {
				"supports": [
					"HEAD",
					"GET"
				],
				"meta": {
					"self": "http:\/\/example.com\/wp-json.php\/"
				}
			},
			"\/posts": {
				"supports": [
					"HEAD",
					"GET",
					"POST"
				],
				"meta": {
					"self": "http:\/\/example.com\/wp-json.php\/posts"
				},
				"accepts_json": true
			},
			"\/posts\/<id>": {
				"supports": [
					"HEAD",
					"GET",
					"POST",
					"PUT",
					"PATCH",
					"DELETE"
				],
				"accepts_json": true
			},
			"\/posts\/<id>\/revisions": {
				"supports": [
					"HEAD",
					"GET"
				]
			},
			"\/posts\/<id>\/comments": {
				"supports": [
					"HEAD",
					"GET",
					"POST"
				],
				"accepts_json": true
			},
			"\/posts\/<id>\/comments\/<comment>": {
				"supports": [
					"HEAD",
					"GET",
					"POST",
					"PUT",
					"PATCH",
					"DELETE"
				],
				"accepts_json": true
			},
		},
		"meta": {
			"links": {
				"help": "https:\/\/github.com\/rmccue\/WP-API",
				"profile": "https:\/\/raw.github.com\/rmccue\/WP-API\/master\/docs\/schema.json"
			}
		}
	}

Post
----
The Post entity is a JSON object of post properties. Unless otherwise defined,
properties are available in all contexts. The following properties are defined
for the Post entity object:

### `title`
The `title` field is a string with the post's title.

### `date`, `date_gmt`
The `date` and `date_gmt` fields are strings with the post's creation date and
time in the local time and UTC respectively. These fields follow the [RFC3339][]
Section 5.6 datetime representation.

	date     = date-time
	date_gmt = date-time

[RFC3339]: http://tools.ietf.org/html/rfc3339

### `modified`, `modified_gmt`
The `date` and `date_gmt` fields are strings with the post's last modification
date and time in the local time and UTC respectively. These fields follow the
[RFC3339][] Section 5.6 datetime representation.

	modified     = date-time
	modified_gmt = date-time

### `date_tz`, `modified_tz`
The `date_tz` and `modified_tz` fields are strings with the timezone applying to
the `date` and `modified` fields respectively. The timezone is a [Olsen zoneinfo
database][] identifier. While the `date` and `modified` fields include timezone
offset information, the `date_tz` and `modified_tz` fields allow proper data
operations across Daylight Savings Time boundaries.

Note that in addition to the normal Olsen timezones, manual offsets may be
given. These manual offsets use the deprecated `Etc/GMT+...` zones and specify
an integer offset in hours from UTC.

	timezone      = Olsen-timezone / manual-offset
	manual-offset = "Etc/GMT" ("-" / "+") 1*2( DIGIT )

Consumers SHOULD use the fields if they perform mathematical operations on the
`date` and `modified` fields (such as adding an hour to the last modification
date) rather than relying on the `time-offset` in the `date` or
`modified` fields.

[Olsen zoneinfo database]: https://en.wikipedia.org/wiki/Tz_database

### `status`
The `status` field is a string with the post's status. This status relates to
where the post is in the editorial process. These are usually set values, but
some providers may have extra post statuses.

	post-status = "draft" / "pending" / "private" / "publish" / "trash" / token

Consumers who encounter an unknown or missing post status SHOULD treat it the
same as a "draft" status.

### `type`
The `type` field is a string with the post's type. This field is specific to
providers, with the most basic representation being "post". The type of the
post usually relates to the fields in the Post entity, with other types having
additional fields specific to the type.

	post-type = "post" / token

Consumers who encounter an unknown or missing post type SHOULD treat it the same
as a "post" type.

### `name`
The `name` field is a string with the post's slug.

### `author`
The `author` field is a User entity with the user who created the post.

### `password`
The `password` field is a string with the post's password. A zero-length
password indicates that the post does not have a password.

Consumers who encounter a missing password MUST treat it the same as a
zero-length password.

### `content`
The `content` field is a string with the post's content.

### `excerpt`
The `excerpt` field is a string with the post's excerpt. This is usually a
shortened version of the post content, suitable for displaying in
collection views.

Consumers who encounter a missing excerpt MAY present a shortened version of the
`content` field instead.

### `content_raw`, `excerpt_raw`
The `content_raw` and `excerpt_raw` fields are strings with the post's content
and excerpt respectively. Unlike the `content` and `excerpt` fields, the value
has not been passed through internal filtering, and is suitable for editing.

(Context Availability: `edit`)

### `parent`
The `parent` field is an integer or JSON object with the post's parent
post ID. A literal zero indicates that the post does not have a parent
post.

	post-parent = "0" / 1*DIGIT

Consumers who encounter a missing parent ID MUST treat it the same as a parent
post ID of 0.

Parent fields will be expanded into a full Post entity in the `view` or `edit`
contexts, but only one level deep. The embedded Post entity will be rendered
using the `parent` context.

In the `parent` context, the field will contain an integer with the post's
parent post ID as above.

### `link`
The `link` field is a string with the full URL to the post's canonical view.
This is typically the human-readable location of the entity.

### `guid`
The `guid` field is a string with the post's globally unique identifier (GUID).

The GUID is typically in URL form, as this is a relatively easy way of ensuring
that the GUID is globally unique. However, consumers MUST NOT treat the GUID as
a URL, and MUST treat the GUID as a string of arbitrary characters.

### `menu_order`
The `menu_order` field is an integer with the post's sorting position. This is
typically used to affect sorting when displaying the post in menus or lists.
Larger integers should be treated as sorting before smaller integers.

	menu-order = 1*DIGIT / "-" 1*DIGIT

Consumers who encounter a missing sorting position MUST treat it the same as a
sorting position of 0.

### `comment_status`
The `comment_status` field is a string with the post's current commenting
status. This field indicates whether users can submit comments to the post.

	post-comment-status = "open" / "closed" / token

Providers MAY use statuses other than "open" or "closed" to indicate other
statuses. Consumers who encounter an unknown or missing comment status SHOULD
treat it as "closed".

### `ping_status`
The `ping_status` field is a string with the post's current pingback/trackback
status. This field indicates whether users can submit pingbacks or trackbacks
to the post.

	ping-status = "open" / "closed" / token

Providers MAY use statuses other than "open" or "closed" to indicate other
statuses. Consumers who encounter an unknown or missing ping status SHOULD treat
it as "closed".

### `sticky`
The `sticky` field is a boolean indicating whether the post is marked as a
sticky post. Consumers typically display sticky posts before other posts in
collection views.

### `post_thumbnail`
The `post_thumbnail` field is a Media entity.

### `post_format`
The `post_format` field is a string with the post format. The post format
indicates how some meta fields should be displayed. For example, posts with the
"link" format may wish to display an extra link to a URL specified in a meta
field or emphasise a link in the post content.

	post-format = "standard" / "aside" / "gallery" / "image" / "link" / "status"

Providers MUST NOT use post formats not specified by this specification, unless
specified in a subsequent version of the specification. Consumers MUST treat
unknown post formats as "standard".

### `terms`
The `terms` field is a Term collection.

### `post_meta`
The `meta` field is a Metadata entity with metadata relating to the post.

### `meta`
The `meta` field is a Entity Meta entity with metadata relating to the entity
representation.

### Example

	{
		"ID": 1,
		"title": "Hello world!q",
		"status": "publish",
		"type": "post",
		"author": {
			"ID": 1,
			"name": "admin",
			"slug": "admin",
			"URL": "",
			"avatar": "http:\/\/0.gravatar.com\/avatar\/c57c8945079831fa3c19caef02e44614&d=404&r=G",
			"meta": {
				"links": {
					"self": "http:\/\/example.com\/wp-json.php\/users\/1",
					"archives": "http:\/\/example.com\/wp-json.php\/users\/1\/posts"
				}
			},
			"first_name": "",
			"last_name": ""
		},
		"content": "<p>Welcome to WordPress. This is your first post. Edit or delete it, then start blogging!<\/p>\n",
		"parent": 0,
		"link": "http:\/\/example.com\/2013\/06\/02\/hello-world\/",
		"date": "2013-06-02T05:28:00+10:00",
		"modified": "2013-06-30T13:56:57+10:00",
		"format": "standard",
		"slug": "hello-world",
		"guid": "http:\/\/example.com\/?p=1",
		"excerpt": "",
		"menu_order": 0,
		"comment_status": "open",
		"ping_status": "open",
		"sticky": false,
		"date_tz": "Australia\/Brisbane",
		"date_gmt": "2013-06-02T05:28:00+00:00",
		"modified_tz": "Australia\/Brisbane",
		"modified_gmt": "2013-06-30T03:56:57+00:00",
		"password": "",
		"post_meta": [
		],
		"meta": {
			"links": {
				"self": "http:\/\/example.com\/wp-json.php\/posts\/1",
				"author": "http:\/\/example.com\/wp-json.php\/users\/1",
				"collection": "http:\/\/example.com\/wp-json.php\/posts",
				"replies": "http:\/\/example.com\/wp-json.php\/posts\/1\/comments",
				"version-history": "http:\/\/example.com\/wp-json.php\/posts\/1\/revisions"
			}
		},
		"featured_image": null,
		"terms": {
			"category": {
				"ID": 1,
				"name": "Uncategorized",
				"slug": "uncategorized",
				"parent": null,
				"count": 7,
				"meta": {
					"links": {
						"collection": "http:\/\/example.com\/wp-json.php\/posts\/types\/post\/taxonomies\/category\/terms",
						"self": "http:\/\/example.com\/wp-json.php\/posts\/types\/post\/taxonomies\/category\/terms\/1"
					}
				}
			}
		}
	}



Entity Meta
-----------
The Entity Meta entity is a JSON object with custom metadata relating to the
representation of the parent entity.

The following properties are defined for the Entity Meta entity object:

### `links`
The `links` field is a JSON object with hyperlinks to related entities. Each
item's key is a link relation as per the [IANA Link Relations registry][] with
the value of the item being the corresponding link URL.

Typical link relations are:

* `self`: A URL pointing to the current entity's location.
* `up`: A URL pointing to the parent entity's location.
* `collection`: A URL pointing to a collection that the entity is a member of.

[IANA Link Relations registry]: http://www.iana.org/assignments/link-relations/link-relations.xml


User
----
The User entity is a JSON object with user properties. The following properties
are defined for the User entity object:

### `ID`
The `ID` field is an integer with the user's ID.

### `name`
The `name` field is a string with the user's display name.

### `slug`
The `slug` field is a string with the user's slug.

### `URL`
The `URL` field is a string with the URL to the author's site. This is typically
an external link of the author's choice.

### `avatar`
The `avatar` field is a string with the URL to the author's avatar image.

Providers SHOULD ensure that for users without an avatar image, this field is
either zero-length or the URL returns a HTTP 404 error code on access. Consumers
MAY display a default avatar instead of a zero-length or URL which returns
a HTTP 404 error code.

### `meta`
The `meta` field is a Entity Meta entity with metadata relating to the entity
representation.


Metadata
--------
The Metadata entity is a JSON array with metadata fields. Each metadata field is
a JSON object with `id`, `key` and `value` fields.

### `id`
The `id` field of the metadata field is a positive integer with the internal
metadata ID.

### `key`
The `key` field of the metadata field is a string with the metadata field name.

### `value`
The `value` field of the metadata field is a string with the metadata
field value.


Comment
-------
The Comment entity is a JSON object with comment properties. The following
properties are defined for the Comment entity object:

### `ID`
The `ID` field is an integer with the comment's ID.

### `content`
The `content` field is a string with the comment's content.

### `status`
The `status` field is a string with the comment's status. This field indicates
whether the comment is in the publishing process, or if it has been deleted or
marked as spam.

	comment-status = "hold" / "approved" / "spam" / "trash" / token

Providers MAY use other values to indicate other statuses. Consumers who
encounter an unknown or missing status SHOULD treat it as "hold".

### `type`
The `type` field is a string with the comment's type. This is usually one of the
following, but providers may provide additional values.

	comment-type = "comment" / "trackback" / "pingback" / token

Providers MAY use other values to indicate other types. Consumers who encounter
an unknown or missing status SHOULD treat it as "comment".

### `post`
The `post` field is an integer with the parent post for the comment, or a Post
entity describing the parent post. A literal zero indicates that the comment
does not have a parent post.

	comment-post-parent = "0" / 1*DIGIT

Consumers who encounter a missing post ID MUST treat it the same as a parent
post ID of 0.

### `parent`
The `post` field is an integer with the parent comment, or a Comment entity
describing the parent comment. A literal zero indicates that the comment does
not have a parent comment.

	comment-parent = "0" / 1*DIGIT

Consumers who encounter a missing parent ID MUST treat it the same as a parent
comment ID of 0.

### `author`
The `author` field is a User entity with the comment author's data, or a
User-like object for anonymous authors. The User-like object contains the
following properties:

#### `ID`
The `ID` property on the User-like object is always set to `0` for anonymous
authors.

#### `name`
The `name` property on the User-like object is a string with the author's name.

#### `URL`
The `URL` property on the User-like object is a string with the author's URL.

#### `avatar`
The `avatar` property on the User-like object is a string with the URL to the
author's avatar image.

This property should be treated the same as the avatar property on the
User entity.


### `date`, `date_gmt`
The `date` and `date_gmt` fields are strings with the post's creation date and
time in the local time and UTC respectively. These fields follow the [RFC3339][]
Section 5.6 datetime representation.

	date     = date-time
	date_gmt = date-time

This field should be treated the same as the `date` and `date_gmt` properties on
a Post entity.

[RFC3339]: http://tools.ietf.org/html/rfc3339

### `date_tz`, `modified_tz`
The `date_tz` and `modified_tz` fields are strings with the timezone applying to
the `date` and `modified` fields respectively. The timezone is a [Olsen zoneinfo
database][] identifier. While the `date` field includes timezone offset
information, the `date_tz` field allows proper data operations across Daylight
Savings Time boundaries.

This field should be treated the same as the `date_tz` property on a
Post entity.


Media
-----
The Media entity is a JSON object based on the Post entity. It contains all
properties of the Post entity, with the following additional properties defined:

### `source`
The `source` field is a string with the URL of the entity's original file. For
image media, this is the source file that intermediate representations are
generated from. For non-image media, this is the attached media file itself.

### `is_image`
The `is_image` field is a boolean which indicates whether the entity's
associated file should be handled as an image.

### `attachment_meta`
The `attachment_meta` field is a Media Meta entity. If the file is not an image
(as indicated by the `is_image` field), this is an empty JSON object.


Media Meta
----------
The Media Meta entity is a JSON object with properties relating to the
associated Media entity. The following properties are defined for the entity:

### `width`
The `width` field is an integer with the original file's width in pixels.

### `height`
The `height` field is an integer with the original file's height in pixels.

### `file`
The `file` field is a string with the path to the original file, relative to the
site's upload directory.

### `sizes`
The `sizes` field is a JSON object mapping intermediate image sizes to image
data objects. The key of each item is the size of the intermediate image as an
internal string representation. The value of each item has the following
properties defined.

* `file`: The filename of the intermediate file, relative to the directory of
  the original file.
* `width`: The width of the intermediate file in pixels.
* `height`: The height of the intermediate file in pixels.
* `mime-type`: The MIME type of the intermediate file.
* `url`: The full URL to the intermediate file.

### `image_meta`
The `image_meta` field is a JSON object mapping image meta properties to their
values. This data is taken from the EXIF data on the original image. The
following properties are defined.

* `aperture`: The aperture used to create the original image as a decimal number
  (with two decimal places).
* `credit`: Credit for the original image.
* `camera`: The camera used to create the original image.
* `created_timestamp`: When the file was created, as a Unix timestamp.
* `copyright`: Copyright for the original image.
* `focal_length`: The focal length used to create the original image as a
  decimal string.
* `iso`: The ISO used to create the original image.
* `shutter_speed`: The shutter speed used to create the original image, as a
  decimal string.
* `title`: The original title of the image.


Documents
=========

Index
-----
The Index document is the root endpoint for the API server and describes the
contents and abilities of the API server.

### Body
The body of an Index document is an Index entity.

### Example

	{
		"name":"My WordPress Site",
		"description":"Just another WordPress site",
		"URL":"http:\/\/example.com",
		"routes": {
			"\/": {
				"supports": [ "HEAD", "GET" ]
			},
			"\/posts": {
				"supports": [ "HEAD", "GET", "POST" ],
				"accepts_json": true
			},
			"\/posts\/<id>": {
				"supports": [ "HEAD", "GET", "POST", "PUT", "PATCH", "DELETE" ]
			},
			"\/posts\/<id>\/revisions": {
				"supports": [ "HEAD", "GET" ]
			},
			"\/posts\/<id>\/comments": {
				"supports": [ "HEAD", "GET", "POST" ],
				"accepts_json":true
			}
		},
		"meta": {
			"links": {
				"help":"http:\/\/codex.wordpress.org\/JSON_API"
			}
		}
	}


Post
----
A Post document is defined as the representation of a post item, analogous to an
Atom item.

### Headers
The following headers are sent when a Post is the main entity:

* `Link`:
	* `rel="alternate"; type=text/html`: The permalink for the Post
	* `rel="collection"`: The endpoint of the Post Collection the Post is
	  contained in
	* `rel="replies"`: The endpoint of the associated Comment Collection
	* `rel="version-history"`: The endpoint of the Post Collection containing
	  the revisions of the Post


### Body
The body of a Post document is a Post entity.


### Example

	HTTP/1.1 200 OK
	Date: Mon, 07 Jan 2013 03:35:14 GMT
	Last-Modified: Mon, 07 Jan 2013 03:35:14 GMT
	Link: <http://localhost/wptrunk/?p=1>; rel="alternate"; type=text/html
	Link: <http://localhost/wptrunk/wp-json.php/users/1>; rel="author"
	Link: <http://localhost/wptrunk/wp-json.php/posts>; rel="collection"
	Link: <http://localhost/wptrunk/wp-json.php/posts/158/comments>; rel="replies"
	Link: <http://localhost/wptrunk/wp-json.php/posts/158/revisions>; rel="version-history"
	Content-Type: application/json; charset=UTF-8

	{
		"ID":158,
		"title":"This is a test!",
		"status":"publish",
		"type":"post",
		"author":{
			"ID":1,
			"name":"admin",
			"slug":"admin",
			"URL":"",
			"avatar":"http:\/\/0.gravatar.com\/avatar\/c57c8945079831fa3c19caef02e44614&d=404&r=G",
			"meta":{
				"links":{
					"self":"http:\/\/localhost\/wptrunk\/wp-json.php\/users\/1",
					"archives":"http:\/\/localhost\/wptrunk\/wp-json.php\/users\/1\/posts"
				}
			}
		},
		"content":"Hello.\r\n\r\nHah.",
		"parent":0,
		"link":"http:\/\/localhost\/wptrunk\/158\/this-is-a-test\/",
		"date":"2013-01-07T13:35:14+10:00",
		"modified":"2013-01-07T13:49:40+10:00",
		"format":"standard",
		"slug":"this-is-a-test",
		"guid":"http:\/\/localhost\/wptrunk\/?p=158",
		"excerpt":"",
		"menu_order":0,
		"comment_status":"open",
		"ping_status":"open",
		"sticky":false,
		"date_tz":"Australia\/Brisbane",
		"date_gmt":"2013-01-07T03:35:14+00:00",
		"modified_tz":"Australia\/Brisbane",
		"modified_gmt":"2013-01-07T03:49:40+00:00",
		"post_thumbnail":[],
		"terms":{
			"category":{
				"ID":1,
				"name":"Uncategorized",
				"slug":"uncategorized",
				"group":0,
				"parent":0,
				"count":4,
				"meta":{
					"links":{
						"collection":"http:\/\/localhost\/wptrunk\/wp-json.php\/taxonomy\/category",
						"self":"http:\/\/localhost\/wptrunk\/wp-json.php\/taxonomy\/category\/terms\/1"
					}
				}
			}
		},
		"post_meta":[],
		"meta":{
			"links":{
				"self":"http:\/\/localhost\/wptrunk\/wp-json.php\/posts\/158",
				"author":"http:\/\/localhost\/wptrunk\/wp-json.php\/users\/1",
				"collection":"http:\/\/localhost\/wptrunk\/wp-json.php\/posts",
				"replies":"http:\/\/localhost\/wptrunk\/wp-json.php\/posts\/158\/comments",
				"version-history":"http:\/\/localhost\/wptrunk\/wp-json.php\/posts\/158\/revisions"
			}
		}
	}


Post Collection
---------------
A Post Collection document is defined as a collection of Post entities.

### Headers
The following headers are sent when a Post Collection is the main entity:

* `Link`:
	* `rel="item"` - Each item in the collection has a corresponding Link header
	  containing the location of the endpoint for that resource.


### Body
The Post Collection document is a JSON array of Post entities.


User
----
The User document describes a member of the site.

### Body
The body of a User document is a User entity.


Appendix A: JSON Schema
=======================
The JSON Schema describing the entities in this document is available in
schema.json.
