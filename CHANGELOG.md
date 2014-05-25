# Changelog

## 1.0

- Add user endpoints.

  Creating, reading, updating and deleting users and their data is now possible
  by using the `/users` endpoints. `/users/me` can be used to determine the
  current user, and returns a 401 status for non-logged in users.

  Note that the format of post authors has changed, as it is now an embedded
  User entity. This should not break backwards compatibility.

  Custom post types gain this ability automatically.

  (props @tobych, @rmccue, [#20][gh-20], [#146][gh-146])

- Add post meta endpoints.

  Creating, reading, updating and deleting post meta is now possible by using
  the `/posts/<id>/meta` endpoints. Post meta is now correctly embedded into
  Post entities.

  Meta can be updated via the Post entity (e.g. `PUT` to `/posts/<id>`) or via
  the entity itself at `/posts/<id>/meta/<mid>`. Meta deletion must be done via
  a `DELETE` request to the latter.

  Only non-protected and non-serialized meta can be accessed or manipulated via
  the API. This is not predicted to change in the future; clients wishing to
  access this data should consider alternative approaches.

  Custom post types do not currently gain this ability automatically.

  (props @attitude, @alisspers, @rachelbaker, @rmccue, @tlovett1, @tobych,
  @zedejose, [#68][gh-68], [#168][gh-168], [#189][gh-189], [#207][gh-207])

- Add endpoint for deleting a single comment.

  Clients can now send a `DELETE` request to comment routes to delete
  the comment.

  Custom post types supporting comments will gain this ability automatically.

  (props @tlovett1, @rmccue, [#178][gh-178], [#191][gh-191])

- Add endpoint for post revisions.

  Post revisions are now available at `/posts/<id>/revisions`, and are linked in
  the `meta.links.version-history` key of post entities.

  Custom post types supporting revisions will gain this ability automatically.

  (props @tlovett1, [#193][gh-193])

- Respond to requests without depending on pretty permalink settings.

  For sites without pretty permalinks enabled, the API is now available from
  `?json_route=/`. Clients should check for this via the autodiscovery methods
  (Link header or RSD).

  (props @rmccue, [#69][gh-69], [#138][gh-138])

- Add register post type argument.

  Post types can now indicate their availability via the API using the
  `show_in_json` argument passed to `register_post_type`. This value defaults to
  the `publicly_queryable` argument (which itself defaults to the
  `public` argument).

  (props @iandunn, @rmccue, [#145][gh-145])

- Remove basic authentication handler.

  **This breaks backwards compatibility** for clients using Basic
  authentication. Clients are encouraged to switch to using [OAuth
  authentication][OAuth1]. The [Basic Authentication plugin][Basic-Auth] can be
  installed for backwards compatibility and local development, however should
  not be used in production.

  (props @rmccue, [#37][gh-37], [#152][gh-152])

- Require nonces for cookie-based authentication.

  **This breaks backwards compatibility** and requires any clients using cookie
  authentication to also send a nonce with the request. The built-in Javascript
  API automatically handles this.

  (props @rmccue, [#177][gh-177], [#180][gh-180])

- Clean up deprecated methods/functions.

  Functions and methods previously deprecated in 0.8/0.9 have now been removed.
  Future deprecations will take place in the same manner as WordPress core.

  **This breaks backwards compatibility**, however these were marked as
  deprecated in previous releases.

  (props @rmccue, [#187][gh-187])

- Only expose meta on 'edit' context as a temporary workaround.

  Privacy concerns around exposing meta to all users necessitate this change.

  **This breaks backwards compatibility** as post meta data is no longer
  available to all users. Clients wishing to access this data should
  authenticate and use the `edit` context.

  (props @iandunn, @rmccue, [#135][gh-135])

- Add `json_ensure_response` function to ensure either a
  `WP_JSON_ResponseInterface` or a `WP_Error` object is returned.

  When extending the API, the `json_ensure_response` function can be used to
  ensure that any raw data returned is wrapped with a `WP_JSON_Response` object.
  This allows using `get_status`/`get_data` easily, however `WP_Error` must
  still be checked via `is_wp_error`.

  (props @rmccue, [#151][gh-151], [#154][gh-154])

- Use version option to check on init if rewrite rules should be flushed.

  Rewrite rules on multisite are now flushed via an init hook, rather than
  switching to each site on activation.

  (props @rachelbaker, [#149][gh-149])

- Fix typo in schema docs

  (props @codebykat, [#132][gh-132])

- Add check for valid JSON data before using to avoid parameter overwrite.

  When passing data to an endpoint that accepts JSON data, the data will now be
  validated before passing to the endpoint.

  (props @rachelbaker, @rmccue, [#133][gh-133])

- Add authentication property to site index.

  (props @rmccue, [#131][gh-131])

- Move the test helper to a subdirectory.

  The plugin will now no longer prompt for updates due to the helper.

  (props @rmccue, [#127][gh-127])

- Include post ID with `json_prepare_meta` filter.

  (props @rmccue, [#137][gh-137])

- Corrected parameter names in x-form examples in docs.

  (props @rachelbaker, [#134][gh-134])

- Pass `WP_JSON_Server` instance to `json_serve_request`.

  (props @alisspers, @rmccue, [#61][gh-61], [#139][gh-139])

- Don't use deprecated function in `WP_JSON_Posts::edit_post()`

  (props @rachelbaker, [#150][gh-150])

- Pass post ID to `json_insert_post` action during both insert and update.

  (props @cmmarslender, [#148][gh-148])

- Add descriptions to taxonomy term data.

  (props @pushred, [#111][gh-111])

- Ensure we handle raw data passed to the API.

  (props @tlovett1, @rmccue, [#91][gh-91], [#155][gh-155])

- Remove unused `prepare_author` method from `WP_JSON_Posts` class.

  (props @rachelbaker, [#165][gh-165])

- Add multiple post type support to get_posts method.

  (props @rmccue, [#142][gh-142], [#163][gh-163])

- Return `WP_Error` in `WP_JSON_Posts::get_comment` for invalid comments.

  (props @tlovett1, [#166][gh-166], [#171][gh-171])

- Update getting started documentation.

  (props @rmccue, [#176][gh-176])

- Improve and clarify "array" input syntax documentation.

  (props @rmccue, [#140][gh-140], [#175][gh-175])

- Update post routes documentation.

  (props @rmccue, [#172][gh-172], [#174][gh-174])

- Add documentation for user endpoints.

  (props @rachelbaker, @rmccue, [#158][gh-158])

- Add permalink settings step to Quick Setup instructions.

  (props @kadamwhite, [#183][gh-183])

- Update taxonomy collection to return indexed array.

  (props @mattheu, [#184][gh-184])

- Remove placeholder endpoints.

  (props @rmccue, [#161][gh-161], [#192][gh-192])

- Fix issues with embedded attachments.

  Checks that the post supports attachment data before adding it, and ensures we
  don't embed entities many layers deep.

  (props @rmccue, [#194][gh-194])

- Change post parent preparation context to embed.

  (props @rmccue, [#195][gh-195])

- Change server meta links to reference the WP-API organization GitHub repo.

  (props @rachelbaker, [#208][gh-208])

- Fix plugin tests

  (props @rmccue, [#215][gh-215])

- Check for errors with invalid dates and remove duplicate date parsing
  methods.

  (props @rachelbaker, @rmccue, [#216][gh-216], [#219][gh-219])

[View all changes](https://github.com/rmccue/WP-API/compare/0.9...1.0)

[OAuth1]: https://github.com/WP-API/OAuth1
[Basic-Auth]: https://github.com/WP-API/Basic-Auth
[gh-20]: https://github.com/WP-API/WP-API/issues/20
[gh-37]: https://github.com/WP-API/WP-API/issues/37
[gh-61]: https://github.com/WP-API/WP-API/issues/61
[gh-68]: https://github.com/WP-API/WP-API/issues/68
[gh-69]: https://github.com/WP-API/WP-API/issues/69
[gh-91]: https://github.com/WP-API/WP-API/issues/91
[gh-111]: https://github.com/WP-API/WP-API/issues/111
[gh-127]: https://github.com/WP-API/WP-API/issues/127
[gh-131]: https://github.com/WP-API/WP-API/issues/131
[gh-132]: https://github.com/WP-API/WP-API/issues/132
[gh-133]: https://github.com/WP-API/WP-API/issues/133
[gh-134]: https://github.com/WP-API/WP-API/issues/134
[gh-135]: https://github.com/WP-API/WP-API/issues/135
[gh-137]: https://github.com/WP-API/WP-API/issues/137
[gh-138]: https://github.com/WP-API/WP-API/issues/138
[gh-139]: https://github.com/WP-API/WP-API/issues/139
[gh-140]: https://github.com/WP-API/WP-API/issues/140
[gh-142]: https://github.com/WP-API/WP-API/issues/142
[gh-145]: https://github.com/WP-API/WP-API/issues/145
[gh-146]: https://github.com/WP-API/WP-API/issues/146
[gh-148]: https://github.com/WP-API/WP-API/issues/148
[gh-149]: https://github.com/WP-API/WP-API/issues/149
[gh-150]: https://github.com/WP-API/WP-API/issues/150
[gh-151]: https://github.com/WP-API/WP-API/issues/151
[gh-152]: https://github.com/WP-API/WP-API/issues/152
[gh-154]: https://github.com/WP-API/WP-API/issues/154
[gh-155]: https://github.com/WP-API/WP-API/issues/155
[gh-158]: https://github.com/WP-API/WP-API/issues/158
[gh-161]: https://github.com/WP-API/WP-API/issues/161
[gh-163]: https://github.com/WP-API/WP-API/issues/163
[gh-165]: https://github.com/WP-API/WP-API/issues/165
[gh-166]: https://github.com/WP-API/WP-API/issues/166
[gh-168]: https://github.com/WP-API/WP-API/issues/168
[gh-171]: https://github.com/WP-API/WP-API/issues/171
[gh-172]: https://github.com/WP-API/WP-API/issues/172
[gh-174]: https://github.com/WP-API/WP-API/issues/174
[gh-175]: https://github.com/WP-API/WP-API/issues/175
[gh-176]: https://github.com/WP-API/WP-API/issues/176
[gh-177]: https://github.com/WP-API/WP-API/issues/177
[gh-178]: https://github.com/WP-API/WP-API/issues/178
[gh-180]: https://github.com/WP-API/WP-API/issues/180
[gh-183]: https://github.com/WP-API/WP-API/issues/183
[gh-184]: https://github.com/WP-API/WP-API/issues/184
[gh-187]: https://github.com/WP-API/WP-API/issues/187
[gh-189]: https://github.com/WP-API/WP-API/issues/189
[gh-191]: https://github.com/WP-API/WP-API/issues/191
[gh-192]: https://github.com/WP-API/WP-API/issues/192
[gh-193]: https://github.com/WP-API/WP-API/issues/193
[gh-194]: https://github.com/WP-API/WP-API/issues/194
[gh-195]: https://github.com/WP-API/WP-API/issues/195
[gh-207]: https://github.com/WP-API/WP-API/issues/207
[gh-208]: https://github.com/WP-API/WP-API/issues/208
[gh-215]: https://github.com/WP-API/WP-API/issues/215
[gh-216]: https://github.com/WP-API/WP-API/issues/216
[gh-219]: https://github.com/WP-API/WP-API/issues/219

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