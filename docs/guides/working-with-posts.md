Working with Posts
==================
Back in the [Getting Started][] guide we used posts to demonstrate how to work
with the API, but only touched on some of the details. Let's take a more
detailed look at the Post API.


Before We Start
---------------
This guide assumes you have a basic knowledge of the API, as well as the
prerequisites noted in the [Getting Started] guide. If you haven't already, make
sure you read that guide to ensure you know the basics.

This guide also assumes that you know how to send requests given how to use
them, so the examples will be HTTP requests. I recommend reading the cURL manual
or using a higher level tool if you don't know how to wrangle cURL.

The examples also pretend that your JSON base URL (`wp-json` in the main WP
directory) is located at `/`, which is probably not the case. For example, if
your base URL is `http://example.com/wp-json` and the example request is
`GET /posts`, you should should actually send the following:

    GET /wp-json/posts HTTP/1.1
    Host: example.com

Higher level HTTP clients can usually handle this for you.


Post Entities and Collections
-----------------------------
As mentioned previously, we can send a GET request to the main posts route to
get posts:

    GET /posts HTTP/1.1

This returns a list of Post objects. From a technical point of view, each object
is called an Entity (in this case, Post Entities), while the endpoint returns a
Collection (here, a Post Collection).

Post Entities have certain [defined properties][schema], although which are set
depend on the context. For example, with embedded entities certain properties
may have different values; while the post parent entity is embedded, the parent
field inside this is only the ID.


Collection Views
----------------
Post Collections can be modified by using various query string parameters. One
that you may already know is the `page` parameter. This parameter is used for
pagination, and while you can set it manually, it's also sent via the Link
headers to point you to the next and previous pages. A `context` parameter is
also available that we'll talk about later.

