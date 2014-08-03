# Changelog

## 1.1.1

- Mitigate Flash CSRF exploit

  Using the API's JSONP support, it's possible to control the first bytes of the
  response sent to the browser. Combining this with an ASCII-encoded SWF allows
  arbitrary SWFs to be served from the site, allowing bypassing the same-origin
  policy built in to browsers.

  While the API includes CSRF protection and is not directly vulnerable, this
  can be used to bypass other browser origin controls.

  Reported by @iandunn on 2014-07-10.

  (props @iandunn, @rmccue, [#356][gh-356])

[View all changes](https://github.com/rmccue/WP-API/compare/1.0...1.1)

[gh-356]: https://github.com/WP-API/WP-API/issues/356

## 1.1

- Add new routes for taxonomies and terms.

  Taxonomies and terms have now been moved from the `/posts/types/<type>`
  namespace to global routes: `/taxonomies`, `/taxonomies/<tax>`,
  `/taxonomies/<tax>/terms` and `/taxonomies/<tax>/terms/<term>`

  Test coverage for taxonomy endpoints has also been increased to 100%.

  **Deprecation warning**: The `/posts/types/<type>/taxonomies` endpoint (and
  sub-endpoints with the same prefix) have been deprecated in favour of the new
  endpoints. These deprecated endpoints will now return a
  `X-WP-DeprecatedFunction` header indicating that the endpoint should not be
  used for new development, but will continue to work in the future.

  (props @kadamwhite, @rachelbaker, @rmccue, [#198][gh-198], [#211][gh-211])

- Allow customizing the API resources prefix

  The API base (typically `wp-json/`) can now be customized to a different
  prefix using the `json_url_prefix` filter. Note that rewrites will need to be
  flushed manually after changing this.

  (props @ericandrewlewis, @rmccue, [#104][gh-104], [#244][gh-244], [#278][gh-278])

- Give `null` as date for draft posts.

  Draft posts would previously return "0000-00-00 00:00:00" or
  "1970-01-01T00:00:00", as draft posts are not assigned a publish date. The API
  now returns `null` where a date is not available.

  **Compatibility warning**: Clients should be prepared to accept `null` as a
  value for date/time fields, and treat it as if no value is set.

  (props @rmccue, [#229][gh-229], [#230][gh-230])

- Fix errors with excerpt.

  Posts without excerpts could previously return nonsense strings, excerpts from
  other posts, or cause internal PHP errors. Posts without excerpts will now
  always return an excerpt, typically automatically generated from the post
  content.

  The `excerpt_raw` field was added to the edit context on posts. This field
  contains the raw excerpt data saved for the post, including empty
  string values.

  (props @rmccue, [#222][gh-226], [#226][gh-226])

- Only expose email for edit context.

  User email addresses are now only exposed for `context=edit`, which requires
  the `edit_users` permission (not required for the current user).

  The email address field will now return `false` instead of a string if the
  field is not exposed.

  (props @pkevan, @rmccue, [#290][gh-290], [#296][gh-296])

- Correct password-protected post handling.

  Password-protected posts could previously be exposed to all users, however
  could also have broken behaviour with excerpts. Password-protected posts are
  now hidden to unauthenticated users, while content and excerpts are shown
  correctly for the `edit` context.

  (Note that hiding password-protected posts is intended to be a temporary
  measure, and will likely change in the future.)

  (props @rmccue, [#286][gh-286], [#313][gh-313])

- Add documentation on authentication methods.

  Full documentation on [authentication](https://github.com/WP-API/WP-API/blob/master/docs/authentication.md)
  is now available. This documentation explains the difference between the
  various available authentication methods, and notes which should be used.

  (props @rmccue, [#242][gh-242])

- Include new client JS from github.io

  The WP-API Javascript library is now loaded dynamically from
  `wp-api.github.io` to ensure it is always up-to-date.

  (props @tlovett1, [#179][gh-179], [#240][gh-240])

- Don't allow setting the modification date on post creation/update.

  As it turns out, WP core doesn't allow us to set this, so this was previously
  a no-op anyway. Discovered during test coverage phase.

  (props @rachelbaker, @rmccue, [#285][gh-285], [#288][gh-288])

- Check post parent correctly on insertion.

  Posts could previously be added with an invalid parent ID. These IDs are now
  checked to ensure the post exists.

  (props @rmccue, [#228][gh-228], [#231][gh-231])

- Make sure the type is actually evaluated for `json_prepare_${type}` filter.

  This value was previously not interpolated correctly, due to the use of the
  single-quoted string type.

  (props @danielbachhuber, [#266][gh-266])

- Return `WP_Error` instead of array of empty objects for a revisions
  permissions error.

  Previously, when trying to access post revisions without correct permissions,
  a JSON list of internal error objects would be returned. This has been
  corrected to return a standard API error instead.

  (props @rachelbaker, @tlovett1, [#251][gh-251], [#276][gh-276])

- Flip user parameters check for insert/update.

  Previously, you could add a user without specifying username/password/email,
  but couldn't update a user without those parameters. The logic has been
  inverted here instead.

  (props @rmccue, [#221][gh-221], [#289][gh-289])

- Add revision endpoints tests

  (props @danielbachhuber, @rachelbaker, @rmccue, [#275][gh-275], [#277][gh-277], [#284][gh-284], [#279][gh-279])

- Add post endpoint testing

  Now at >54% coverage for the whole class, and >80% for the main methods. This
  figure will continue to rise over the next few releases.

  (props @rachelbaker, @rmccue, [#99][gh-99])

- Separate helper functions into global namespace.

  `WP_JSON_Server::get_timezone()`, `WP_JSON_Server::get_date_with_gmt()`,
  `WP_JSON_Server::get_avatar_url()` and ``WP_JSON_Server::parse_date()` have
  all been moved into the global namespace to decouple them from the server
  class.

  **Deprecation warning**: These methods have been deprecated. The new
  `json_get_timezone()`, `json_get_date_with_gmt()`, `json_get_avatar_url()` and
  `json_parse_date()` methods should now be used instead.

  (props @rmccue, [#185][gh-185], [#298][gh-298])

- Re-order Users and Media routes documentation based on CRUD order

  (props @rachelbaker, [#214][gh-214])

- Update Post route documentation to provide more detail for data parameter

  (props @rachelbaker, [#212][gh-212])

- Correct documentation typo ("inforcement" -> "enforcement").

  (props @ericandrewlewis, [#236][gh-236])

- Coding Standards audit

  (props @DrewAPicture, [#235][gh-235])

- Add comparison documentation.

  (props @rachelbaker, @rmccue, [#217][gh-225], [#225][gh-225])

- `json_url` filter call should be passed `$scheme`

  (props @ericandrewlewis, [#243][gh-243])

- Set `class-jsonserializable.php` file mode to 644.

  (props @jeremyfelt, [#255][gh-255])

- Remove unneeded "which" in implementation doc.

  (props @JDGrimes, [#254][gh-254])

- Fix a copy/paste error in schema doc.

  (props @JDGrimes, [#253][gh-253])

- Correct reference link in example schema.

  (props @danielbachhuber, [#258][gh-258])

- Add missing post formats to post schema documentation.

  (props @danielbachhuber, [#260][gh-260])

- Ensure we always use "public" on public methods.

  (props @danielbachhuber, [#268][gh-268])

- Ensure we don't cause a PHP error if a post does not have revisions.

  (props @rmccue, [#227][gh-227])

- Add note to where upload_files cap comes from

  (props @pkevan, [#282][gh-282])

- Add handling of `sticky` property when creating or editing posts.

  (props @rachelbaker, [#218][gh-218])

- Update post route endpoint docs to include details on `post_meta` handling.

  (props @rachelbaker, [#213][gh-213])

- Update main readme file to better describe the project.

  (props @rmccue, [#303][gh-303])

- Fix `--data-binary` cURL option in documentation

  (props @Pezzab, @rachelbaker, @rmccue, [#283][gh-283], [#304][gh-304])

[View all changes](https://github.com/rmccue/WP-API/compare/1.0...1.1)

[gh-99]: https://github.com/WP-API/WP-API/issues/99
[gh-104]: https://github.com/WP-API/WP-API/issues/104
[gh-179]: https://github.com/WP-API/WP-API/issues/179
[gh-185]: https://github.com/WP-API/WP-API/issues/185
[gh-198]: https://github.com/WP-API/WP-API/issues/198
[gh-211]: https://github.com/WP-API/WP-API/issues/211
[gh-212]: https://github.com/WP-API/WP-API/issues/212
[gh-213]: https://github.com/WP-API/WP-API/issues/213
[gh-214]: https://github.com/WP-API/WP-API/issues/214
[gh-218]: https://github.com/WP-API/WP-API/issues/218
[gh-221]: https://github.com/WP-API/WP-API/issues/221
[gh-225]: https://github.com/WP-API/WP-API/issues/225
[gh-225]: https://github.com/WP-API/WP-API/issues/225
[gh-226]: https://github.com/WP-API/WP-API/issues/226
[gh-226]: https://github.com/WP-API/WP-API/issues/226
[gh-227]: https://github.com/WP-API/WP-API/issues/227
[gh-228]: https://github.com/WP-API/WP-API/issues/228
[gh-229]: https://github.com/WP-API/WP-API/issues/229
[gh-230]: https://github.com/WP-API/WP-API/issues/230
[gh-231]: https://github.com/WP-API/WP-API/issues/231
[gh-235]: https://github.com/WP-API/WP-API/issues/235
[gh-236]: https://github.com/WP-API/WP-API/issues/236
[gh-240]: https://github.com/WP-API/WP-API/issues/240
[gh-242]: https://github.com/WP-API/WP-API/issues/242
[gh-243]: https://github.com/WP-API/WP-API/issues/243
[gh-244]: https://github.com/WP-API/WP-API/issues/244
[gh-251]: https://github.com/WP-API/WP-API/issues/251
[gh-253]: https://github.com/WP-API/WP-API/issues/253
[gh-254]: https://github.com/WP-API/WP-API/issues/254
[gh-255]: https://github.com/WP-API/WP-API/issues/255
[gh-258]: https://github.com/WP-API/WP-API/issues/258
[gh-260]: https://github.com/WP-API/WP-API/issues/260
[gh-266]: https://github.com/WP-API/WP-API/issues/266
[gh-268]: https://github.com/WP-API/WP-API/issues/268
[gh-275]: https://github.com/WP-API/WP-API/issues/275
[gh-276]: https://github.com/WP-API/WP-API/issues/276
[gh-277]: https://github.com/WP-API/WP-API/issues/277
[gh-278]: https://github.com/WP-API/WP-API/issues/278
[gh-279]: https://github.com/WP-API/WP-API/issues/279
[gh-282]: https://github.com/WP-API/WP-API/issues/282
[gh-283]: https://github.com/WP-API/WP-API/issues/283
[gh-284]: https://github.com/WP-API/WP-API/issues/284
[gh-285]: https://github.com/WP-API/WP-API/issues/285
[gh-286]: https://github.com/WP-API/WP-API/issues/286
[gh-288]: https://github.com/WP-API/WP-API/issues/288
[gh-289]: https://github.com/WP-API/WP-API/issues/289
[gh-290]: https://github.com/WP-API/WP-API/issues/290
[gh-296]: https://github.com/WP-API/WP-API/issues/296
[gh-298]: https://github.com/WP-API/WP-API/issues/298
[gh-303]: https://github.com/WP-API/WP-API/issues/303
[gh-304]: https://github.com/WP-API/WP-API/issues/304
[gh-313]: https://github.com/WP-API/WP-API/issues/313

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