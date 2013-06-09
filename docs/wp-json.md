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

	Index            = "{" index-field *( "," index-field ) "}"
	index-field      = ( ( "name" ":" quoted-string )
	                 | ( "description" ":" quoted-string )
	                 | ( "URL" ":" URI )
	                 | ( "routes" ":" route-map )
	                 | ( "meta" ":" meta-map ) )
	route-map        = "{" route ":" route-descriptor
	                 *( "," route ":" route-descriptor ) "}"
	route            = <"> ( "/"
	                 | *( "/" ( token | route-variable ) ) ) <">
	route-variable   = "<" token ">"
	route-descriptor = "{" route-property *( "," route-property ) "}"
	route-property   = ( ( "supports" ":" "[" method *( "," method ) "]" )
	                 | ( "accepts_json" ":" boolean ) )
	method           = "HEAD" | "GET" | "POST" | "PUT" | "PATCH" | "DELETE"

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

	Post           = "{" post-field *( "," post-field ) "}"
	post-field     = ( ( "ID" ":"  [ "-" ] 1*DIGIT )
	               | ( "title" ":" quoted-string )
	               | ( "date" ":" date )
	               | ( "date_tz" ":" timezone )
	               | ( "date_gmt" ":" date )
	               | ( "modified" ":" date )
	               | ( "modified_tz" ":" timezone )
	               | ( "modified_gmt" ":" date )
	               | ( "status" ":" <"> post-status <"> )
	               | ( "type" ":" <"> post-type <"> )
	               | ( "name" ":" quoted-string )
	               | ( "author" ":" ( 1*DIGIT | User ) )
	               | ( "password" ":" quoted-string )
	               | ( "excerpt" ":" quoted-string )
	               | ( "content" ":" quoted-string )
	               | ( "parent" ":" ( 1*DIGIT | Post ) )
	               | ( "link" ":" URI )
	               | ( "guid" ":" quoted-string )
	               | ( "menu_order" ":" 1*DIGIT )
	               | ( "comment_status" ":" comment-status )
	               | ( "ping_status" ":" ping-status )
	               | ( "sticky" ":" boolean )
	               | ( "post_thumbnail" ":" post-thumbnail )
	               | ( "post_format" ":" post-format )
	               | ( "terms" ":" terms )
	               | ( "post_meta" ":" custom-fields ) )
	post-status    = "draft" | "pending" | "private" | "publish" | "trash"
	post-type      = "post" | "page" | token
	comment-status = "open" | "closed"
	ping-status    = "open" | "closed"
	post-thumbnail = "[" *( post-thumb ) "]"
	post-format    = "standard" | "aside" | "gallery" | "image" | "link" | "status"
	custom-fields  = "[" *( "{"
	               ( "id" ":" 1*DIGIT
	               | "key" ":" quoted-string
	               | "value" ":" quoted-string
	               ) "}" ) "]"


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
