Extending the API
=================

If you're a plugin author, you're probably itching to start adding functionality
to the API, including working with custom post types. I'm here to guide you
through the process of extending the API with your data, and to remind you of
some important points.


Before We Start
---------------
This guide assumes that you have a fairly decent knowledge of the API already.
Before starting, make sure you've read the [Getting Started][] guide, and if you
want to work with custom post types, also read the [Working With Posts][] guide.

You should also have a pretty good knowledge of working with actions and filters
in WordPress, as well as how plugins work in general.

[Getting Started]: getting-started.md
[Working with Posts]: working-with-posts.md


A Philosophy Lesson
-------------------
Right off the bat, let me just say that the REST API is not designed for every
use under the sun. Following the mantra of [Decisions, not Options][decisions],
the API is opinionated and designed for the most common use case. You may find
that what you're trying to do doesn't fit in with the REST philosophy, and
**that's fine**; the low-level `admin-ajax.php` handler is
[still available][admin-ajax] for you to use in these scenarios, and you can
always roll your own API. An example of an API which doesn't fit with this API
is the heartbeat API; heartbeats aren't inherently designed around resources,
but events, so are a poor fit for a REST API.

That said, evaluate whether it's better to rethink your structure in terms of
resources, or to roll your own API. In most cases, thinking about data in terms
of resources rather than events or actions will not only fit better with the
API, but also make for cleaner and more maintainable code. For example, although
publishing a post is an action, it's equivalent to updating a post's status.

Keep in mind that since the REST API is opinionated, you will have to work with
the API's structure rather than rolling your own. The API is strict about how
you talk to the client, and you should always use the built-in handlers rather
than rolling your own. This strict enforcement of data handling helps to make
the API more pluggable and extensible, plus ensures that you play nice with
other plugins.

[decisions]: http://wordpress.org/about/philosophy/
[admin-ajax]: http://codex.wordpress.org/AJAX_in_Plugins


Designing Your Entities
-----------------------
The first step you should take in working with the API is to think about the
data that you're working with. You should always think about the data in terms
of the clients that you're working with; it's your job to handle translation
back and forth. While it might seem easier to just return internal objects
directly, this may expose private data, or data that's harder to handle for
clients; posts internally translate WordPress's date format (YYYY-MM-DD
HH:MM:SS) to the standard RFC3339 which can be parsed by most date parsers. It's
worth your time to consider issues like this which can save clients hours of
work on their end.

You should also consider how other plugins might extend your data, and design
for this. While things like `comment_status` could become a boolean field,
plugins might add custom statuses. On the other side of this, you should also
consider how these fields should be handled by clients. Custom field values that
a client doesn't understand should have a sensible default handling; for
example, the `comment_status` field is documented as the following:

> Providers MAY use statuses other than "open" or "closed" to indicate other
> statuses. Consumers who encounter an unknown or missing comment status SHOULD
> treat it as "closed".

This ensures that while clients may not know what a field value means, they can
still operate in a safe manner with the posts.


Designing Your Routes and Endpoints
-----------------------------------
After you've decided what your entities should look like, it's time to think
about how you want clients to access and interact with the data. Picking your
routes is an important part of this, and should be oriented around your data.
Collections of Entities should be a base route, with individual Entities as
subroutes of this. The following is a good general rule:

* `/<object>`:
    * GET returns a collection
    * POST adds a new item to the collection
* `/<object>/<id>`:
    * GET returns an entity
    * PUT updates the entity
    * DELETE deletes the entity

(Again, this is the difference between routes and endpoints. There are two
routes here, but with two and three endpoints respectively. In general, a route
maps to an Entity/Collection, whereas an endpoint maps to an action on that
Entity/Collection.)

Since your data is in your plugin, you should also prefix your routes. For
example, routes for Hello Dolly might look something like:

* `/hello-dolly`: Get all lines from Hello Dolly
* `/hello-dolly/<n>`: Get the nth line from Hello Dolly
* `/hello-dolly/random`: Get a random line from Hello Dolly

If you're extending an existing route, you might want to use a flat style of
prefixing. Plugins like Akismet might implement something like:

* `/posts/<id>/comments/akismet-recheck`: Recheck a post's comments for spam

Note that this is somewhat against the previously discussed philosophy of
organising around resources; sometimes, you might want to have actions
available directly. These should always be a POST endpoint, since you're taking
action on the server in response. You should also avoid this where possible,
although there's no need to go overboard and create things like an Akismet Queue
resource just for a single action.


