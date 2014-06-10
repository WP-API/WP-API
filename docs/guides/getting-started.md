Getting Started
===============

Hi! If you're reading this, you're probably wondering how to get started using
the REST API. In that case, you've come to the right place! I'm about to show
you the basics of the API from the ground up.

(If you're in the wrong place, there's not much I can do to help. Sorry!)


Before We Start
---------------
To interact with the API, you're going to need some tools. The first of these
you need is a HTTP client/library. These examples use command-line cURL, but any
HTTP client would do here.

The next tool you need is a JSON parser, and generator if you're sending data.
I'm going to do this by hand for the command-line examples, but it's recommended
to use a proper serializer/deserializer in your programming language of choice.
That said, it's not a bad idea to know how to read and write JSON by hand.


Checking for the API
--------------------
Before you start using the API, it's a good idea to check that the site itself
supports the API. WordPress is flexible enough to allow disabling the API or
parts thereof, and plugins may add or change parts of the API, so it's always
good to double-check this.

The easiest way to check this is to send a HEAD request to any page on the site.
Any site with the API enabled will return a `Link` header pointing to the API,
with the link relation set to `https://github.com/WP-API/WP-API`.

My test site for this documentation is set up at `http://example.com/`, so we
can find the API by sending a HEAD request to this URL:

    curl -I http://example.com/

(Uppercase `-I` here sends a HEAD request rather than a GET request, since we
only care about the headers here. I'll strip some irrelevant headers for
this documentation.)

And we get back:

    HTTP/1.1 200 OK
    X-Pingback: http://example.com/wp/xmlrpc.php
    Link: <http://example.com/wp-json>; rel="https://github.com/WP-API/WP-API"

The `Link` header tells us that the base URL for the API is
`http://example.com/wp-json`. Any routes should be appended to this. For
example, the API index that we'll use in a second is available at the `/` route,
so we append this to the URL to get `http://example.com/wp-json/`
(note the trailing slash).

For sites without pretty permalinks enabled, this will return something like
`http://example.com/?json_route=` instead; the route should again be appended
directly as a string, giving the API index at `http://example.com/?json_route=/`

(You can also "discover" the API by looking for the HTML `<link>` in the
`<head>` with the same link relation, or in the RSD along with other WordPress
API information.)

Checking the API Index
----------------------
As our next command, let's go ahead and check the API index. The index tells us
what routes are available, and a short summary about the site.

As we discovered previously, the index is available at the site's address with
`/wp-json/` on the end. Let's fire off the request:

    curl -i http://example.com/wp-json/

