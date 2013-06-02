Documents
=========

General
-------

	date          = 1*DIGIT
	boolean       = "true" | "false"

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
	post-field     = ( ( "post_id" ":"  [ "-" ] 1*DIGIT )
	               | ( "post_title" ":" quoted-string )
	               | ( "post_date" ":" date )
	               | ( "post_date_gmt" ":" date )
	               | ( "post_modified" ":" date )
	               | ( "post_modified_gmt" ":" date )
	               | ( "post_status" ":" <"> post-status <"> )
	               | ( "post_type" ":" <"> post-type <"> )
	               | ( "post_name" ":" quoted-string )
	               | ( "post_author" ":" 1*DIGIT )
	               | ( "post_password" ":" quoted-string )
	               | ( "post_excerpt" ":" quoted-string )
	               | ( "post_content" ":" quoted-string )
	               | ( "post_parent" ":" 1*DIGIT )
	               | ( "post_mime_type" ":" quoted-string )
	               | ( "link" ":" URI )
	               | ( "guid" ":" quoted-string )
	               | ( "menu_order" ":" 1*DIGIT )
	               | ( "comment_status" ":" comment-status )
	               | ( "ping_status" ":" ping-status )
	               | ( "sticky" ":" boolean )
	               | ( "post_thumbnail" ":" post-thumbnail )
	               | ( "post_format" ":" post-format )
	               | ( "terms" ":" terms )
	               | ( "custom_fields" ":" custom-fields ) )
	post-status    = "draft" | "pending" | "private" | "publish" | "trash"
	post-type      = "post" | "page" | token
	comment-status = "open" | "closed"
	ping-status    = "open" | "closed"
	post-thumbnail = "[" *( post-thumb ) "]"
	post-format    = "aside" | "gallery" | "image" | "link" | "status"
	custom-fields  = "[" *( "{"
	               ( "id" ":" 1*DIGIT
	               | "key" ":" quoted-string
	               | "value" ":" quoted-string
	               ) "}" ) "]"


### Example

	HTTP/1.1 200 OK
	Date: Tue, 01 Jan 2013 23:59:59 GMT
	Last-Modified: Tue, 01 Jan 2013 12:00:00 GMT
	Link: <http://localhost/wptrunk/?p=1>; rel="alternate"; type=text/html
	Link: <http://localhost/wptrunk/wp-json.php/users/1>; rel="author"
	Link: <http://localhost/wptrunk/wp-json.php/posts>; rel="collection"
	Link: <http://localhost/wptrunk/wp-json.php/posts/1/comments>; rel="replies"
	Link: <http://localhost/wptrunk/wp-json.php/posts/1/revisions>; rel="version-history"
	Content-Type: application/json; charset=UTF-8

	{
		"post_id": 69,
		"post_title": "Hello!",
		"post_date": 1352215701,
		"post_date_gmt": 1352215701,
		"post_modified": 1352259960,
		"post_modified_gmt": 1352259960,
		"post_status": "publish",
		"post_type": "post",
		"post_name": "hello",
		"post_author": 1,
		"post_password": "",
		"post_excerpt": "",
		"post_content": "<p>Hi there!<\/p>\n\n<p>This is just a proof of concept.</p>\n",
		"post_parent": "0",
		"post_mime_type": "",
		"link": "http:\/\/localhost\/wptrunk\/?p=69",
		"guid":"http:\/\/localhost\/wptrunk\/?p=69",
		"menu_order":0,
		"comment_status":"open",
		"ping_status":"open",
		"sticky":false,
		"post_thumbnail":[],
		"post_format":"standard",
		"terms":[
			{
				"term_id":"1",
				"name":"Uncategorized",
				"slug":"uncategorized",
				"term_group":"0",
				"term_taxonomy_id":"1",
				"taxonomy":"category",
				"description":"",
				"parent":"0",
				"count":3
			}
		],
		"custom_fields":[]
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
