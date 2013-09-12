# Changelog

## 0.5
- Add support for media - This has been a long time coming, and it's finally at
	a point where I'm happy to push it out. Good luck. ([#272][])
- Separate the post-related endpoints - Post-related endpoints are now located
	in the `WP_JSON_Posts` class. When implementing custom post type support,
	it's recommended to subclass this.

	The various types are now also only registered via hooks, rather than
	directly in the server class, which should make it easier to override them
	as well ([#348][])
- Add page support - This is a good base if you're looking to create your own
	custom post type support ([#271][])
- Switch from fields to context - Rather than passing in a list of fields that
	you want, you can now pass in a context (usually `view` or `edit`)
	([#328][]).
- Always send headers via the server handler - Endpoints are now completely
	separate from the request, so the server class can now be used for
	non-HTTP/JSON handlers if needed ([#293][])
- Use better error codes for disabled features ([#338][])
- Send `X-WP-Total` and `X-WP-TotalPages` headers for information on
	post/pagination counts ([#266][])

[View all changes](https://github.com/rmccue/WP-API/compare/0.4...0.5)

[#266]: https://gsoc.trac.wordpress.org/ticket/266
[#271]: https://gsoc.trac.wordpress.org/ticket/271
[#272]: https://gsoc.trac.wordpress.org/ticket/272
[#293]: https://gsoc.trac.wordpress.org/ticket/293
[#328]: https://gsoc.trac.wordpress.org/ticket/328
[#338]: https://gsoc.trac.wordpress.org/ticket/338
[#348]: https://gsoc.trac.wordpress.org/ticket/348


## 0.4
- Add Backbone-based models and collections - These are available to your code
	by declaring a dependency on `wp-api` ([#270][])
- Check `json_route` before using it ([#336][])
- Conditionally load classes ([#337][])
- Add additional test helper plugin - Provides code coverage as needed to the
	API client tests. Currently unused. ([#269][])
- Move `json_url()` and `get_json_url()` to `plugin.php` - This allows using
	both outside of the API itself ([#343][])
- `getPost(0)` now returns an error rather than the latest post ([#344][])

[View all changes](https://github.com/rmccue/WP-API/compare/0.3...0.4)

[#269]: https://gsoc.trac.wordpress.org/ticket/269
[#270]: https://gsoc.trac.wordpress.org/ticket/270
[#336]: https://gsoc.trac.wordpress.org/ticket/336
[#337]: https://gsoc.trac.wordpress.org/ticket/337
[#343]: https://gsoc.trac.wordpress.org/ticket/343
[#344]: https://gsoc.trac.wordpress.org/ticket/344

## 0.3
- Add initial comment endpoints to get comments for a post, and get a single
	comment ([#320][])
- Return a Post entity when updating a post, rather than wrapping it with
	useless text ([#329][])
- Allow filtering the output as well as input. You can now use the
	`json_dispatch_args` filter for input as well as the `json_serve_request`
	filter for output to serve up alternative formats (e.g. MsgPack, XML (if
	you're insane))
- Include a `profile` link in the index, to indicate the JSON Schema that the
	API conforms to. In the future, this will be versioned.

[#320]: https://gsoc.trac.wordpress.org/ticket/320
[#329]: https://gsoc.trac.wordpress.org/ticket/329

## 0.2
- Allow all public query vars to be passed to WP Query - Some private query vars
	can also be passed in, and all can if the user has `edit_posts`
	permissions ([#311][])
- Pagination can now be handled by using the `page` argument without messing
	with WP Query syntax ([#266][])
- The index now generates links for non-variable routes ([#268][])
- Editing a post now supports the `If-Unmodified-Since` header. Pass this in to
	avoid conflicting edits ([#294][])
- Post types and post statuses now have endpoints to access their data ([#268][])

[View all changes](https://github.com/rmccue/WP-API/compare/0.1.2...0.2)

[#268]: https://gsoc.trac.wordpress.org/ticket/268
[#294]: https://gsoc.trac.wordpress.org/ticket/294
[#266]: https://gsoc.trac.wordpress.org/ticket/266
[#311]: https://gsoc.trac.wordpress.org/ticket/311

## 0.1.2
- Disable media handling to avoid fatal error ([#298][])

[#298]: http://gsoc.trac.wordpress.org/ticket/298

## 0.1.1
- No changes, process error

## 0.1
- Enable the code to be used via the plugin architecture (now uses rewrite rules
	if running in this mode)
- Design documents are now functionally complete for the current codebase
	([#264][])
- Add basic writing support ([#265][])
- Filter fields by default - Unfiltered results are available via their
	corresponding `*_raw` key, which is only available to users with
	`edit_posts` ([#290][])
- Use correct timezones for manual offsets (GMT+10, e.g.) ([#279][])
- Allow permanently deleting posts ([#292])

[View all changes](https://github.com/rmccue/WP-API/compare/b3a8d7656ffc58c734aad95e0839609011b26781...0.1.1)

[#264]: https://gsoc.trac.wordpress.org/ticket/264
[#265]: https://gsoc.trac.wordpress.org/ticket/265
[#279]: https://gsoc.trac.wordpress.org/ticket/279
[#290]: https://gsoc.trac.wordpress.org/ticket/290
[#292]: https://gsoc.trac.wordpress.org/ticket/292

## 0.0.4
- Hyperlinks now available in most constructs under the 'meta' key. At the
	moment, the only thing under this key is 'links', but more will come
	eventually. (Try browsing with a browser tool like JSONView; you should be
	able to view all content just by clicking the links.)
- Accessing / now gives an index which briefly describes the API and gives
	links to more (also added the HIDDEN_ENDPOINT constant to hide from this).
- Post collections now contain a summary of the post, with the full post
	available via the single post call. (prepare_post() has fields split into
	post and post-extended)
- Post entities have dropped post_ prefixes, and custom_fields has changed to
	post_meta.
- Now supports JSONP callback via the _jsonp argument. This can be disabled
	separately to the API itself, as it's only needed for
	cross-origin requests.
- Internal: No longer extends the XMLRPC class. All relevant pieces have been
	copied over. Further work still needs to be done on this, but it's a start.

## 0.0.3:
 - Now accepts JSON bodies if an endpoint is marked with ACCEPT_JSON