(By the way, `-i` tells cURL that we want to see the headers as well. As before,
I'll strip some irrelevant ones for this documentation.)

And here's what we get back:

    HTTP/1.1 200 OK
    Content-Type: application/json; charset=UTF-8

```json
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
                "self": "http:\/\/example.com\/wp-json\/"
            }
        },
        "\/posts": {
            "supports": [
                "HEAD",
                "GET",
                "POST"
            ],
            "meta": {
                "self": "http:\/\/example.com\/wp-json\/posts"
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
        "\/posts\/types": {
            "supports": [
                "HEAD",
                "GET"
            ],
            "meta": {
                "self": "http:\/\/example.com\/wp-json\/posts\/types"
            }
        },
        "\/posts\/types\/<type>": {
            "supports": [
                "HEAD",
                "GET"
            ]
        },
        "\/posts\/statuses": {
            "supports": [
                "HEAD",
                "GET"
            ],
            "meta": {
                "self": "http:\/\/example.com\/wp-json\/posts\/statuses"
            }
        },
        "\/taxonomies": {
            "supports": [
                "HEAD",
                "GET"
            ],
            "meta": {
                "self": "http:\/\/example.com\/wp-json\/taxonomies"
            }
        },
        "\/taxonomies\/<taxonomy>": {
            "supports": [
                "HEAD",
                "GET"
            ]
        },
        "\/taxonomies\/<taxonomy>\/terms": {
            "supports": [
                "HEAD",
                "GET"
            ]
        },
        "\/taxonomies\/<taxonomy>\/terms\/<term>": {
            "supports": [
                "HEAD",
                "GET"
            ]
        },
        "\/users": {
            "supports": [
                "HEAD",
                "GET",
                "POST"
            ],
            "meta": {
                "self": "http:\/\/example.com\/wp-json\/users"
            },
            "accepts_json": true
        },
        "\/users\/me": {
            "supports": [
                "HEAD",
                "GET",
                "POST",
                "PUT",
                "PATCH",
                "DELETE"
            ],
            "meta": {
                "self": "http:\/\/example.com\/wp-json\/users\/me"
            }
        },
        "\/users\/<user>": {
            "supports": [
                "HEAD",
                "GET",
                "POST"
            ],
            "accepts_json": true
        }
    },
    "meta": {
        "links": {
            "help": "https:\/\/github.com\/WP-API\/WP-API",
            "profile": "https:\/\/raw.github.com\/rmccue\/WP-API\/master\/docs\/schema.json"
        }
    }
}
```

Wow, that's a lot of data! Let's break it down.

First, let's look at the headers. We get a 200 status code back, indicating that
we can successfully get the index. Note that it's possible for sites to disable
the API, which would give us a 404 error. You can check the returned body to
find out if the API exists at all; a disabled site will give you a JSON object
with the `json_disabled` error code, while a site without the API at all will
probably give you an error from the theme.

Next, we can see the `name`, `description` and `URL` fields. These are intended
to be used if you want to show the site's name to users, such as in settings
dialogs.

The `routes` object contains the meat of the body. This object has a bunch of
keys with "routes" (templates for creating URLs) pointing to objects containing
data (this data tells you about the "endpoint"). Each of these objects has at
least a `supports` key that contains a list of the HTTP methods supported. The
`meta` key has a hyperlink to the route, while the `accepts_json` key will also
be set and be `true` if you can POST/PUT JSON directly to the endpoint.

You might notice that some of the routes have a `<something>` part in them. This
is a variable part, and it tells you that you can replace it with something,
such as an object ID.


Routes vs Endpoints
-------------------
Quick note on terminology: a "route" is a URL that gets passed into the API;
these look like `/posts` or `/posts/2` and can be written in a generic form as
`/some/part/<variable_part>`. An "endpoint" on the other hand is the handler
that actually does something with your request.

For example, the `/posts` route has two endpoints: the Retrieve Posts endpoint
to handle GET requests, and the Create Post endpoint to handle POST requests.


Meta Objects
------------
You'll also see in the response that we have a `meta` object at the top level,
plus a bunch of smaller ones for the routes. Meta objects are mappings of [link
relations][] to their corresponding URL. These URLs are usually internal URLs to
help you browse the API, and can be used by clients embracing the [HATEOAS][]
methodology.

[link relations]: http://www.iana.org/assignments/link-relations/link-relations.xhtml
[HATEOAS]: http://en.wikipedia.org/wiki/HATEOAS

Here's some common relations you'll see:

* `self`: The canonical URL for the resource. This is usually most helpful in
   embedded objects (post parent, author, etc)
* `collection`: The URL for the collection containing the resource. The endpoint
   at this route usually gives a list, of which, the current resource is a
   member (for example, `/posts/1` would have a collection of `/posts`)
* `archive`: The URL for the collection owned by this resource (for example,
   posts written by an author)
* `replies`: Responses to a resource (for example, comments on a post, replies
   to a comment)


Getting Posts
-------------
Now that we understand some of the basics, let's have a look at the posts route.
All we need to do is send a GET request to the posts endpoint.

    curl -i http://example.com/wp-json/posts

And this time, we get (again trimming headers, you'll have more than this):

    HTTP/1.1 200 OK
    Last-Modified: Wed, 31 Oct 2012 18:26:17 GMT
    Link: <http://example.com/wp-json/posts/1>; rel="item"; title="Hello world!"

```json
[
    {
        "ID": 1,
        "title": "Hello world!",
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
                    "self": "http:\/\/example.com\/wp-json\/users\/1",
                    "archives": "http:\/\/example.com\/wp-json\/users\/1\/posts"
                }
            }
        },
        "content": "Welcome to WordPress. This is your first post. Edit or delete it, then start blogging!",
        "parent": 0,
        "link": "http:\/\/example.com\/2012\/10\/31\/hello-world\/",
        "date": "2012-10-31T18:26:17+10:00",
        "modified": "2012-10-31T18:26:17+10:00",
        "format": "standard",
        "terms": {
            "category": {
                "ID": 1,
                "name": "Uncategorized",
                "slug": "uncategorized",
                "group": 0,
                "parent": 0,
                "count": 1,
                "meta": {
                    "links": {
                        "collection": "http:\/\/example.com\/wp-json\/taxonomy\/category",
                        "self": "http:\/\/example.com\/wp-json\/taxonomy\/category\/terms\/1"
                    }
                }
            }
        },
        "meta": {
            "links": {
                "self": "http:\/\/example.com\/wp-json\/posts\/1",
                "author": "http:\/\/example.com\/wp-json\/users\/1",
                "collection": "http:\/\/example.com\/wp-json\/posts",
                "replies": "http:\/\/example.com\/wp-json\/posts\/1\/comments",
                "version-history": "http:\/\/example.com\/wp-json\/posts\/1\/revisions"
            }
        }
    }
]
```

Hopefully this looks fairly self-explanatory. For a full look at all the fields
you can get back from this, take a look at [Working with Posts][] or the
[definition][schema] for the Post entity.

You'll notice that in this case we're getting a list back with just a single
item, one Post. If you have two Posts here, you'll get back two, and so on, up
to 10. If there are more than 10, pagination will kick in and you'll have to
handle this in your client.

Pagination details are given in the HTTP headers from the endpoint. The
`X-WP-Total` header has the total number of posts available to you, while the
`X-WP-TotalPages` header has the number of pages. While you can build the page
URL manually, you should use the `next` and `prev` links from the `Link` header
where possible.

Example of pagination headers:

    X-WP-Total: 492
    X-WP-TotalPages: 50
    Link: <http://example.com/wp-json/posts?page=4>; rel="next",
     <http://example.com/wp-json/posts?page=2>; rel="prev"

If you want to grab a single post, you can instead send a GET request to the
post itself. You can grab the URL for this from the `meta.links.self` field, or
construct it yourself (`/posts/<id>`):

    curl -i http://example.com/wp-json/posts/1


Editing and Creating Posts
--------------------------
Just as we can use a GET request to get a post, we can use PUT to edit a post.
The easiest way to do this is to send a JSON body back to the server with just
the fields you want to change. [Authentication][auth] is **required** to edit
posts. For example, to edit the title and the modification date:

```json
{
    "title": "Hello Updated World!",
    "modified": "2013-04-01T14:00:00+10:00"
}
```

Save the data as "updated-post.json", then we can send this to the server with
the correct headers and authentication. We will provide our username and
password with HTTP Basic authentication, which requires the [Basic Auth plugin][basic-auth-plugin]
be installed:

    curl --data-binary "@updated-post.json" \
        -H "Content-Type: application/javascript" \
        --user admin:password \
        http://example.com/wp-json/posts/1

And we should get back a 200 status code, indicating that the post has been
updated, plus the updated Post in the body.

Note that there are some fields we can't update; ID is an obvious example, but
others like timezone fields cannot be updated either. Check the [schema][] for
more details on this.

Similarly to editing posts, you can create posts. [Authentication][auth] is
**required** to create posts. We can use the same data from before, but this
time, we POST it to the main posts route. Again, we are providing our username
and password using HTTP Basic authentication which requires the
[Basic Auth plugin][basic-auth-plugin] be installed:

    curl --data-binary "@updated-post.json" \
        -H "Content-Type: application/javascript" \
        --user admin:password \
        http://example.com/wp-json/posts

We should get a similar response to the editing endpoint, but this time we get
a 201 Created status code, with a Location header telling us where to access the
post in future:

    HTTP/1.1 201 Created
    Location: http://example.com/wp-json/posts/2

Finally, we can clean this post up and delete it by sending a DELETE request:

    curl -X DELETE --user admin:password http://example.com/wp-json/posts/2

In general, routes follow the same pattern:

* `/<object>`:
    * GET returns a collection
    * POST adds a new item to the collection
* `/<object>/<id>`:
    * GET returns an entity
    * PUT updates the entity
    * DELETE deletes the entity

Note that by convention, the plural form of `<object>` is used in the URL
(e.g. `posts` instead of `post`, `pages` instead of `page`).

Next Steps
----------
You should now be able to understand the basics of accessing and creating data
via the API, plus have the ability to apply this to posts. We've only really
touched on the post-related data so far, and there's plenty more to the API, so
get exploring!

* [Working With Posts][]: Learn more about Posts and their data
* [Schema][schema]: View technical information on all the available data
* [Authentication][auth]: Explore authentication options

[Working with Posts]: working-with-posts.md
[schema]: ../schema.md
[auth]: ../authentication.md
[basic-auth-plugin]: https://github.com/WP-API/Basic-Auth