Organizing Your Endpoints
-------------------------
Now that you've got a roadmap for everything you'd like to implement, it's time
to start implementing it.

The recommended way to organize your endpoints is to group related endpoints
together into a class, and extend the base classes built into the API.
Post-related endpoints should look to extend the `WP_JSON_CustomPostType` class,
and use the same method naming as it, falling back to it for the post
preparation and request handling. This will automatically register all the post
methods for their endpoints.

Along these lines, keep your methods named as generically as possible; while
`MyPlugin_API_MyType::get_my_type_items()` might seem like a good name, it makes it
harder for other plugins to use; standardising on
`MyPlugin_API_MyType::get_posts()` with similar arguments to the parent is a
better idea and allows a nicer fall-through.

You should also aim to keep these related endpoints modular, and make liberal
use of filters for this. As an example of this, the built-in Posts API is
filtered by the Media API to add featured image-related data, ensuring that
there's no dependencies between the two and either can be enabled independently.


Creating Your Endpoints
-----------------------
Before you actually write the code, there's some important things to know about
how you write the code. (Remember the opinions I mentioned before? This
is them.)

Each endpoint should be written in the same way you'd write an internal function
for your plugin. That is, they take parameters, and return data. They also
*never* read directly from request globals (such as `$_GET` or `$_POST`), but
instead read them via the parameters to your function. The REST API serving code
automatically maps GET and POST parameters to your function's parameters (along
with some special request-related parameters). This concept might seem a little
strange at first, but it helps to ensure pluggability and standardisation of
errors.

For example, an endpoint that takes a required `context` parameter, an optional
`type` parameter and uses the `X-WP-Example` header would look like this:

	function get_my_data( $context, $_headers, $type = 'my-default-value' ) {
		if ( isset( $_headers['X-WP-EXAMPLE'] ) ) {
			$my_header_value = $_headers['X-WP-EXAMPLE'];
		}

		if ( $type !== 'my-default-value' && $type !== 'some-other-value' ) {
			return new WP_Error( 'myplugin_mydata_invalid_type', __( 'Invalid type.' ), array( 'status' => 400 ) );
		}

		// ...

		return array( /* ... */ );
	}

Note that this is intentionally the same way you'd write a function for internal
consumption; default parameter values are specified as default argument values,
errors are returned as a WP_Error object (with the special `status` field in the
data set to an appropriate HTTP status), and the data itself being returned from
the function.

The following special values are also available, and can be trusted (always
internal data, not overridable by clients):

* `_method`: The requested HTTP method (`GET`, `HEAD`, `POST`, `PUT`, `DELETE`)
* `_route`: The route followed to your endpoint (`/posts/(?P<id>\d+)`)
* `_path`: The actual path that matches your route (`/posts/1`)
* `_headers`: An associative array of header names to values. Names are always
  uppercased (HTTP header names are case-insensitive)
* `_files`: An associative array of upload file data, in the same format
  as `$_FILES`

The special `data` parameter is also available if your endpoint is set to
receive JSON or raw data. Note that this can be given by clients either via the
HTTP body or via the `data` query parameter, which is intentional for backwards
compatibility. That is, the following requests are the same:

	Content-Type: application/json; charset=utf-8

	{
		"foo": "bar",
		"hello": "dolly"
	}

	----

	Content-Type: application/x-www-form-urlencoded

	data[foo]=bar&data[hello]=dolly

The return value of your endpoints should be an error object with the status
field set to an [appropriate value][http-status-codes], or the relevant data.
The data itself can be any JSON-serializable value: an integer, string, array,
object or null. In practice, this is almost always an array (for Collections) or
an object (for Entities).

Return headers can be set by returning an instance of `WP_JSON_Response`, which
includes a `header()` method, as well as similar helper methods
(`set_status()`, `link_header()`, `query_navigation_headers()`) called on the
response object, but should *never* be set via the direct `header()` or
`status_header()` functions.

[http-status-codes]: http://en.wikipedia.org/wiki/List_of_HTTP_status_codes


