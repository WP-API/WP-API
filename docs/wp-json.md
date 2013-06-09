Introduction
============
The API is designed around two types of responses: entities, and collections.
Entities are JSON objects representing internal objects, both abstract and
WordPress objects. Collections are JSON arrays of Entities.


Documents
=========

General
-------

	date          = 1*DIGIT
	boolean       = "true" | "false"
	timezone      = quoted-string

Index
-----
The Index entity is the root endpoint for the API server and describes the
contents and abilities of the API server.

### Entity
The Index entity is a JSON object of site properties. The following properties
are defined for the Index entity object:

#### ABNF

	Index            = "{" index-field *( "," index-field ) "}"
	index-field      = ( ( DQUOTE "name" DQUOTE ":" quoted-string )
	                 | ( DQUOTE "description" DQUOTE ":" quoted-string )
	                 | ( DQUOTE "URL" DQUOTE ":" DQUOTE URI DQUOTE )
	                 | ( DQUOTE "routes" DQUOTE ":" route-map )
	                 | ( DQUOTE "meta" DQUOTE ":" meta-map ) )
	route-map        = "{" route ":" route-descriptor
	                 *( "," route ":" route-descriptor ) "}"
	route            = DQUOTE ( "/"
	                 | *( "/" ( token | route-variable ) ) ) DQUOTE
	route-variable   = "<" token ">"
	route-descriptor = "{" route-property *( "," route-property ) "}"
	route-property   = ( ( DQUOTE "supports" DQUOTE ":" "[" method *( "," method ) "]" )
	                 | ( DQUOTE "accepts_json" DQUOTE ":" boolean ) )
	method           = DQUOTE ( "HEAD" | "GET" | "POST" | "PUT" | "PATCH" | "DELETE" ) DQUOTE

### JSON Schema

	{
		"$schema": "http://json-schema.org/schema#",
		"id": "http://wp-api.ryanmccue.info/schema#/definitions/index",
		"title": "Index",

		"type": "object",
		"properties": {
			"name": {
				"type": "string",
				"description": "The site's name"
			},
			"description": {
				"type": "string",
				"description": "The site's description"
			},
			"URL": {
				"type": "string",
				"description": "The location of the site"
			},
			"routes": {
				"type": "object",
				"description": "The URL patterns that are handled by the API",
				"patternProperties": {
					".+": {
						"type": "object",
						"description": "A single route description",
						"properties": {
							"supports": {
								"type": "array",
								"description": "The HTTP methods supported by the endpoint",
								"items": {
									"enum": [
										"HEAD",
										"GET",
										"POST",
										"PUT",
										"PATCH",
										"DELETE"
									]
								}
							},
							"accepts_json": {
								"type": "boolean",
								"description": "Whether the endpoint accepts a raw JSON POST body for data",
								"default": false
							}
						},
						"required": ["supports"]
					}
				}
			},
			"meta": {
				"type": "object",
				"description": "Metadata for the Index entity"
			}
		},

		"required": ["name", "description", "URL", "routes", "meta"]
	}

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
A Post entity is defined as the representation of a post item, analogous to an
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


### Entity
The Post entity is a JSON object of post properties. The following properties
are defined for the Post entity object:

#### ABNF
	Post           = "{" post-field *( "," post-field ) "}"
	post-field     = ( ( "ID" ":"  [ "-" ] 1*DIGIT )
	               | ( DQUOTE "title" DQUOTE ":" quoted-string )
	               | ( DQUOTE "date" DQUOTE ":" date )
	               | ( DQUOTE "date_tz" DQUOTE ":" timezone )
	               | ( DQUOTE "date_gmt" DQUOTE ":" date )
	               | ( DQUOTE "modified" DQUOTE ":" date )
	               | ( DQUOTE "modified_tz" DQUOTE ":" timezone )
	               | ( DQUOTE "modified_gmt" DQUOTE ":" date )
	               | ( DQUOTE "status" DQUOTE ":" DQUOTE post-status DQUOTE )
	               | ( DQUOTE "type" DQUOTE ":" DQUOTE post-type DQUOTE )
	               | ( DQUOTE "name" DQUOTE ":" quoted-string )
	               | ( DQUOTE "author" DQUOTE ":" ( 1*DIGIT | User ) )
	               | ( DQUOTE "password" DQUOTE ":" quoted-string )
	               | ( DQUOTE "excerpt" DQUOTE ":" quoted-string )
	               | ( DQUOTE "content" DQUOTE ":" quoted-string )
	               | ( DQUOTE "parent" DQUOTE ":" ( 1*DIGIT | Post ) )
	               | ( DQUOTE "link" DQUOTE ":" URI )
	               | ( DQUOTE "guid" DQUOTE ":" quoted-string )
	               | ( DQUOTE "menu_order" DQUOTE ":" 1*DIGIT )
	               | ( DQUOTE "comment_status" DQUOTE ":" DQUOTE comment-status DQUOTE )
	               | ( DQUOTE "ping_status" DQUOTE ":" DQUOTE ping-status DQUOTE )
	               | ( DQUOTE "sticky" DQUOTE ":" boolean )
	               | ( DQUOTE "post_thumbnail" DQUOTE ":" post-thumbnail )
	               | ( DQUOTE "post_format" DQUOTE ":" DQUOTE post-format DQUOTE )
	               | ( DQUOTE "terms" DQUOTE ":" terms )
	               | ( DQUOTE "post_meta" DQUOTE ":" custom-fields ) )
	post-status    = "draft" | "pending" | "private" | "publish" | "trash"
	post-type      = "post" | "page" | token
	comment-status = "open" | "closed"
	ping-status    = "open" | "closed"
	post-thumbnail = "[" *( post-thumb ) "]"
	post-format    = "standard" | "aside" | "gallery" | "image" | "link" | "status"
	custom-fields  = "[" *( "{"
	               ( DQUOTE "id" DQUOTE ":" 1*DIGIT
	               | DQUOTE "key" DQUOTE ":" quoted-string
	               | DQUOTE "value" DQUOTE ":" quoted-string
	               ) "}" ) "]"