You can also change the post type via the `type` parameter. It's recommended
that you check the [available post types](#custom-post-types) before doing this,
as plugins may change what's available.

The last parameter is the `filter` parameter. This gives you full access to the
[WP_Query][] parameters, to alter the query to your liking. Depending on the
level of access you have, not all parameters will be available, so check the
[schema][] for the available parameters. A good assumption to make is that
anything you can put in a query on the site itself (such as `?s=...` for
searches) will be available. You can specify filter parameters in a request
using [array-style URL formatting][].


Creating and Editing Posts
--------------------------
The main posts route also has a creation endpoint:

    POST /posts HTTP/1.1
    Content-Type: application/json; charset=utf-8

    {
        "title": "My Post Title"
    }

Here we send a Post entity that we've created locally to the server. These Post
entities are the same as Post entities served up by the server, but excluding
some fields which are immutable. For example, the ID and timezone fields cannot
be changed.

When editing posts, it's helpful to pass the `edit` context along with your
request. This gives you extra data useful for editing, such as the unfiltered
content and title. This is not required, however it is recommended, as the
normal content and title fields have been filtered to correct HTML (such as via
the `wpautop` function), making them less than optimal for editing.

You can also pass an `If-Unmodified-Since` header when editing posts with the
previous modification date to ensure that you don't accidentally overwrite edits
made in the meantime. Note that while dates in the `modified` field of the post
are in [RFC3339][] format, this header requires the use of [RFC1123][] (similar
to [RFC822][]). (Sorry about that, but the HTTP standard requires it.)

[RFC3339]: http://tools.ietf.org/html/rfc3339
[RFC1123]: http://tools.ietf.org/html/rfc1123
[RFC822]: http://tools.ietf.org/html/rfc822


Custom Post Types
-----------------
Custom post types can be queried via the main post routes (`/posts` and
children) when they have been made public. The type can be set via the `type`
query parameter.

Before working with custom post types, you should first check that the API has
support for the post type that you want to work with. This data is available via
the read-only APIs at `/posts/types`:

    GET /posts/types HTTP/1.1

This should return a list of the available types:

    {
        "post": {
            "name": "Posts",
            "slug": "post",
            "description": "",
            "labels": {
                "name": "Posts",
                "singular_name": "Post",
                "add_new": "Add New",
                "add_new_item": "Add New Post",
                "edit_item": "Edit Post",
                "new_item": "New Post",
                "view_item": "View Post",
                "search_items": "Search Posts",
                "not_found": "No posts found.",
                "not_found_in_trash": "No posts found in Trash.",
                "parent_item_colon": null,
                "all_items": "All Posts",
                "menu_name": "Posts",
                "name_admin_bar": "Post"
            },
            "queryable": true,
            "searchable": true,
            "hierarchical": false,
            "meta": {
                "links": {
                    "self": "http:\/\/example.com\/wp-json\/posts\/types\/post",
                    "archives": "http:\/\/example.com\/wp-json\/posts"
                }
            }
        },
        "page": {
            "name": "Pages",
            "slug": "page",
            "description": "",
            "labels": {
                "name": "Pages",
                "singular_name": "Page",
                "add_new": "Add New",
                "add_new_item": "Add New Page",
                "edit_item": "Edit Page",
                "new_item": "New Page",
                "view_item": "View Page",
                "search_items": "Search Pages",
                "not_found": "No pages found.",
                "not_found_in_trash": "No pages found in Trash.",
                "parent_item_colon": "Parent Page:",
                "all_items": "All Pages",
                "menu_name": "Pages",
                "name_admin_bar": "Page"
            },
            "queryable": false,
            "searchable": true,
            "hierarchical": true,
            "meta": {
                "links": {
                    "self": "http:\/\/example.com\/wp-json\/posts\/types\/page"
                }
            }
        },
        "attachment": {
            "name": "Media",
            "slug": "attachment",
            "description": "",
            "labels": {
                "name": "Media",
                "singular_name": "Media",
                "add_new": "Add New",
                "add_new_item": "Add New Post",
                "edit_item": "Edit Media",
                "new_item": "New Post",
                "view_item": "View Attachment Page",
                "search_items": "Search Posts",
                "not_found": "No posts found.",
                "not_found_in_trash": "No posts found in Trash.",
                "parent_item_colon": null,
                "all_items": "Media",
                "menu_name": "Media",
                "name_admin_bar": "Media"
            },
            "queryable": true,
            "searchable": true,
            "hierarchical": false,
            "meta": {
                "links": {
                    "self": "http:\/\/example.com\/wp-json\/posts\/types\/attachment",
                    "archives": "http:\/\/example.com\/wp-json\/posts?type=attachment"
                }
            }
        }
    }

The `meta.links.archives` value gives a nicer way to access given post type
archives for HATEOAS supporters and should always be used rather than manually
setting the `type` parameter directly, as CPTs may create their own route
structure instead. The `labels` fields should also always be used when
displaying the field, as these are run through WordPress' translations.

A similar API exists for post statuses at `/posts/statuses`:

    {
        "publish": {
            "name": "Published",
            "slug": "publish",
            "public": true,
            "protected": false,
            "private": false,
            "queryable": true,
            "show_in_list": true,
            "meta": {
                "archives": "http:\/\/example.com\/wp-json\/posts"
            }
        },
        "future": {
            "name": "Scheduled",
            "slug": "future",
            "public": false,
            "protected": true,
            "private": false,
            "queryable": false,
            "show_in_list": true,
            "meta": [
            ]
        },
        "draft": {
            "name": "Draft",
            "slug": "draft",
            "public": false,
            "protected": true,
            "private": false,
            "queryable": false,
            "show_in_list": true,
            "meta": [
            ]
        },
        "pending": {
            "name": "Pending",
            "slug": "pending",
            "public": false,
            "protected": true,
            "private": false,
            "queryable": false,
            "show_in_list": true,
            "meta": [
            ]
        },
        "private": {
            "name": "Private",
            "slug": "private",
            "public": false,
            "protected": false,
            "private": true,
            "queryable": false,
            "show_in_list": true,
            "meta": [
            ]
        }
    }


Next Steps
----------
You should now understand more advanced usage of the post-related APIs, and be
able to implement a fully compliant client for the API. You might now want to
take a look at the other APIs, or look at documentation on the specifics.

* [Schema][schema]: Full documentation of every parameter for the APIs.
* [Extending the API][]: Create your own API endpoints.

[Getting Started]: getting-started.md
[Extending the API]: extending.md
[schema]: ../schema.md
[WP_Query]: http://codex.wordpress.org/Class_Reference/WP_Query
[array-style URL formatting]: ../compatibility.md#inputting-data-as-an-array