Registering Your Endpoints
--------------------------
Now that we've done the bulk of the work, it's time to tell WordPress that we
have an API to register. If you're following the same structure as the API's
built-in types, your registration code should look something like this:

	function myplugin_api_init() {
		global $myplugin_api_mytype;

		$myplugin_api_mytype = new MyPlugin_API_MyType();
		add_filter( 'json_endpoints', array( $myplugin_api_mytype, 'register_routes' ) );
	}
	add_action( 'wp_json_server_before_serve', 'myplugin_api_init' );

	class MyPlugin_API_MyType {
		public function register_routes( $routes ) {
			$routes['/myplugin/mytypeitems'] = array(
				array( array( $this, 'get_posts'), WP_JSON_Server::READABLE ),
				array( array( $this, 'new_post'), WP_JSON_Server::CREATABLE | WP_JSON_Server::ACCEPT_JSON ),
			);
			$routes['/myplugin/mytypeitems/(?P<id>\d+)'] = array(
				array( array( $this, 'get_post'), WP_JSON_Server::READABLE ),
				array( array( $this, 'edit_post'), WP_JSON_Server::EDITABLE | WP_JSON_Server::ACCEPT_JSON ),
				array( array( $this, 'delete_post'), WP_JSON_Server::DELETABLE ),
			);

			// Add more custom routes here

			return $routes;
		}

		// ...
	}

You will need to implement the `get_post`, `edit_post`, `get_posts`, and
`new_post` methods within your new class. Take a look at the `WP_JSON_Posts`
class to see examples of how these methods can be written.

Alternatively, use the custom post type base class, which will handle the
hooking and more for you:

	// main.php
	function myplugin_api_init( $server ) {
		global $myplugin_api_mytype;

		require_once dirname( __FILE__ ) . '/class-myplugin-api-mytype.php';
		$myplugin_api_mytype = new MyPlugin_API_MyType( $server );
		$myplugin->register_filters();
	}
	add_action( 'wp_json_server_before_serve', 'myplugin_api_init' );

	// class-myplugin-api-mytype.php
	class MyPlugin_API_MyType extends WP_JSON_CustomPostType {
		protected $base = '/myplugin/mytypeitems';
		protected $type = 'myplugin-mytype';

		public function register_routes( $routes ) {
			$routes = parent::register_routes( $routes );
			// $routes = parent::register_revision_routes( $routes );
			// $routes = parent::register_comment_routes( $routes );

			// Add more custom routes here

			return $routes;
		}

		// ...
	}

(Note that this CPT base class handles other things as well, including strict
post type checking and correcting URLs.)

It is important that this class lives in a separate file that is only included
on a WP API hook, as your plugin may load before the WP API plugin. If you get
errors about the `WP_JSON_CustomPostType` class not being loaded, this is
the reason.

The data passed in and returned by the `json_endpoints` filter should be in the
format of an array containing a map of route regular expression to endpoint
lists. An endpoint list is a potentially unlimited array of endpoints' data.
Endpoint data is a two-element array, with the first element being the endpoint
callback, and the second element specifying options. In other words, the format
is:

	$routes['regex expression'] = array(
		// one or more:
		array(
			'callback_function',
			0 // bitwise options flag
		)
	);

(Where possible, use named parameters of the form `(?P<name>...)` for variables
in your route, as these will be automatically replaced with a more friendly
version in the index.)

The available bitwise options are:

* `WP_JSON_Server::READABLE`: Read endpoint (responds to GET)
* `WP_JSON_Server::CREATABLE`: Creation endpoint (responds to POST)
* `WP_JSON_Server::EDITABLE`: Edit endpoint (responds to POST/PUT/PATCH)
* `WP_JSON_Server::DELETABLE`: Deletion endpoint (responds to DELETE)
* `WP_JSON_Server::ALLMETHODS`: Generic endpoint (responds to
  GET/POST/PUT/PATCH/DELETE)
* `WP_JSON_Server::ACCEPT_RAW`: Accepts raw data in the HTTP body
* `WP_JSON_Server::ACCEPT_JSON`: Accepts JSON data in the HTTP body
  (automatically deserialized by the server)
* `WP_JSON_Server::HIDDEN_ENDPOINT`: Hide the endpoint from the index

Once you've done this, you should now go and check that your route is appearing
in the index. Whip out a HTTP client and test out that your API endpoints are
working correctly.


Next Steps
----------
You should now have a working API built with the REST API. Congratulations!
You now have a mastery of how the API works, so get out there and start
expanding your API. You should consider contributing back to the core API and
improving the built-in APIs.

* [API Philosophy][]: Learn more about the design of the API and the decisions
  made in the core.
* [Schema][]: Read up on the schema for the core API and use it to inspire your
  own entity design.
* [Internal Implementation][]: Learn about how the REST server works internally.

[API Philosophy]: ../internals/philosophy.md
[Schema]: ../schema.md
[Internal Implementation]: ../internals/implementation.md