#### JSON Schema

	{
		"$schema": "http://json-schema.org/schema#",
		"id": "http://wp-api.ryanmccue.info/schema#/definitions/post",
		"title": "Post",

		"type": "object",
		"properties": {
			"ID": {
				"type": "integer",
				"description": "Entity ID"
			},
			"title": {
				"type": "string",
				"description": "Post title"
			},
			"date": {
				"type": "string",
				"description": "Post creation datetime in the local timezone"
			},
			"date_tz": {
				"type": "string",
				"description": "Olsen timezone identifier for the date field"
			},
			"date_gmt": {
				"type": "string",
				"description": "Post creation datetime in UTC"
			},
			"modified": {
				"type": "string",
				"description": "Post last modification datetime in the local timezone"
			},
			"modified_tz": {
				"type": "string",
				"description": "Olsen timezone identifier for the modified field"
			},
			"modified_gmt": {
				"type": "string",
				"description":"Post last modification datetime in UTC"
			},
			"status": {
				"type": "string",
				"description": "Post published status",
				"enum": [
					"draft",
					"pending",
					"private",
					"publish",
					"trash"
				]
			},
			"type": {
				"type": "string",
				"description": "Post type"
			},
			"name": {
				"type": "string",
				"description": "Post slug (URL identifier)"
			},
			"author": {
				"type": "object",
				"description": "Post author details"
			},
			"password": {
				"type": "string",
				"description": "Post password"
			},
			"excerpt": {
				"type": "string",
				"description": "Short version of the post content for display"
			},
			"content": {
				"type": "string",
				"description": "Post content"
			},
			"parent": {
				"type": "integer",
				"description": "Parent post's ID, 0 for no parent"
			},
			"link": {
				"type": "string",
				"format": "uri",
				"description": "Full URL to the post"
			},
			"guid": {
				"type": "string",
				"description": "Globally unique identifier for the post"
			},
			"menu_order": {
				"type": "integer",
				"description": "The post's position in menus",
				"default": 0
			},
			"comment_status": {
				"type": "string",
				"description": "Whether the post is open for commenting",
				"enum": [
					"open",
					"closed"
				]
			},
			"ping_status": {
				"type": "string",
				"description": "Whether the post is open for pingbacks/trackbacks",
				"enum": [
					"open",
					"closed"
				]
			},
			"sticky": {
				"type": "boolean",
				"description": "Whether the post is stickied (shown at the top of archives)"
			},
			"post_thumbnail": {
				"type": [ "object", "array" ],
				"description": "Thumbnail image representing the post"
			},
			"post_format": {
				"type": "string",
				"description": "Standardized post format",
				"enum": [
					"standard",
					"aside",
					"gallery",
					"image",
					"link",
					"status"
				]
			},
			"terms": {
				"type": "object",
				"description": "Taxonomic terms"
			},
			"post_meta": {
				"type": "array",
				"description": "Post meta fields"
			}
		},

		"required": ["ID", "link"]
	}

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
A Post Collection entity is defined as a collection of Post entities.

### Headers
The following headers are sent when a Post Collection is the main entity:

* `Link`:
	* `rel="item"` - Each item in the collection has a corresponding Link header
	  containing the location of the endpoint for that resource.


### Entity
The Post Collection entity is a JSON list of Post entities.

	Post-Collection = "[" Post *( "," Post ) "]"


Endpoints
=========

The following endpoints return the given document with associated headers.

	/: Index
	/posts: Post Collection
	/posts/<id>: Post
	/posts/<id>/revisions: Post Collection
	/posts/<id>/comments: Comment Collection
	/posts/<id>/comments/<comment>: Comment

	/taxonomies: Taxonomy Collection
	/taxonomies/<tax>: Taxonomy
	/taxonomies/<tax>/terms: Term Collection
	/taxonomies/<tax>/terms/<term>: Term

	/users: User Collection
	/users/me: User
	/users/<user>: User
