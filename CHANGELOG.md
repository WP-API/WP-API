# Changelog

## 0.9

- Move from `wp-json.php/` to `wp-json/`

  **This breaks backwards compatibility** and requires any clients to now use
  `wp-json/`, or preferably the new RSD/Link headers.

  (props @rmccue, @matrixik, [#46][gh-46], [#96][gh-96], [#106][gh-106])

- Move filter registration out of CPT constructor. CPT subclasses now require
  you to call `$myobject->register_filters()`, in order to move global state out
  of the constructor.

  **This breaks backwards compatibility** and requires any subclassing to now
  call `$myobject->register_filters()`

  (props @rmccue, @thenbrent, [#42][gh-42], [#126][gh-126])

- Introduce Response/ResponseInterface

  Endpoints that need to set headers or response codes should now return a
  `WP_JSON_Response` rather than using the server methods.
  `WP_JSON_ResponseInterface` may also be used for more flexible use of the
  response methods.

  **Deprecation warning:** Calling `WP_JSON_Server::header`,
  `WP_JSON_Server::link_header` and `WP_JSON_Server::query_navigation_headers`
  is now deprecated. This will be removed in 1.0.

  (props @rmccue, [#33][gh-33])

- Change all semiCamelCase names to underscore_case.

  **Deprecation warning**: Any calls to semiCamelCase methods require any
  subclassing to update method references. This will be removed in 1.0.

  (props @osiux, [#36][gh-36], [#82][gh-82])

- Add multisite compatibility. If the plugin is network activated, the plugin is
  now activated once-per-site, so `wp-json/` is always site-local.

  (props @rachelbaker, [#48][gh-48], [#49][gh-49])

- Add RSD and Link headers for discovery

  (props @rmccue, [#40][gh-40])

- WP_JSON_Posts->prepare_author() now verifies the `$user` object is set.

  (props @rachelbaker, [#51][gh-51], [#54][gh-54])

- Added unit testing framework. Currently only a smaller number of tests, but we
  plan to increase this significantly as soon as possible.

  (props @tierra, @osiux, [#65][gh-65], [#76][gh-76], [#84][gh-84])

- Link collection filtering docs to URL formatting guide.

  (props @kadamwhite, [#74][gh-74])

- Remove hardcoded `/pages` references from `WP_JSON_Pages`

  (props @rmccue, @thenbrent, [#28][gh-28], [#78][gh-78])

- Fix compatibility with `DateTime::createFromFormat` on PHP 5.2

  (props @osiux, [#52][gh-52], [#79][gh-79])

- Document that `WP_JSON_CustomPostType::__construct()` requires a param of type
  `WP_JSON_ResponseHandler`.

  (props @tlovett1, [#88][gh-88])

- Add timezone parameter to WP_JSON_DateTime::createFromFormat()

  (props @royboy789, @rachelbaker, [#85][gh-85], [#87][gh-87])

- Remove IXR references. `IXR_Error` is no longer accepted as a return value.

  **This breaks backwards compatibility** and requires anyone returning
  `IXR_Error` objects to now return `WP_Error` or `WP_JSON_ResponseInterface`
  objects.

  (props @rmccue, [#50][gh-50], [#77][gh-77])

- Fix bugs with attaching featured images to posts:
  - `WP_JSON_Media::attachThumbnail()` should do nothing if `$update` is false
    without a post ID
  - The post ID must be fetched from the `$post` array.

  (props @Webbgaraget, [#55][gh-55])

- Don't declare `jsonSerialize` on ResponseInterface

  (props @rmccue, [#97][gh-97])

- Allow JSON post creation/update for `WP_JSON_CustomPostType`

  (props @tlovett1, [#90][gh-90], [#108][gh-108])

- Return null if post doesn't have an excerpt

  (props @rachelbacker, [#72][gh-72])

- Fix link to issue tracker in README

  (props @rmccue, @tobych, [#125][gh-125])

[View all changes](https://github.com/rmccue/WP-API/compare/0.8...0.9)

[gh-28]: https://github.com/WP-API/WP-API/issues/28
[gh-33]: https://github.com/WP-API/WP-API/issues/33
[gh-36]: https://github.com/WP-API/WP-API/issues/36
[gh-40]: https://github.com/WP-API/WP-API/issues/40
[gh-42]: https://github.com/WP-API/WP-API/issues/42
[gh-46]: https://github.com/WP-API/WP-API/issues/46
[gh-48]: https://github.com/WP-API/WP-API/issues/48
[gh-49]: https://github.com/WP-API/WP-API/issues/49
[gh-50]: https://github.com/WP-API/WP-API/issues/50
[gh-51]: https://github.com/WP-API/WP-API/issues/51
[gh-52]: https://github.com/WP-API/WP-API/issues/52
[gh-54]: https://github.com/WP-API/WP-API/issues/54
[gh-55]: https://github.com/WP-API/WP-API/issues/55
[gh-65]: https://github.com/WP-API/WP-API/issues/65
[gh-72]: https://github.com/WP-API/WP-API/issues/72
[gh-74]: https://github.com/WP-API/WP-API/issues/74
[gh-76]: https://github.com/WP-API/WP-API/issues/76
[gh-77]: https://github.com/WP-API/WP-API/issues/77
[gh-78]: https://github.com/WP-API/WP-API/issues/78
[gh-79]: https://github.com/WP-API/WP-API/issues/79
[gh-82]: https://github.com/WP-API/WP-API/issues/82
[gh-84]: https://github.com/WP-API/WP-API/issues/84
[gh-85]: https://github.com/WP-API/WP-API/issues/85
[gh-87]: https://github.com/WP-API/WP-API/issues/87
[gh-88]: https://github.com/WP-API/WP-API/issues/88
[gh-90]: https://github.com/WP-API/WP-API/issues/90
[gh-96]: https://github.com/WP-API/WP-API/issues/96
[gh-97]: https://github.com/WP-API/WP-API/issues/97
[gh-106]: https://github.com/WP-API/WP-API/issues/106
[gh-108]: https://github.com/WP-API/WP-API/issues/108
[gh-125]: https://github.com/WP-API/WP-API/issues/125
[gh-126]: https://github.com/WP-API/WP-API/issues/126

## 0.8
- Add compatibility layer for JsonSerializable. You can now return arbitrary
  objects from endpoints and use the `jsonSerialize()` method to return the data
  to serialize instead of just using the properties of the object.

  (props @rmccue, [#24][gh-24])

- Fix page parent links to use `/pages`

  (props @thenbrent, [#27][gh-27])

- Remove redundant `WP_JSON_Pages::type_archive_link()` function

  (props @thenbrent, [#29][gh-29])

- Removed unneeded executable bit on all files

  (props @tierra, [#31][gh-31])

- Don't include the `featured_image` property for post types that don't
  support thumbnails

  (props @phh, [#43][gh-43])

- Use `wp_json_server_before_serve` instead of `plugins_loaded` in the Extending
  documentation for plugins

  (props @phh, [#43][gh-43])

- Parse the avatar URL from the `get_avatar()` function in core, allowing custom
  avatar implementations

  (props @rachelbaker, [#47][gh-47], [#35][gh-35])

- Ensure that the author is set if passed

  (props @kuchenundkakao, [#44][gh-44])

- Clarify the usage of `WP_JSON_CustomPostType` in plugins

  (props @rmccue, [#45][gh-45])

- Ensure JSON disabled error messages are translated

  (props @rmccue, [#38][gh-38])

- Remove extra "Link: " from link headers

  (props @jmusal, [#56][gh-56], [#30][gh-30])

- Remove redundant `get_avatar` method in `WP_JSON_Posts`

  (props @rachelbaker, [#35][gh-35])

- Rename `WP_JSON_Server::get_avatar()` to `WP_JSON_Server::get_avatar_url()`

  (props @rachelbaker, [#35][gh-35])

[View all changes](https://github.com/rmccue/WP-API/compare/0.7...0.8)

[gh-24]: https://github.com/WP-API/WP-API/issues/24
[gh-27]: https://github.com/WP-API/WP-API/issues/27
[gh-29]: https://github.com/WP-API/WP-API/issues/29
[gh-30]: https://github.com/WP-API/WP-API/issues/30
[gh-31]: https://github.com/WP-API/WP-API/issues/31
[gh-35]: https://github.com/WP-API/WP-API/issues/35
[gh-38]: https://github.com/WP-API/WP-API/issues/38
[gh-43]: https://github.com/WP-API/WP-API/issues/43
[gh-43]: https://github.com/WP-API/WP-API/issues/43
[gh-44]: https://github.com/WP-API/WP-API/issues/44
[gh-45]: https://github.com/WP-API/WP-API/issues/45
[gh-47]: https://github.com/WP-API/WP-API/issues/47
[gh-56]: https://github.com/WP-API/WP-API/issues/56

## 0.7
- The response handler object is now passed into the endpoint objects via the
  constructor, allowing you to avoid excess global state where possible. It's
  recommended to use this where possible rather than the global object.

  (props @rmccue, [#2][gh-2])

- Fix undefined variables and indices
  (props @pippinsplugins, [#5][gh-5])

- Correct call to deactivation hook
  (props @ericpedia, [#9][gh-9])

- Check metadata access correctly rather than always hiding for users without
  the `edit_post_meta` capability
  (props @kokarn, [#10][gh-10])

- Return all term metadata, rather than just the last one
  (props @afurculita, [#13][gh-13])

- Access post metadata from cache where possible - Note, this is a backwards
  compatibility break, as the format of the metadata has changed. This may
  change again in the near future, so don't rely on it until 1.0.
  (props @afurculita, [#14][gh-14])

- Add term_link to prepare_term
  (props @afurculita, [#15][gh-15])

- Fix hardcoded `/pages` references in `WP_JSON_CustomPostType`
  (props @thenbrent, [#26][gh-26])

- Sanitize headers for newlines
  (props @kokarn, [#7][gh-7])

- Register rewrite rules during plugin activation
  (props @pippinsplugins, [#17][gh-17])

[View all changes](https://github.com/rmccue/WP-API/compare/0.6...0.7)

[gh-2]:  https://github.com/WP-API/WP-API/issues/2
[gh-5]:  https://github.com/WP-API/WP-API/issues/5
[gh-7]:  https://github.com/WP-API/WP-API/issues/7
[gh-9]:  https://github.com/WP-API/WP-API/issues/9
[gh-10]: https://github.com/WP-API/WP-API/issues/10
[gh-13]: https://github.com/WP-API/WP-API/issues/13
[gh-14]: https://github.com/WP-API/WP-API/issues/14
[gh-15]: https://github.com/WP-API/WP-API/issues/15
[gh-17]: https://github.com/WP-API/WP-API/issues/17
[gh-26]: https://github.com/WP-API/WP-API/issues/26

## 0.6
- Huge documentation update - Guides on getting started and extending the API
	are [now available for your perusal][docs]
- Add generic CPT class - Plugins are now encouraged to extend
	`WP_JSON_CustomPostType` and get free hooking for common actions. This
	removes most of the boilerplate that you needed to write for new CPT-based
	routes and endpoints ([#380][])
- Use defined filter priorities for endpoint registration - It's now easier to
	inject your own endpoints at a defined point
- Update the schema - Now includes documentation on the Media entity, plus more
	([#264][])
- Add better taxonomy support - You can now query for taxonomies and terms
	directly. The routes here might seem strange
	(`/posts/types/post/taxonomies/category` for example), but the intention is
	to [future-proof them](http://make.wordpress.org/core/2013/07/28/potential-roadmap-for-taxonomy-meta-and-post-relationships/)
	as much as possible([#275][])
- Ensure the JSON URL is relative to the home URL ([#375][])
- Check all date formats for If-Unmodified-Since ([#378][])
- Register the correct URL for the JS library ([#376][])
- Correct the usage of meta links ([#379][])
- Add filters for post type and post status data ([#380][])
- Separate parent post and parent comment relation ([#330][]()

[View all changes](https://github.com/rmccue/WP-API/compare/0.5...0.6)

[docs]: https://github.com/rmccue/WP-API/tree/master/docs

[#264]: https://gsoc.trac.wordpress.org/ticket/264
[#275]: https://gsoc.trac.wordpress.org/ticket/275
[#330]: https://gsoc.trac.wordpress.org/ticket/330
[#375]: https://gsoc.trac.wordpress.org/ticket/375
[#376]: https://gsoc.trac.wordpress.org/ticket/376
[#378]: https://gsoc.trac.wordpress.org/ticket/378
[#379]: https://gsoc.trac.wordpress.org/ticket/379
[#380]: https://gsoc.trac.wordpress.org/ticket/380


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