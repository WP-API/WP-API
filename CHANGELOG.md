# Changelog

## 2.0 Beta 12.0 (February 9, 2016)

- BREAKING CHANGE: Removes meta endpoints from primary plugin.

  If your project depends on post meta endpoints, please install [WP REST API Meta Endpoints](https://wordpress.org/plugins/rest-api-meta-endpoints/). For the gory history of meta, read [#1425](https://github.com/WP-API/WP-API/issues/1425) and linked issues. At this time, we recommend using `register_rest_field()` to expose meta ([docs](http://v2.wp-api.org/extending/modifying/)).

  (props @danielbachhuber, [#2172](https://github.com/WP-API/WP-API/pull/2172))

- BREAKING CHANGE: Returns original resource when deleting PTCU.

  Now that all resources require the `force` param, we don't need to wrap delete responses with the `trash` state.

  (props @danielbachhuber, [#2163](https://github.com/WP-API/WP-API/pull/2163))

- BREAKING CHANGE: Uses `roles` rather than `role` in the Users controller.

  Building the REST API gives us the opportunity to standardize on `roles`, instead of having both `roles` and `role`.

  (props @joehoyle, [#2177](https://github.com/WP-API/WP-API/pull/2177))

- BREAKING CHANGES: Moves to consistent use of `context` throughout controllers.

  Contexts limit the data present in the response. Here's how to think of them: `embed` correlates with sidebar representation, `view` represents the primary public view, and `edit` is the data expected for an editor.

  (props @danielbachhuber, [#2205](https://github.com/WP-API/WP-API/pull/2205), [#2204](https://github.com/WP-API/WP-API/pull/2204), [#2203](https://github.com/WP-API/WP-API/pull/2203), [#2218](https://github.com/WP-API/WP-API/pull/2218), [#2216](https://github.com/WP-API/WP-API/pull/2216), [#2230](https://github.com/WP-API/WP-API/pull/2230), [#2184](https://github.com/WP-API/WP-API/pull/2184), [#2235](https://github.com/WP-API/WP-API/pull/2235))

- BREAKING CHANGE: Removes `post_*` query param support for `GET /wp/v2/comments`.

  The proper pattern is to use `GET /wp/v2/posts` to fetch the post IDs to limit the request to.

  (props @danielbachhuber, [#2165](https://github.com/WP-API/WP-API/pull/2165))

- BREAKING CHANGE: Introduces `rest_validate_request_arg()`/`rest_sanitize_request_arg()`.

  Dedicated functions means we can use them for validating / sanitizing query args too. Removes `WP_REST_Controller::validate_schema_property()` and `WP_REST_Controller::sanitize_schema_property()`.

  (props @danielbachhuber, [#2166](https://github.com/WP-API/WP-API/pull/2166), [#2213](https://github.com/WP-API/WP-API/pull/2213))

- Requires minimum value of 1 for `page` param.

  (props @danielbachhuber, [#2241](https://github.com/WP-API/WP-API/pull/2241))

- Introduces `media_type` and `mime_type` params for `GET /wp/v2/media`.

  (props @danielbachhuber, [#2231](https://github.com/WP-API/WP-API/pull/2231))

- Uses the term cache for post data.

  (props @rmccue, [#2234](https://github.com/WP-API/WP-API/pull/2234))

- Supports for querying comments where `post=0`.

  (props @danielbachhuber, [#1865](https://github.com/WP-API/WP-API/pull/1865))

- Exposes taxonomy and post type capabilities in `context=edit`.

  (props @danielbachhuber, [#2216](https://github.com/WP-API/WP-API/pull/2216))

- Errors early when user can't GET types or taxonomies when `context=edit`.

  (props @danielbachhuber, [#2218](https://github.com/WP-API/WP-API/pull/2218))

- Passes original $request context to `prepare_items_query`.

  (props @danielbachhuber, [#2211](https://github.com/WP-API/WP-API/pull/2211))

- Adds `parent` and `parent_exclude` params to GET Comments.

  (props @danielbachhuber, [#2206](https://github.com/WP-API/WP-API/pull/2206))

- Enforces minimum 1 and maximum 100 values for `per_page` parameter.

  (props @danielbachhuber, [#2209](https://github.com/WP-API/WP-API/pull/2209))

- Adds `author` and `author_exclude` params to GET Posts and Comments.

  (props @danielbachhuber, [#2200](https://github.com/WP-API/WP-API/pull/2202), [#2200](https://github.com/WP-API/WP-API/pull/2202))

- Adds `menu_order` param for `GET` Pages; support `menu_order` orderby.

  (props @danielbachhuber, [#2193](https://github.com/WP-API/WP-API/pull/2193))

- Only calls `sanitize_text_field()` when sanitizing `type=string,format=email`.

  (props @danielbachhuber, [#2185](https://github.com/WP-API/WP-API/pull/2185))

- Validates `GET /wp/v2/comments` private query params.

  Returns an error when user doesn't have permission to use them, instead of silently discarding.

  (props @danielbachhuber, [#2178](https://github.com/WP-API/WP-API/pull/2178))

- Explicitly prevents uploading attachments to other attachments or revisions.

  (props @danielbachhuber, [#2180](https://github.com/WP-API/WP-API/pull/2180))

- Permits user urls to be edited through the API.

  (props @danielbachhuber, [#2182](https://github.com/WP-API/WP-API/pull/2182))

- Marks all Status, Type and Taxonomy fields as `readonly`.

  (props @danielbachhuber, [#2181](https://github.com/WP-API/WP-API/pull/2181))

- Adds validation callbacks to collection query params.

  (props @danielbachhuber, [#2170](https://github.com/WP-API/WP-API/pull/2170), [#2171](https://github.com/WP-API/WP-API/pull/2171), [#2176](https://github.com/WP-API/WP-API/pull/2176), [#2174](https://github.com/WP-API/WP-API/pull/2174), [#2175](https://github.com/WP-API/WP-API/pull/2175))

- Links taxonomy terms to the post type collections they support.

  (props @danielbachhuber, [#2167](https://github.com/WP-API/WP-API/pull/2167))

- Returns error when making a `GET` request with invalid context.

  (props @danielbachhuber, [#2169](https://github.com/WP-API/WP-API/pull/2169))

- Adds `trash` status to `GET /wp/v2/statuses`.

  (props @danielbachhuber, [#2158](https://github.com/WP-API/WP-API/pull/2158))

- Indicates when fields have HTML in schema.

  (props @joehoyle, [#2159](https://github.com/WP-API/WP-API/pull/2159))

- Permits viewing of User who has published any Public posts.

  (props @danielbachhuber, [#2155](https://github.com/WP-API/WP-API/pull/2155))

- Respects `show_avatars` option when adding avatars to Users.

  (props @nullvariable, [#2151](https://github.com/WP-API/WP-API/pull/2151))

- Controllers use `$namespace` and `$rest_base` class variables for easier subclassing.

  (props @danielbachhuber, [#2119](https://github.com/WP-API/WP-API/pull/2119), [#2130](https://github.com/WP-API/WP-API/pull/2130), [#2131](https://github.com/WP-API/WP-API/pull/2131), [#2132](https://github.com/WP-API/WP-API/pull/2132), [#2133](https://github.com/WP-API/WP-API/pull/2133), [#2134](https://github.com/WP-API/WP-API/pull/2134), [#2139](https://github.com/WP-API/WP-API/pull/2139), [#2141](https://github.com/WP-API/WP-API/pull/2141), [#2142](https://github.com/WP-API/WP-API/pull/2142))

## 2.0 Beta 11.0 (January 25, 2016)

- BREAKING CHANGE: Moves Post->Term relations to the Post Resource

  Previously, a client would fetch a Post's Tags with `GET /wp/v2/posts/<id>/tags`.

  In Beta 11, an array of term ids is included on the Post resource.

  The collection of terms for a Post can be fetched with `GET /wp/v2/tags?post=<id>`.

  The `WP_REST_Posts_Terms_Controller` class no longer exists.

  (props @joehoyle, [#2063](https://github.com/WP-API/WP-API/pull/2063))

- BREAKING CHANGE: Adds latest JS client including a minified version.

  See pull request for a summarized changelog.

  (props @adamsilverstein, [#1981](https://github.com/WP-API/WP-API/pull/1981))

- BREAKING CHANGE: Changes `featured_image` attribute on Posts to `featured_media`.

  While featuring other attachment types isn't yet officially supported, this makes it easier for us to introduce the possibility in the future.

  (props @danielbachhuber, [#2044](https://github.com/WP-API/WP-API/pull/2044))

- BREAKING CHANGE: Uses discrete schema title for categories and tags.

  If you've used `register_rest_field( 'term' )`, you'll need to change `'term'` to `'tag'` and/or `'category'`.

  (props @danielbachhuber, [#2005](https://github.com/WP-API/WP-API/pull/2005))

- BREAKING CHANGE: Makes many filters dynamic based on the controller type.

  If you were using the `rest_prepare_term` filter, you'll need to change it to `rest_prepare_post_tag` or `rest_prepare_category`.

  If you were using `rest_post_query` or `rest_terms_query`, you'll need update your use to `rest_page_query`, etc.

  If you were using `rest_post_trashable`, `rest_insert_post` or `rest_delete_post`, they are now dynamic based on the post type slug.

  (props @danielbachhuber, [#2008](https://github.com/WP-API/WP-API/pull/2008), [#2010](https://github.com/WP-API/WP-API/pull/2010), [#2057](https://github.com/WP-API/WP-API/pull/2057), [#2058](https://github.com/WP-API/WP-API/pull/2058))

- Renames `GET /wp/v2/comments` `user` param to `author` to match resource attribute.

  Not a breaking change, because it didn't work in the first place.

  (props @danielbachhuber, [#2105](https://github.com/WP-API/WP-API/pull/2105))

- Adds support for `GET /wp/v2/pages parent=1,2,3`.

  (props @danielbachhuber, [#2101](https://github.com/WP-API/WP-API/pull/2101))

- Persists image metadata title and caption when not present in the request.

  (props @danielbachhuber, [#2079](https://github.com/WP-API/WP-API/pull/2079))

- Add `parent_exclude` param to `GET /wp/v2/posts`.

  (props @danielbachhuber, [#2077](https://github.com/WP-API/WP-API/pull/2077))

- Adds `slug` param support for collections of Posts, Users, and Taxonomy Terms.

  (props @danielbachhuber, [#2071](https://github.com/WP-API/WP-API/pull/2071), [#2072](https://github.com/WP-API/WP-API/pull/2072), [#2103](https://github.com/WP-API/WP-API/pull/2103))

- When a comment is already trashed, returns `410:rest_already_trashed`.

  (props @danielbachhuber, [#2069](https://github.com/WP-API/WP-API/pull/2069))

- Filter the responses by context after processing additional fields.

  (props @danielbachhuber, [#2067](https://github.com/WP-API/WP-API/pull/2067))

- Adds `offset` param support for collections of Posts, Users, Comments, and Taxonomy Terms.

  (props @danielbachhuber, [#2061](https://github.com/WP-API/WP-API/pull/2061), [#2062](https://github.com/WP-API/WP-API/pull/2062), [#2064](https://github.com/WP-API/WP-API/pull/2064), [#2076](https://github.com/WP-API/WP-API/pull/2076))

- Adds `rest_insert_{$taxonomy}` and `rest_delete_{$taxonomy}` actions.

  (props @danielbachhuber, [#2060](https://github.com/WP-API/WP-API/pull/2060))

- Provides more helpful error message/code on Post Create/Update fail.

  (props @danielbachhuber, [#2053](https://github.com/WP-API/WP-API/pull/2053))

- Forces `GET /wp/v2/media` to be limited to `'status' => [ inherit, private, trash ]`

  (props @danielbachhuber, [#2026](https://github.com/WP-API/WP-API/pull/2026))

- Uses more correct error code for `Comment::delete` permission check.

  (props @danielbachhuber, [#2054](https://github.com/WP-API/WP-API/pull/2054))

- Calls `prepare_item_for_response()` directly in create and update methods.

  This lets us pass the original request through, giving the method and its filter genuine context, and avoids an
unnecessary call to `get_item()`.

  (props @danielbachhuber, [#2038](https://github.com/WP-API/WP-API/pull/2038), [#2040](https://github.com/WP-API/WP-API/pull/2040), [#2041](https://github.com/WP-API/WP-API/pull/2041), [#2043](https://github.com/WP-API/WP-API/pull/2043), [#2042](https://github.com/WP-API/WP-API/pull/2042))

- Moves permission check methods across controllers.

  Placing them above the method they're supposed to check makes the code more readable.

  (props @danielbachhuber, [#2030](https://github.com/WP-API/WP-API/pull/2030), [#2029](https://github.com/WP-API/WP-API/pull/2029), [#2034](https://github.com/WP-API/WP-API/pull/2034), [#2036](https://github.com/WP-API/WP-API/pull/2036), [#2037](https://github.com/WP-API/WP-API/pull/2037), [#2035](https://github.com/WP-API/WP-API/pull/2035), [#2039](https://github.com/WP-API/WP-API/pull/2039))

- Requires `force` argument for `DELETE /wp/v2/<taxonomy>/<id>`.

  (props @danielbachhuber, [#2028](https://github.com/WP-API/WP-API/pull/2028))

- Conditionally requires and defines REST API classes and functions.

  (props @danielbachhuber, [#2023](https://github.com/WP-API/WP-API/pull/2023), [#2024](https://github.com/WP-API/WP-API/pull/2024))

- Avoid a duplicate query for the comment count.

  (props @rmccue, [#2015](https://github.com/WP-API/WP-API/pull/2015))

- Parses `$date` if available in `prepare_date_response()`

  (props @adamsilverstein, [#1951](https://github.com/WP-API/WP-API/pull/1951))

- Abstracts `POST /wp/v2/media` permissions check.

  (props @danielbachhuber, [#2003](https://github.com/WP-API/WP-API/pull/2003))

- Adds `exclude` param to getting collections of Posts, Users, Comments, and Taxonomy Terms.

  (props @danielbachhuber, [#1998](https://github.com/WP-API/WP-API/pull/1998), [#1999](https://github.com/WP-API/WP-API/pull/1999), [#2000](https://github.com/WP-API/WP-API/pull/2000), [#2002](https://github.com/WP-API/WP-API/pull/2002))

- Adds `rest_comment_query` for filtering `GET /wp/v2/comments`.

  (props @danielbachhuber, [#2007](https://github.com/WP-API/WP-API/pull/2007))

- Uses HTTP status code `500` for `db_update_error` when creating an attachment.

  (props @danielbachhuber, [#1993](https://github.com/WP-API/WP-API/pull/1993))

- Adds helpful description to `force` param across all `DELETE` registrations

  (props @danielbachhuber, [#2004](https://github.com/WP-API/WP-API/pull/2004), [#2027](https://github.com/WP-API/WP-API/pull/2027))

- In `GET /wp/v2/<taxonomy>`, drops support for `orderby=>term_id`.

  Only one `id` is exposed through the REST API.

  (props @danielbachhuber, [#1990](https://github.com/WP-API/WP-API/pull/1990))

## 2.0 Beta 10.0 (January 11, 2016)

- SECURITY: Ensure media of private posts are private too.

  Reported by @danielbachhuber on 2016-01-08.

- BREAKING CHANGE: Removes compatibility repo for WordPress 4.3.

  WordPress 4.4 is now the minimum supported WordPress version.

  (props @danielbachhuber, [#1848](https://github.com/WP-API/WP-API/pull/1848))

- BREAKING CHANGE: Changes link relation for types and taxonomies.

  In Beta 9, this link relation was introduced as `item`, which isn't correct. The relation has been changed to `https://api.w.org/items`.

  (props @danielbachhuber, [#1853](https://github.com/WP-API/WP-API/pull/1853))

- BREAKING CHANGE: Introduces `edit` context for `wp/v2/types` and `wp/v2/taxonomies`.

  Some fields have moved into this context, which require `edit_posts` and `manage_terms`, respectively.

  (props @danielbachhuber, [#1894](https://github.com/WP-API/WP-API/pull/1894), [#1864](https://github.com/WP-API/WP-API/pull/1864))

- BREAKING CHANGE: Removes `post_format` as a term `_link` for Posts.

  Post formats aren't a custom taxonomy in the eyes of the REST API.

  (props @danielbachhuber, [#1854](https://github.com/WP-API/WP-API/pull/1854))

- Declares `parent` query param for Pages.

  (props @danielbachhuber, [#1975](https://github.com/WP-API/WP-API/pull/1975))

- Permits logged-in users to query for media.

  (props @danielbachhuber, [#1973](https://github.com/WP-API/WP-API/pull/1973))

- Removes duplicated query params from Terms controller.

  (props @danielbachhuber, [#1963](https://github.com/WP-API/WP-API/pull/1963))

- Adds `include` param to `/wp/v2/posts`, `/wp/v2/users`, `/wp/v2/<taxonomy>` and `/wp/v2/comments`.

  (props @danielbachhuber, [#1961](https://github.com/WP-API/WP-API/pull/1961), [#1964](https://github.com/WP-API/WP-API/pull/1964), [#1968](https://github.com/WP-API/WP-API/pull/1968), [#1971](https://github.com/WP-API/WP-API/pull/1971))

- Ensures `GET /wp/v2/posts` respects `order` and `orderby` params.

  (props @danielbachhuber, [#1962](https://github.com/WP-API/WP-API/pull/1962))

- Fixes fatal by loading `wp-admin/includes/user.php` to expose `wp_delete_user()`.

  (props @danielbachhuber, [#1958](https://github.com/WP-API/WP-API/pull/1958))

- Permits making a post sticky when also supplying an empty password.

  (props @westonruter, [#1949](https://github.com/WP-API/WP-API/pull/1949))

- Uses `WP_REST_Request` internally across controllers.

  (props @danielbachhuber, [#1933](https://github.com/WP-API/WP-API/pull/1933), [#1939](https://github.com/WP-API/WP-API/pull/1939), [#1934](https://github.com/WP-API/WP-API/pull/1934), [#1938](https://github.com/WP-API/WP-API/pull/1938))

- Cleans up permissions checks in `WP_REST_Terms_Controller`.

  (props @danielbachhuber, [#1941](https://github.com/WP-API/WP-API/pull/1941))

- Uses `show_in_rest` to determine publicness for post types.

  (props @danielbachhuber, [#1942](https://github.com/WP-API/WP-API/pull/1942))

- Makes `description` strings available for translation.

  (props @danielbachhuber, [#1944](https://github.com/WP-API/WP-API/pull/1944))

- Checks `assign_terms` cap for taxonomy when managing post terms.

  (props @danielbachhuber, [#1940](https://github.com/WP-API/WP-API/pull/1940))

- Defer to `edit_posts` of the custom post type when accessing private query vars.

  (props @danielbachhuber, [#1886](https://github.com/WP-API/WP-API/pull/1886))

- Allows Terms collection params to be filtered.

  (props @rachelbaker, [#1882](https://github.com/WP-API/WP-API/pull/1882))

- Renames post terms create/delete permissions callback.

  (props @wpsmith, [#1923](https://github.com/WP-API/WP-API/pull/1923))

- Fixes invalid use of 'uri' as schema `type`.

  (props @wpsmith, [#1913](https://github.com/WP-API/WP-API/pull/1913))

- Casts integer with (int) over intval for speed.

  (props @wpsmith, [#1907](https://github.com/WP-API/WP-API/pull/1907))

- Fixes PHP Doc typo for `validate_schema_property` and `sanitize_schema_property`.

  (props @wpsmith, @danielbachhuber, [#1909](https://github.com/WP-API/WP-API/pull/1909), [#1910](https://github.com/WP-API/WP-API/pull/1910))

- Adds a helpful description to the `filter` argument.

  (props @danielbachhuber, [#1885](https://github.com/WP-API/WP-API/pull/1885))

- Changes order of Users response to match schema order.

  (props @rachelbaker, [#1879](https://github.com/WP-API/WP-API/pull/1879))

- Adjusts Posts pagination headers for `filter` params.

  (props @rachelbaker, [#1878](https://github.com/WP-API/WP-API/pull/1878))

- Uses proper status code when failing to get comments of private post.

  (props @danielbachhuber, [#1866](https://github.com/WP-API/WP-API/pull/1867))

- Fixes invalid capability for comments get items permissions callback.

  `manage_comments` doesn't exist; `moderate_comments` does.

  (props @danielbachhuber, [#1866](https://github.com/WP-API/WP-API/pull/1866))

- Permits creating comments without an assigned post.

  (props @danielbachhuber, [#1857](https://github.com/WP-API/WP-API/pull/1857))

- Prevents error notice when `show_in_rest` isn't set for a post type.

  (props @danielbachhuber, [#1852](https://github.com/WP-API/WP-API/pull/1852))

## 2.0 Beta 9.0 (December 11, 2015)

- BREAKING CHANGE: Move tags and categories to top-level endpoints.

  Tags are now accessible at `/wp/v2/tags`, and categories accessible at `/wp/v2/categories`. Post terms reside at `/wp/v2/posts/<id>/tags` and `/wp/v2/<id>/categories`.

  (props @danielbachhuber, [#1802](https://github.com/WP-API/WP-API/pull/1802))

- BREAKING CHANGE: Return object for requests to `/wp/v2/taxonomies`.

  This is consistent with `/wp/v2/types` and `/wp/v2/statuses`.

  (props @danielbachhuber, [#1825](https://github.com/WP-API/WP-API/pull/1825))

- BREAKING CHANGE: Remove `rest_get_timezone()`.

  `json_get_timezone()` was only ever used in v1. This function causes fatals, and shouldn't be used.

  (props @danielbachhuber, [#1823](https://github.com/WP-API/WP-API/pull/1823))

- BREAKING CHANGE: Rename `register_api_field()` to `register_rest_field()`.

  Introduces a `register_api_field()` function for backwards compat, which calls `_doing_it_wrong()`. However, `register_api_field()` won't ever be committed to WordPress core, so you should update your function calls.

  (props @danielbachhuber, [#1824](https://github.com/WP-API/WP-API/pull/1824))

- BREAKING CHANGE: Change taxonomies' `post_type` argument to `type`.

  It's consistent with how we're exposing post types in the API.

  (props @danielbachhuber, [#1824](https://github.com/WP-API/WP-API/pull/1824))

- Sync infrastructure with shipped in WordPress 4.4.

  * `wp-includes/rest-api/rest-functions.php` is removed, and its functions moved into `wp-includes/rest-api.php`.
  * Send nocache headers for REST requests. [#34832](https://core.trac.wordpress.org/ticket/34832)
  * Fix handling of HEAD requests. [#34837](https://core.trac.wordpress.org/ticket/34837)
  * Mark `WP_REST_Server::get_raw_data()` as static. [#34768](https://core.trac.wordpress.org/ticket/34768)
  * Unabbreviate error string. [#34818](https://core.trac.wordpress.org/ticket/34818)

- Change terms endpoints to use `term_id` not `tt_id`.

  (props @joehoyle, [#1837](https://github.com/WP-API/WP-API/pull/1837))

- Standardize declaration of `context` param for `GET` requests across controllers.

  However, we're still inconsistent in which controllers expose which params. Follow [#1845](https://github.com/WP-API/WP-API/issues/1845) for further discussion.

  (props @danielbachhuber, [#1795](https://github.com/WP-API/WP-API/pull/1795), [#1835](https://github.com/WP-API/WP-API/pull/1835), [#1838](https://github.com/WP-API/WP-API/pull/1838))

- Link types / taxonomies to their collections, and vice versa.

  Collections link to their type / taxonomy with the `about` relation; types / taxonomies link to their colletion with the `item` relation, which is imperfect and may change in the future.

  (props @danielbachhuber, [#1814](https://github.com/WP-API/WP-API/pull/1814), [#1817](https://github.com/WP-API/WP-API/pull/1817), [#1829](https://github.com/WP-API/WP-API/pull/1829). [#1846](https://github.com/WP-API/WP-API/pull/1846))

- Add missing 'wp/v2' in Location Response header when creating new Post Meta.

  (props @johanmynhardt, [#1790](https://github.com/WP-API/WP-API/pull/1790))

- Expose Post collection query params, including `author`, `order`, `orderby` and `status`.

  (props @danielbachhuber, [#1793](https://github.com/WP-API/WP-API/pull/1793))

- Ignore sticky posts by default.

  (props @danielbachhuber, [#1801](https://github.com/WP-API/WP-API/pull/1801))

- Include `full` image size in attachment `sizes` attribute.

  (props @danielbachhuber, [#1806](https://github.com/WP-API/WP-API/pull/1806))

- In text strings, use `id` instead of `ID`.

  `ID` is an implementation artifact. Our Resources use `id`.

  (props @danielbachhuber, [#1803](https://github.com/WP-API/WP-API/pull/1803))

- Ensure `attachment.sizes[]` use `mime_type` instead of `mime-type`.

  (props @danielbachhuber, [#1809](https://github.com/WP-API/WP-API/pull/1809))

- Introduce `rest_authorization_required_code()`.

  Many controllers returned incorrect HTTP codes, which this also fixes.

  (props @danielbachhuber, [#1808](https://github.com/WP-API/WP-API/pull/1808))

- Respect core's `comment_registration` setting.

  If it's enabled, require users to be logged in to comment.

  (props @danielbachhuber, [#1826](https://github.com/WP-API/WP-API/pull/1826))

- Default to wildcard when searching users.

  (props @danielbachhuber, [#1827](https://github.com/WP-API/WP-API/pull/1827))

- Bring the wp-api.js library up to date for v2 of the REST API.

  (props @adamsilverstein, [#1828](https://github.com/WP-API/WP-API/pull/1828))

- Add `rest_prepare_status` filter.

  (props @danielbachhuber, [#1830](https://github.com/WP-API/WP-API/pull/1830))

- Make `prepare_*` filters more consistent.

  (props @danielbachhuber, [#1831](https://github.com/WP-API/WP-API/pull/1831))

- Add `rest_prepare_post_type` filter for post types.

  (props @danielbachhuber, [#1833](https://github.com/WP-API/WP-API/pull/1833))

## 2.0 Beta 8.0 (December 1, 2015)

- Prevent fatals when uploading attachment by including admin utilities.

  (props @danielbachhuber, [#1756](https://github.com/WP-API/WP-API/pull/1756))

- Return 201 status code when creating a term.

  (props @danielbachhuber, [#1753](https://github.com/WP-API/WP-API/pull/1753))

- Don't permit requesting terms cross routes.

  Clients should only be able to request categories from the category route, and tags from the tag route.

  (props @danielbachhuber, [#1764](https://github.com/WP-API/WP-API/pull/1764))

- Set `fields=>id` when using `WP_User_Query` to fix large memory usage

  (props @joehoyle, [#1770](https://github.com/WP-API/WP-API/pull/1770))

- Fix Post `_link` to attached attachments.

  (props @danielbachhuber, [#1777](https://github.com/WP-API/WP-API/pull/1777))

- Add support for getting a post with a custom public status.

  (props @danielbachhuber, [#1765](https://github.com/WP-API/WP-API/pull/1765))

- Ensure post content doesn't get double-slashed on update.

  (props @joehoyle, [#1772](https://github.com/WP-API/WP-API/pull/1772))

- Change 'int' to 'integer' for `WP_REST_Controller::validate_schema_property()`

  (props @wpsmith, [#1759](https://github.com/WP-API/WP-API/pull/1759))

## 2.0 Beta 7.0 (November 17, 2015)

- Sync infrastructure from WordPress core as of r35691.

  * Remove `register_api_field()` because it's conceptually tied to `WP_REST_Controller` [#34730](https://core.trac.wordpress.org/ticket/34730)
  * Update the REST API header links to use api.w.org [#34303](https://core.trac.wordpress.org/ticket/34303)
  * Require the `$namespace` argument in `register_rest_route()` [#34416](https://core.trac.wordpress.org/ticket/34416)
  * Include `enum` and `description` in help data [#34543](https://core.trac.wordpress.org/ticket/34543)
  * Save `preg_match` iterations in `WP_REST_Server` [#34488](https://core.trac.wordpress.org/ticket/34488)
  * Don't return route URL in `WP_REST_Request:get_params()` [#34647](https://core.trac.wordpress.org/ticket/34647)

- Restore `register_api_field()` within the plugin.

  (props @danielbachhuber, [#1748](https://github.com/WP-API/WP-API/pull/1748))

- Require admin functions for use of `wp_handle_upload()`, fixing fatal.

  (props @joehoyle, [#1746](https://github.com/WP-API/WP-API/pull/1746))

- Properly handle requesting terms where `parent=0` and `0` is a string.

  (props @danielbachhuber, [#1739](https://github.com/WP-API/WP-API/pull/1739))

- Prevent PHP error notice when `&filter` isn't an array.

  (props @danielbachhuber, [#1734](https://github.com/WP-API/WP-API/pull/1734))

- Change link relations to use api.w.org.

  (props @danielbachhuber, [#1726](https://github.com/WP-API/WP-API/pull/1726))


## 2.0 Beta 6.0 (November 12, 2015)

- Remove global inclusion of wp-admin/includes/admin.php

  For a long time, the REST API loaded wp-admin/includes/admin.php to make use of specific admin utilities. Now, it only loads those admin utilities when it needs them.

  If your custom endpoints make use of admin utilities, you'll need to make sure to load wp-admin/includes/admin.php before you use them.

  (props @joehoyle, [#1696](https://github.com/WP-API/WP-API/pull/1696))

- Link directly to the featured image in a Post's links.

  (props @rmccue, [#1563](https://github.com/WP-API/WP-API/pull/1563), [#1711](https://github.com/WP-API/WP-API/pull/1711))

- Provide object type as callback argument for custom API fields.

  (props @jtsternberg, [#1714](https://github.com/WP-API/WP-API/pull/1714))

- Change users schema order to be order of importance instead of alpha.

  (props @rachelbaker, [#1708](https://github.com/WP-API/WP-API/pull/1708))

- Clarify documentation for `date` and `modified` attributes.

  (props @danielbachhuber, [#1715](https://github.com/WP-API/WP-API/pull/1715))

- Update the wp-api.js client from the client-js repo.

  (props @rachelbaker, [#1709](https://github.com/WP-API/WP-API/pull/1709))

- Fix the `format` enum to be an array of strings.

  (props @joehoyle, [#1707](https://github.com/WP-API/WP-API/pull/1707))

- Run revisions for collection through `prepare_response_for_collection()`.

  (props @danielbachhuber, @rachelbaker, [#1671](https://github.com/WP-API/WP-API/pull/1671))

- Expose `date_gmt` for `view` context of Posts and Comments.

  (props @danielbachhuber, [#1690](https://github.com/WP-API/WP-API/pull/1690))

- Fix PHP and JS docblock formatting.

  (props @ahmadawais, [#1699](https://github.com/WP-API/WP-API/pull/1698), [#1699](https://github.com/WP-API/WP-API/pull/1699), [#1701](https://github.com/WP-API/WP-API/pull/1701), [#1700](https://github.com/WP-API/WP-API/pull/1700), [#1702](https://github.com/WP-API/WP-API/pull/1702), [#1703](https://github.com/WP-API/WP-API/pull/1703))

- Include `media_details` attribute for attachments in embed context.

  For image attachments, media_details includes a sizes array of image sizes, which is useful for templating.

  (props @danielbachhuber, [#1667](https://github.com/WP-API/WP-API/pull/1667))

- Make `WP_REST_Controller` error messages more helpful by specifying method to subclass.

  (props @danielbachhuber, [#1670](https://github.com/WP-API/WP-API/pull/1670))

- Expose `slug` in `embed` context for Users.

  `user_nicename` is a public attribute, used in user URLs, so this is safe data to present.

  (props @danielbachhuber, [#1666](https://github.com/WP-API/WP-API/pull/1666))

- Handle falsy value from `wp_count_terms()`, fixing fatal.

  (props @joehoyle, [#1641](https://github.com/WP-API/WP-API/pull/1641))

- Correct methods in `WP_REST_SERVER::EDITABLE` description.

  (props @rachelbaker, [#1601](https://github.com/WP-API/WP-API/pull/1601))

- Add the embed context to Users collection query params.

  (props @rachelbaker, [#1591](https://github.com/WP-API/WP-API/pull/1591))

- Add Terms Controller collection args details.

  (props @rachelbaker, [#1603](https://github.com/WP-API/WP-API/pull/1603))

- Set comment author details from current user.

  (props @rmccue, [#1580](https://github.com/WP-API/WP-API/pull/1580))

- More hook documentation.

  (props @adamsilverstein, [#1556](https://github.com/WP-API/WP-API/pull/1556), [#1560](https://github.com/WP-API/WP-API/pull/1560))

- Return the trashed status of deleted posts/comments.

  When a post or a comment is deleted, returns a flag to say whether it's been trashed or properly deleted.

  (props @pento, [#1499](https://github.com/WP-API/WP-API/pull/1499))

- In `WP_REST_Posts_Controller::update_item()`, check the post ID based on the proper post type.

  (props @rachelbaker, [#1497](https://github.com/WP-API/WP-API/pull/1497))

## 2.0 Beta 5.0 (October 23, 2015)

- Load api-core as a compatibility library

  Now api-core has been merged into WordPress trunk (for 4.4) we should no longer load the infrastructure code
  when it's already available. This also fixes a fatal error for users who were on trunk.

  (props @rmccue)

- Switch to new mysql_to_rfc3339

  (props @rmccue)

- Double-check term taxonomy

  (props @rmccue)

- Load admin functions

  This was removed from the latest beta of WordPress in the REST API infrastructure, a more long term fix is planned.

  (props @joehoyle)

- Add Add compat shim for renamed `rest_mysql_to_rfc3339()`

  (props @danielbachhuber)

- Compat shim for `wp_is_numeric_array()`

  (props @danielbachhuber)

- Revert Switch to register_post_type_args filter

  (props @joehoyle)

## 2.0 Beta 4.0 (August 14, 2015)

- Show public user information through the user controller.

  In WordPress as of [r32683](https://core.trac.wordpress.org/changeset/32683) (scheduled for 4.3), `WP_User_Query` now has support for getting users with published posts.

  To match current behaviour in WordPress themes and feeds, we now expose this public user information. This includes the avatar, description, user ID, custom URL, display name, and URL, for users who have published at least one post on the site. This information is available to all clients; other fields and data for all users are still only available when authenticated.

  (props @joehoyle, @rmccue, @Shelob9, [#1397][gh-1397], [#839][gh-839], [#1435][gh-1435])

- Send schema in OPTIONS requests and index.

  Rather than using separate `/schema` endpoints, the schema for items is now available through an OPTIONS request to the route. This means that full documentation is now available for endpoints through an OPTIONS request; this includes available methods, what data you can pass to the endpoint, and the data you'll get back.

  This data is now also available in the main index and namespace indexes. Simply request the index with `context=help` to get full schema data. Warning: this response will be huge. The schema for single endpoints is also available in the collection's OPTIONS response.

  **⚠️ This breaks backwards compatibility** for clients relying on schemas being at their own routes. These clients should instead send `OPTIONS` requests.

  Custom endpoints can register their own schema via the `schema` option on the route. This option should live side-by-side with the endpoints (similar to `relation` in WP's meta queries), so your registration call will look something like:

  ```php
  register_rest_route( 'test-ns', '/test', array(
    array(
      'methods' => 'GET',
      'callback' => 'my_test_callback',
    ),

    'schema' => 'my_schema_callback',
  ) );
  ```

  (props @rmccue, [#1415][gh-1415], [#1222][gh-1222], [#1305][gh-1305])

- Update JavaScript API for version 2.

  Our fantastic JavaScript API from version 1 is now available for version 2, refreshed with the latest and greatest changes.

  As a refresher: if you want to use it, simply make your script depend on `wp-api` when you enqueue it. If you want to enqueue the script manually, add `wp_enqueue_script( 'wp-api' )` to a callback on `wp_enqueue_scripts`.

  (props @tlovett1, @kadamwhite, @nathanrice, [#1374][gh-1374], [#1320][gh-1320])

- Embed links inside items in a collection.

  Previously when fetching a collection of items, you only received the items themselves. To fetch the links as well via embedding, you needed to make a request to the single item with `_embed` set.

  No longer! You can now request a collection with embeds enabled (try `/wp/v2/posts?_embed`). This will embed links inside each item, allowing you to build interface items much easier (for example, post archive pages can get featured image data at the same time).

  This also applies to custom endpoints. Any endpoint that returns a list of objects will automatically have the embedding applied to objects inside the list.

  (props @rmccue, [#1459][gh-1459], [#865][gh-865])

- Fix potential XSS vulnerability.

  Requests from other origins could potentially run code on the API domain, allowing cross-origin access to authentication cookies or similar.

  Reported by @xknown on 2015-07-23.

- Move `/posts` `WP_Query` vars back to `filter` param.

  In version 1, we had internal `WP_Query` vars available via `filter` (e.g. `filter[s]=search+term`). For our first betas of version 2, we tried something different and exposed these directly on the endpoint. The experiment has now concluded; we didn't like this that much, so `filter` is back.

  We plan on adding nicer looking arguments to collections in future releases, with a view towards being consistent across different collections. We also plan on opening up the underlying query vars via `filter` for users, comments, and terms as well.

  **⚠️ This breaks backwards compatibility** for users using WP Query vars. Simply change your `x=y` parameter to `filter[x]=y`.

  (props @WP-API, [#1420][gh-1420])

- Respect `rest_base` for taxonomies.

  **⚠️ This breaks backwards compatibility** by changing the `/wp/v2/posts/{id}/terms/post_tag` endpoint to `/wp/v2/posts/{id}/tag`.

  (props @joehoyle, [#1466][gh-1466])

- Add permission check for retrieving the posts collection in edit context.

  By extension of the fact that getting any individual post yields a forbidden context error when the `context=edit` and the user is not authorized, the user should also not be permitted to list any post items when unauthorized.

  (props @danielpunkass, [#1412][gh-1412])

- Ensure the REST API URL always has a trailing slash.

  Previously, when pretty permalinks were enabled, the API URL during autodiscovery looked like `/wp-json`, whereas the non-pretty permalink URL looked like `?rest_route=/`. These are now consistent, and always end with a slash character to simplify client URL building.

  (props @danielpunkass, @rmccue, [#1426][gh-1426], [#1442][gh-1442], [#1455][gh-1455], [#1467][gh-1467])

- Use `wp_json_encode` instead of `json_encode`

  Since WordPress 4.1, `wp_json_encode` has been available to ensure encoded values are sane, and that non-UTF8 encodings are supported. We now use this function rather than doing the encode ourselves.

  (props @rmccue, @pento, [#1417][gh-1417])

- Add `role` to schema for users.

  The available roles you can assign to a user are now available in the schema as an `enum`.

  (props @joehoyle, [#1400][gh-1400])

- Use the schema for validation inside the comments controller.

  Previously, the schema was merely a decorative element for documentation inside the comments controller. To bring it inline with our other controllers, the schema is now used internally for validation.

  (props @joehoyle, [#1422][gh-1422])

- Don't set the Location header in update responses.

  Previously, the Location header was sent when updating resources due to some inadvertent copypasta. This header should only be sent when creating to direct clients to the new resource, and isn't required when you're already on the correct resource.

  (props @rachelbaker, [#1441][gh-1441])

- Re-enable the `rest_insert_post` action hook for `WP_REST_Posts_Controller`

  This was disabled during 2.0 development to avoid breaking lots of plugins on the `json_insert_post` action. Now that we've changed namespaces and are Mostly Stable (tm), we can re-enable the action.

  (props @jaredcobb, [#1427][gh-1427], [#1424][gh-1424])

- Fix post taxonomy terms link URLs.

  When moving the routes in a previous beta, we forgot to correct the links on post objects to the new correct route. Sorry!

  (props @rachelbaker, @joehoyle, [#1447][gh-1447], [#1383][gh-1383])

- Use `wp_get_attachment_image_src()` on the image sizes in attachments.

  Since the first versions of the API, we've been building attachment URLs via `str_replace`. Who knows why we were doing this, but it caused problems with custom attachment URLs (such as CDN-hosted images). This now correctly uses the internal functions and filters.

  (props @joehoyle, [#1462][gh-1462])

- Make the embed context a default, not forced.

  If you want embeds to bring in full data rather than with `context=edit`, you can now change the link to specify `context=view` explicitly.

  (props @rmccue, [#1464][gh-1464])

- Ensure we always use the `term_taxonomy_id` and never expose `term_id` publicly.

  Previously, `term_id` was inadvertently exposed in some error responses.

  (props @jdolan, [#1430][gh-1430])

- Fix adding alt text to attachments on creation.

  Previously, this could only be set when updating an attachment, not when creating one.

  (props @joehoyle, [#1398][gh-1398])

- Throw an error when registering routes without a namespace.

  Namespaces should **always** be provided when registering routes. We now throw a `doing_it_wrong` error when attempting to register one. (Previously, this caused a warning, or an invalid internal route.)

  If you *really* need to register namespaceless routes (e.g. to replicate an existing API), call `WP_REST_Server::register_route` directly rather than using the convenience function.

  (props @joehoyle, @rmccue, [#1355][gh-1355])

- Show links on embeds.

  Previously, links were accidentally stripped from embedded response data.

  (props @rmccue, [#1472][gh-1472])

- Clarify insufficient permisssion error when editing posts.

  (props @danielpunkass, [#1411][gh-1411])

- Improve @return inline docs for rest_ensure_response()

  (props @Shelob9, [#1328][gh-1328])

- Check taxonomies exist before trying to set properties.

  (props @joehoyle, @rachelbaker, [#1354][gh-1354])

- Update controllers to ensure we use `sanitize_callback` wherever possible.

  (props @joehoyle, [#1399][gh-1399])

- Add more phpDoc documentation, and correct existing documentation.

  (props @Shelob9, @rmccue, [#1432][gh-1432], [#1433][gh-1433], [#1465][gh-1465])

- Update testing infrastructure.

  Travis now runs our coding standards tests in parallel, and now uses the new, faster container-based testing infrastructure.

  (props @ntwb, @frozzare, [#1449][gh-1449], [#1457][gh-1457])

[View all changes](https://github.com/WP-API/WP-API/compare/2.0-beta3...2.0-beta4)

[gh-839]: https://github.com/WP-API/WP-API/issues/839
[gh-865]: https://github.com/WP-API/WP-API/issues/865
[gh-1222]: https://github.com/WP-API/WP-API/issues/1222
[gh-1305]: https://github.com/WP-API/WP-API/issues/1305
[gh-1310]: https://github.com/WP-API/WP-API/issues/1310
[gh-1320]: https://github.com/WP-API/WP-API/issues/1320
[gh-1328]: https://github.com/WP-API/WP-API/issues/1328
[gh-1354]: https://github.com/WP-API/WP-API/issues/1354
[gh-1355]: https://github.com/WP-API/WP-API/issues/1355
[gh-1372]: https://github.com/WP-API/WP-API/issues/1372
[gh-1374]: https://github.com/WP-API/WP-API/issues/1374
[gh-1383]: https://github.com/WP-API/WP-API/issues/1383
[gh-1397]: https://github.com/WP-API/WP-API/issues/1397
[gh-1398]: https://github.com/WP-API/WP-API/issues/1398
[gh-1399]: https://github.com/WP-API/WP-API/issues/1399
[gh-1400]: https://github.com/WP-API/WP-API/issues/1400
[gh-1402]: https://github.com/WP-API/WP-API/issues/1402
[gh-1411]: https://github.com/WP-API/WP-API/issues/1411
[gh-1412]: https://github.com/WP-API/WP-API/issues/1412
[gh-1413]: https://github.com/WP-API/WP-API/issues/1413
[gh-1415]: https://github.com/WP-API/WP-API/issues/1415
[gh-1417]: https://github.com/WP-API/WP-API/issues/1417
[gh-1420]: https://github.com/WP-API/WP-API/issues/1420
[gh-1422]: https://github.com/WP-API/WP-API/issues/1422
[gh-1424]: https://github.com/WP-API/WP-API/issues/1424
[gh-1426]: https://github.com/WP-API/WP-API/issues/1426
[gh-1427]: https://github.com/WP-API/WP-API/issues/1427
[gh-1430]: https://github.com/WP-API/WP-API/issues/1430
[gh-1432]: https://github.com/WP-API/WP-API/issues/1432
[gh-1433]: https://github.com/WP-API/WP-API/issues/1433
[gh-1435]: https://github.com/WP-API/WP-API/issues/1435
[gh-1441]: https://github.com/WP-API/WP-API/issues/1441
[gh-1442]: https://github.com/WP-API/WP-API/issues/1442
[gh-1447]: https://github.com/WP-API/WP-API/issues/1447
[gh-1449]: https://github.com/WP-API/WP-API/issues/1449
[gh-1455]: https://github.com/WP-API/WP-API/issues/1455
[gh-1455]: https://github.com/WP-API/WP-API/issues/1455
[gh-1457]: https://github.com/WP-API/WP-API/issues/1457
[gh-1459]: https://github.com/WP-API/WP-API/issues/1459
[gh-1462]: https://github.com/WP-API/WP-API/issues/1462
[gh-1464]: https://github.com/WP-API/WP-API/issues/1464
[gh-1465]: https://github.com/WP-API/WP-API/issues/1465
[gh-1466]: https://github.com/WP-API/WP-API/issues/1466
[gh-1467]: https://github.com/WP-API/WP-API/issues/1467
[gh-1472]: https://github.com/WP-API/WP-API/issues/1472

## 2.0 Beta 3.0 (July 1, 2015)

- Add ability to declare sanitization and default options for schema fields.

  The `arg_options` array can be used to declare the sanitization callback,
  default value, or requirement of a field.

  (props @joehoyle, [#1345][gh-1345])
  (props @joehoyle, [#1346][gh-1346])

- Expand supported parameters for creating and updating Comments.

  (props @rachelbaker, [#1245][gh-1245])

- Declare collection parameters for Terms of a Post.

  Define the available collection parameters in `get_collection_params()` and
  allow Terms of a Post to be queried by term order.

  (props @danielbachhuber, [#1332][gh-1332])

- Improve the Attachment error message for an invalid Content-Disposition

  (props @danielbachhuber, [#1317][gh-1317])

- Return 200 status when updating Attachments, Comments, and Users.

  (props @rachelbaker, [#1348][gh-1348])

- Remove unnecessary `handle_format_param()` method.

  (props @danielbachhuber, [#1331][gh-1331])

- Add `author_avatar_url` field to the Comment response and schema.

  (props @rachelbaker [#1327][gh-1327])

- Introduce `rest_do_request()` for making REST requests internally.

  (props @danielbachhuber, [#1333][gh-1333])

- Remove unused DateTime class.

  (props @rmccue, [#1314][gh-1314])

- Add inline documentation for `$wp_rest_server` global.

  (props @Shelob9, [#1324][gh-1324])

  [View all changes](https://github.com/WP-API/WP-API/compare/2.0-beta2...2.0-beta3)
  [gh-1245]: https://github.com/WP-API/WP-API/issues/1245
  [gh-1314]: https://github.com/WP-API/WP-API/issues/1314
  [gh-1317]: https://github.com/WP-API/WP-API/issues/1317
  [gh-1318]: https://github.com/WP-API/WP-API/issues/1318
  [gh-1324]: https://github.com/WP-API/WP-API/issues/1324
  [gh-1326]: https://github.com/WP-API/WP-API/issues/1326
  [gh-1327]: https://github.com/WP-API/WP-API/issues/1327
  [gh-1331]: https://github.com/WP-API/WP-API/issues/1331
  [gh-1332]: https://github.com/WP-API/WP-API/issues/1332
  [gh-1333]: https://github.com/WP-API/WP-API/issues/1333
  [gh-1345]: https://github.com/WP-API/WP-API/issues/1345
  [gh-1346]: https://github.com/WP-API/WP-API/issues/1346
  [gh-1347]: https://github.com/WP-API/WP-API/issues/1347
  [gh-1348]: https://github.com/WP-API/WP-API/issues/1348

## 2.0 Beta 2.0 (May 28, 2015)

- Load the WP REST API before the main query runs.

  The `rest_api_loaded` function now hooks into the `parse_request` action.
  This change prevents the main query from being run on every request and
  allows sites to set `WP_USE_THEMES` to `false`.  Previously, the main query
  was always being run (`SELECT * FROM wp_posts LIMIT 10`), even though the
  result was never used and couldn't be cached.

  (props @rmccue, [#1270][gh-1270])

- Register a new field on an existing WordPress object type.

  Introduces `register_api_field()` to add a field to an object and
  its schema.

  (props @joehoyle, @rachelbaker, [#927][gh-927])
  (props @joehoyle, [#1207][gh-1207])
  (props @joehoyle, [#1243][gh-1243])

- Add endpoints for viewing, creating, updating, and deleting Terms for a Post.

  The new `WP_REST_Posts_Terms_Controller` class controller supports routes for
  Terms that belong to a Post.

  (props @joehoyle, @danielbachhuber, [#1216][gh-1216])

- Add pagination headers for collection queries.

  The `X-WP-Total` and `X-WP-TotalPages` are now present in terms, comments,
  and users collection responses.

  (props @danielbachhuber, [#1182][gh-1182])
  (props @danielbachhuber, [#1191][gh-1191])
  (props @danielbachhuber, @joehoyle, [#1197][gh-1197])

- List registered namespaces in the index for feature detection.

  The index (`/wp-json` by default) now contains a list of the available
  namespaces. This allows for simple feature detection. You can grab the index
  and check namespaces for `wp/v3` or `pluginname/v2`, which indicate the
  supported endpoints on the site.

  (props @rmccue,, [#1283][gh-1283])

- Standardize link property relations and support embedding for all resources.

  Change link properties to use IANA-registered relations.  Also adds embedding
  support to Attachments, Comments and Terms.

  (props @rmccue, @rachelbaker, [#1284][gh-1284])

- Add support for Composer dependency management.

  Allows you to recursively install/update the WP REST API inside of WordPress
  plugins or themes.

  (props @QWp6t, [#1157][gh-1157])

- Return full objects in the delete response.

  Instead of returning a random message when deleting a Post, Comment, Term, or
  User provide the original resource data.

  (props @danielbachhuber, [#1253][gh-1253])
  (props @danielbachhuber, [#1254][gh-1254])
  (props @danielbachhuber, [#1255][gh-1255])
  (props @danielbachhuber, [#1256][gh-1256])

- Return programmatically readable error messages for invalid or missing
  required parameters.

  (props @joehoyle, [#1175][gh-1175])

- Declare supported arguments for Comment and User collection queries.

  (props @danielbachhuber, [#1211][gh-1211])
  (props @danielbachhuber, [#1217][gh-1217])

- Automatically validate parameters based on Schema data.

  (props @joehoyle, [#1128][gh-1128])

- Use the `show_in_rest` attributes for exposing Taxonomies.

  (props @joehoyle, [#1279][gh-1279])

- Handle `parent` when creating or updating a Term.

  (props @joehoyle, [#1221][gh-1221])

- Limit fields returned in `embed` context User responses.

  (props @rachelbaker, [#1251][gh-1251])

- Only include `parent` in term response when tax is hierarchical.

  (props @danielbachhuber, [#1189][gh-1189])

- Fix bug in creating comments if `type` was not set.

  (props @rachelbaker, [#1244][gh-1244])

- Rename `post_name` field to `post_slug`.

  (props @danielbachhuber, [#1235][gh-1235])

- Add check when creating a user to verify the provided role is valid.

  (props @rachelbaker, [#1267][gh-1267])

- Add link properties to the Post Status response.

  (props @joehoyle, [#1243][gh-1243])

- Return `0` for `parent` in Post response instead of `null`.

  (props @danielbachhuber, [#1269][gh-1269])

- Only link `author` when there's a valid author

  (props @danielbachhuber, [#1203][gh-1203])

- Only permit querying by parent term when tax is hierarchical.

  (props @danielbachhuber, [#1219][gh-1219])

- Only permit deleting posts of the proper type

  (props @danielbachhuber, [#1257][gh-1257])

- Set pagination headers even when no found posts.

  (props @danielbachhuber, [#1209][gh-1209])

- Correct prefix in `rest_request_parameter_order` filter.

  (props @quasel, [#1158][gh-1158])

- Retool `WP_REST_Terms_Controller` to follow Posts controller pattern.

  (props @danielbachhuber, [#1170][gh-1170])

- Remove unused `accept_json argument` from the `register_routes` method.

  (props @quasel, [#1160][gh-1160])

- Fix typo in `sanitize_params` inline documentation.

  (props @Shelob9, [#1226][gh-1226])

- Remove commented out code in dispatch method.

  (props @rachelbaker, [#1162][gh-1162])


[View all changes](https://github.com/WP-API/WP-API/compare/2.0-beta1.1...2.0-beta2)
[gh-927]: https://github.com/WP-API/WP-API/issues/927
[gh-1128]: https://github.com/WP-API/WP-API/issues/1128
[gh-1157]: https://github.com/WP-API/WP-API/issues/1157
[gh-1158]: https://github.com/WP-API/WP-API/issues/1158
[gh-1160]: https://github.com/WP-API/WP-API/issues/1160
[gh-1162]: https://github.com/WP-API/WP-API/issues/1162
[gh-1168]: https://github.com/WP-API/WP-API/issues/1168
[gh-1170]: https://github.com/WP-API/WP-API/issues/1170
[gh-1171]: https://github.com/WP-API/WP-API/issues/1171
[gh-1175]: https://github.com/WP-API/WP-API/issues/1175
[gh-1176]: https://github.com/WP-API/WP-API/issues/1176
[gh-1177]: https://github.com/WP-API/WP-API/issues/1177
[gh-1181]: https://github.com/WP-API/WP-API/issues/1181
[gh-1182]: https://github.com/WP-API/WP-API/issues/1182
[gh-1188]: https://github.com/WP-API/WP-API/issues/1188
[gh-1189]: https://github.com/WP-API/WP-API/issues/1189
[gh-1191]: https://github.com/WP-API/WP-API/issues/1191
[gh-1197]: https://github.com/WP-API/WP-API/issues/1197
[gh-1200]: https://github.com/WP-API/WP-API/issues/1200
[gh-1203]: https://github.com/WP-API/WP-API/issues/1203
[gh-1207]: https://github.com/WP-API/WP-API/issues/1207
[gh-1209]: https://github.com/WP-API/WP-API/issues/1209
[gh-1210]: https://github.com/WP-API/WP-API/issues/1210
[gh-1211]: https://github.com/WP-API/WP-API/issues/1211
[gh-1216]: https://github.com/WP-API/WP-API/issues/1216
[gh-1217]: https://github.com/WP-API/WP-API/issues/1217
[gh-1219]: https://github.com/WP-API/WP-API/issues/1219
[gh-1221]: https://github.com/WP-API/WP-API/issues/1221
[gh-1226]: https://github.com/WP-API/WP-API/issues/1226
[gh-1235]: https://github.com/WP-API/WP-API/issues/1235
[gh-1243]: https://github.com/WP-API/WP-API/issues/1243
[gh-1244]: https://github.com/WP-API/WP-API/issues/1244
[gh-1249]: https://github.com/WP-API/WP-API/issues/1249
[gh-1251]: https://github.com/WP-API/WP-API/issues/1251
[gh-1253]: https://github.com/WP-API/WP-API/issues/1253
[gh-1254]: https://github.com/WP-API/WP-API/issues/1254
[gh-1255]: https://github.com/WP-API/WP-API/issues/1255
[gh-1256]: https://github.com/WP-API/WP-API/issues/1256
[gh-1257]: https://github.com/WP-API/WP-API/issues/1257
[gh-1259]: https://github.com/WP-API/WP-API/issues/1259
[gh-1267]: https://github.com/WP-API/WP-API/issues/1267
[gh-1268]: https://github.com/WP-API/WP-API/issues/1268
[gh-1269]: https://github.com/WP-API/WP-API/issues/1269
[gh-1270]: https://github.com/WP-API/WP-API/issues/1270
[gh-1276]: https://github.com/WP-API/WP-API/issues/1276
[gh-1277]: https://github.com/WP-API/WP-API/issues/1277
[gh-1279]: https://github.com/WP-API/WP-API/issues/1279
[gh-1283]: https://github.com/WP-API/WP-API/issues/1283
[gh-1284]: https://github.com/WP-API/WP-API/issues/1284
[gh-1295]: https://github.com/WP-API/WP-API/issues/1295
[gh-1301]: https://github.com/WP-API/WP-API/issues/1301


## 2.0 Beta 1.1

- Fix user access security vulnerability.

  Authenticated users were able to escalate their privileges bypassing the
  expected capabilities check.

  Reported by @kacperszurek on 2015-05-16.


## 2.0 Beta 1 (April 28, 2015)

- Avoid passing server to the controller each time

  (props @rmccue, [#543][gh-543])

- Unify naming of methods across classes

  (props @danielbachhuber, [#546][gh-546])

- Disable unit tests while we move things around

  (props @danielbachhuber, [#548][gh-548])

- Mock code to represent new Resources

  (props @danielbachhuber, [#549][gh-549])

- WP_JSON_Controller POC

  (props @danielbachhuber, [#556][gh-556])

- Add request object

  (props @rmccue, [#563][gh-563])

- Update routes for new-style registration

  (props @rmccue, [#564][gh-564])

- Add compatibility with v1 routing

  (props @rmccue, [#565][gh-565])

- Remove Last-Modified and If-Unmodified-Since

  (props @rmccue, [#566][gh-566])

- Allow multiple route registration

  (props @rmccue, [#586][gh-586])

- Use https in test setup

  (props @danielbachhuber, [#588][gh-588])

- Terms Controller Redux

  (props @danielbachhuber, [#579][gh-579])

- Add hypermedia functionality to the response

  (props @rmccue, @rachelbaker, [#570][gh-570])

- Initial pass at new style Users Controller

  (props @rachelbaker, [#603][gh-603])

- Drop old Users class

  (props @danielbachhuber, [#619][gh-619])

- Fix passing array to 'methods' are in register_json_route()

  (props @joehoyle, [#620][gh-620])

- Allow 'ignore_sticky_posts' filter #415

  (props @Shelob9, [#612][gh-612], [#415][gh-415])

- Initial Extras.php commit

  (props @NikV, [#575][gh-575])

- Allow filtering response before returning

  (props @danielbachhuber, [#573][gh-573])

- Parse JSON data from the request

  (props @rmccue, [#626][gh-626])

- Remove old taxonomies controller

  (props @danielbachhuber, [#637][gh-637])

- Make our code DRY by consolidating use of strtoupper

  (props @danielbachhuber, [#589][gh-589])

- Move WP_Test_JSON_Testcase to a properly named file

  (props @danielbachhuber, [#643][gh-643])

- Speed up builds by only running against MS once

  (props @danielbachhuber, [#638][gh-638])

- `->prepare_post()` should be public

  (props @staylor, [#645][gh-645])

- Get by and return `term_taxonomy_id`

  (props @danielbachhuber, [#648][gh-648])

- Base class with standard test methods for every controller

  (props @danielbachhuber, [#649][gh-649])

- Unused arguments

  (props @staylor, [#647][gh-647])

- JS should be under version control

  (props @staylor, [#644][gh-644])

- Register multiple routes for users correctly

  (props @rmccue, [#654][gh-654])

- Check get_post_type_object() returns an object before using it

  (props @NateWr, [#656][gh-656])

- Run multisite test against PHP 5.2

  (props @danielbachhuber, [#659][gh-659])

- Pass the edit context when returning the create or update response. Fixes
#661

  (props @rachelbaker, [#664][gh-664], [#661][gh-661])

- Check for errors when responding to create

  (props @rmccue, [#652][gh-652])

- Fix bug in check_required_parameters where JSON params were missed

  (props @rachelbaker, [#673][gh-673])

- Fix parameter handling and improve Users Controller tests

  (props @rachelbaker, [#675][gh-675])

- Check that param is null

  (props @danielbachhuber, [#678][gh-678])

- Parse URL-encoded body with PUT requests

  (props @rmccue, [#681][gh-681])

- End to end testing for users

  (props @rmccue, [#682][gh-682])

- End to end test coverage of Terms Controller

  (props @danielbachhuber, @rmccue, [#676][gh-676])

- Add ability to wrap response in an envelope

  (props @Japh, @rmccue, [#628][gh-628])

- Wrap up PUT handling in Users Controller

  (props @rachelbaker, [#683][gh-683])

- ID shouldn't be a param on update user endpoint

  (props @joehoyle, [#692][gh-692])

- Clean up Terms controller

  (props @danielbachhuber, [#696][gh-696])

- Remove mis-placed duplicate Users Delete route and id parameter

  (props @rachelbaker, [#700][gh-700])

- Fields cleanup for User controller

  (props @danielbachhuber, [#701][gh-701])

- Throw an error when a user tries to update to an existing user's email

  (props @danielbachhuber, [#705][gh-705])

- `PUT User` shouldn't permit using existing `user_login` or `user_nicename`

  (props @danielbachhuber, [#707][gh-707])

- Change return value of WP_JSON_Users_Controller::get_item.

  (props @rachelbaker, [#712][gh-712])

- Add the ability to specify default param values in register_json_route

  (props @WP-API, [#715][gh-715])

- Merge JS into main repo

  (props @tlovett1, [#730][gh-730])

- Make the "required" param on args optional

  (props @joehoyle, @rachelbaker, [#728][gh-728])

- Always allow JSON data for POST and PUT requests

  (props @rachelbaker, [#731][gh-731])

- Initial pass at new style Posts Controller

  (props @rachelbaker, [#684][gh-684])

- Drop required argument declaration

  (props @danielbachhuber, [#736][gh-736])

- Update post format after post has been updated

  (props @danielbachhuber, [#737][gh-737])

- Allow the title to be set via title.raw

  (props @iseulde, [#741][gh-741])

- Fix some incompatible interfaces

  (props @staylor, [#742][gh-742])

- Full Test Coverage for Users Controller

  (props @rachelbaker, [#744][gh-744])

- Refer to BaseCollection statically instead of via this.constructor

  (props @tlovett1, [#750][gh-750])

- Adjustments to Users Controller DocBlocks

  (props @rachelbaker, [#743][gh-743])

- Default `args` to an empty array

  (props @danielbachhuber, [#758][gh-758])

- Do not require type parameter to be set when updating a Post

  (props @rachelbaker, [#761][gh-761])

- Remove from docs the "post_type" filter parameter for /posts endpoint

  (props @NateWr, [#666][gh-666])

- Resolve regressions in Posts Controller

  (props @rachelbaker, [#753][gh-753])

- WP_Json_Server::dispatch() should always return a WP_JSON_Response

  (props @joehoyle, [#714][gh-714])

- Update Timeline note

  (props @tapsboy, [#774][gh-774])

- Make json_pre_dispatch and json_post_dispatch consistent

  (props @joehoyle, [#786][gh-786])

- Normalize our test classes setUP and tearDown methods

  (props @rachelbaker, [#794][gh-794])

- Comments Endpoints

  (props @joehoyle, @rachelbaker, [#693][gh-693])

- Correct /posts/ endpoint read post permission logic

  (props @rachelbaker, [#805][gh-805])

- Ensure global $post has proper state when the json_prepare_post filter f...

  (props @ericandrewlewis, [#823][gh-823])

- Adds missing description field to the Taxonomy response

  (props @rachelbaker, [#826][gh-826])

- Posts controller abstraction

  (props @danielbachhuber, [#820][gh-820])

- Remove old Pages and CustomPostType classes no longer in use

  (props @danielbachhuber, [#831][gh-831])

- Add `featured_image` attribute for post types that support `thumbnails`

  (props @danielbachhuber, [#832][gh-832])

- Specify Capability in Route

  (props @joehoyle, [#602][gh-602])

- Posts Controller Headers and Links Fixes

  (props @rachelbaker, [#836][gh-836])

- Don't noop `future` status. It's confusing

  (props @danielbachhuber, [#841][gh-841])

- Remove unused $request parameter from prepare_links method.

  (props @rachelbaker, [#842][gh-842])

- Expose basic author details when user has published posts

  (props @danielbachhuber, [#838][gh-838])

- Make `get_post_type_base()` public so we can DRY

  (props @danielbachhuber, [#845][gh-845])

- Remove Duplicate Logic for Post Type Attributes

  (props @rachelbaker, [#853][gh-853])

- Move infrastructure classes to `lib/infrastructure`, part one

  (props @danielbachhuber, [#872][gh-872])

- Passing a value for the slug parameter should update the post_name.

  (props @rachelbaker, [#883][gh-883])

- Break Pages tests into a separate class

  (props @danielbachhuber, [#870][gh-870])

- Empty checks in Posts Controller make setting values to Falsy impossible

  (props @joehoyle, [#885][gh-885])

- Change project name to WP REST API in plugin name and Readme title.

  (props @rachelbaker, [#876][gh-876])

- Return 200 and an empty array for valid queries with 0 results.

  (props @rachelbaker, [#888][gh-888])

- Include the taxonomy in the term response

  (props @danielbachhuber, [#891][gh-891])

- JSON Schemas for our Controllers, second attempt

  (props @danielbachhuber, [#844][gh-844])

- From the left with love

  (props @MichaelArestad, [#896][gh-896])

- Add `link` field to Users, Comments and Terms

  (props @danielbachhuber, [#897][gh-897])

- Fix flipped assertions

  (props @danielbachhuber, [#902][gh-902])

- Add missing break statement

  (props @danielbachhuber, [#905][gh-905])

- Move all of our endpoint controllers to `lib/endpoints`

  (props @danielbachhuber, [#906][gh-906])

- Always include `guid` in Post and Page schemas

  (props @danielbachhuber, [#907][gh-907])

- If post type doesn't match controller post type, throw 404

  (props @danielbachhuber, [#908][gh-908])

- Allow post type attributes to be set based on presence in schema

  (props @danielbachhuber, [#910][gh-910])

- Updating another post field shouldn't change sticky status

  (props @danielbachhuber, [#911][gh-911])

- Expose post type data at `/types`

  (props @danielbachhuber, [#914][gh-914])

- Always defer to controller for post type

  (props @danielbachhuber, [#913][gh-913])

- Add `template` parameter to Page response

  (props @danielbachhuber, [#909][gh-909])

- Convert /media to new controller pattern

  (props @danielbachhuber, [#904][gh-904])

- Remove v1.0 Posts (and Media) controller

  (props @WP-API, [#923][gh-923])

- Clean up taxonomies controller tests by running through dispatch; add schema

  (props @danielbachhuber, [#919][gh-919])

- Separate permissions logic for comments

  (props @joehoyle, [#854][gh-854])

- `wp-json.php` isn't needed anymore

  (props @danielbachhuber, [#931][gh-931])

- Tweak the post controller

  (props @rmccue, [#936][gh-936])

- Switch CORS headers callback to new action

  (props @rmccue, [#935][gh-935])

- Remove `_id` suffix from field names

  (props @danielbachhuber, [#941][gh-941])

- Add `author_ip`, `author_user_agent` and `karma` fields to Comment

  (props @danielbachhuber, [#946][gh-946])

- Explicitly test that these additional comment fields aren't present

  (props @danielbachhuber, [#947][gh-947])

- Allow `title` to be set to empty string in request

  (props @danielbachhuber, [#953][gh-953])

- Use real URLs instead of query_params attribute

  (props @rmccue, [#958][gh-958])

- Use `wp_filter_post_kses()` instead of `wp_kses_post()` on insert

  (props @danielbachhuber, [#917][gh-917])

- Add missing core path to post endpoint link hrefs.

  (props @rachelbaker, [#966][gh-966])

- Allow HTTP method to be overwritten by HTTP_X_HTTP_METHOD_OVERRIDE

  (props @tlovett1, [#967][gh-967])

- Fix attachment caption and description fields

  (props @danielbachhuber, [#968][gh-968])

- Move validation to the `WP_JSON_Request` class

  (props @danielbachhuber, [#971][gh-971])

- Move the Route Registering to the Controllers

  (props @joehoyle, [#970][gh-970])

- Correct test method spelling of permission.

  (props @rachelbaker, [#973][gh-973])

- Permission abstractions 2

  (props @joehoyle, [#987][gh-987])

- If an invalid date is supplied to create / update post, return an error

  (props @joehoyle, [#1000][gh-1000])

- Update README.md

  (props @hubdotcom, [#1006][gh-1006])

- Add embeddable attachments to Post response _links

  (props @rachelbaker, [#1026][gh-1026])

- Throw error if requesting user doesn't have capability for context

  (props @danielbachhuber, [#1033][gh-1033])

- `/wp/statuses` endpoint, modeled after `/wp/types`

  (props @danielbachhuber, [#1039][gh-1039])

- Turn post types from array to object, with name as key

  (props @danielbachhuber, [#1042][gh-1042])

- Add missing response fields to the user schema.

  (props @rachelbaker, [#1034][gh-1034])

- Setting a post to be sticky AND password protected should fail

  (props @joehoyle, [#1044][gh-1044])

- Use appropriate functions when creating users on multisite

  (props @danielbachhuber, [#1043][gh-1043])

- Define context in which each schema field appears

  (props @danielbachhuber, [#1046][gh-1046])

- Use schema abstraction to limit which user fields are exposed per context

  (props @danielbachhuber, [#1049][gh-1049])

- Run Statuses, Types, and Taxonomies through our context filter

  (props @danielbachhuber, [#1050][gh-1050])

- Run Terms controller through schema context filter

  (props @danielbachhuber, [#1051][gh-1051])

- Don't allow contributors to set sticky on posts

  (props @joehoyle, [#1052][gh-1052])

- Return correct response code from wp_insert_post() error

  (props @joehoyle, [#999][gh-999])

- Move the permissions checks for password and author into the permissions
callback

  (props @joehoyle, [#1054][gh-1054])

- Use full Post schema to filter fields based on context

  (props @danielbachhuber, [#1053][gh-1053])

- Allow WP_JSON_Server::send_header()/send_headers() to be accessed publicly

  (props @johnbillion, [#1059][gh-1059])

- Remove unnecessary sticky posts abstraction

  (props @danielbachhuber, [#1064][gh-1064])

- Re-enable the Post endpoint filters

  (props @rachelbaker, [#1028][gh-1028])

- Fix the format of the args when building them from the Schema

  (props @joehoyle, [#1066][gh-1066])

- Add more tests for the server class

  (props @rmccue, [#685][gh-685])

- Fix error with OPTIONS requests

  (props @rmccue, [#1091][gh-1091])

- Ensure the JSON endpoint URL is properly escaped

  (props @johnbillion, [#1097][gh-1097])

- Correct a bunch of filter docs in WP_JSON_Server

  (props @johnbillion, [#1098][gh-1098])

- Require `moderate_comments` capability to context=edit a Comment

  (props @danielbachhuber, @joehoyle, [#951][gh-951])

- Add all the permission check functions to the base controller for better
consistancy and help to subclasses

  (props @joehoyle, [#1104][gh-1104])

- `author` is the Comment attribute with user ID

  (props @danielbachhuber, [#1106][gh-1106])

- Fix copy pasta in the schema checks

  (props @danielbachhuber, [#1111][gh-1111])

- When `context=edit`, confirm user can `manage_comments`

  (props @danielbachhuber, [#1112][gh-1112])

- Abstract revisions to dedicated controller; only include revisioned fields

  (props @danielbachhuber, [#1110][gh-1110])

- Add Embeddable Taxonomy Term Links to the Post Response

  (props @rachelbaker, [#1048][gh-1048])

- Increase Terms Controller test coverage

  (props @rachelbaker, [#1117][gh-1117])

- Rename the `wp_json_server_before_serve` to `wp_json_init`

  (props @joehoyle, [#1105][gh-1105])

- Drop revision embedding from posts controller; link instead

  (props @danielbachhuber, [#1121][gh-1121])

- Add security section to our README

  (props @rmccue, [#1123][gh-1123])

- Missing @param inline docs in main plugin file.

  (props @Shelob9, [#1122][gh-1122])

- Ensure post deletion is idempotent

  (props @rmccue, [#959][gh-959])

- Support for validation / sanitize callbacks in arguments

  (props @joehoyle, [#989][gh-989])

- Display links in collections

  (props @rmccue, @rachelbaker, [#937][gh-937])

- Sanitize args using new args API

  (props @joehoyle, [#1129][gh-1129])

- Use the user fields from the item schema as the request args in route
registration

  (props @joehoyle, [#1109][gh-1109])

- Build the array of args for /wp/posts from the allowed query vars

  (props @joehoyle, [#1108][gh-1108])

- Show all the invalid param errors at once

  (props @joehoyle, [#1131][gh-1131])

- Readonly attribute in schema to exclude from args array

  (props @joehoyle, [#1133][gh-1133])

- Use the `required` flags from the schema for CREATE post

  (props @joehoyle, [#1132][gh-1132])

- Only return 201 on Create. Update should be 200

  (props @danielbachhuber, [#1142][gh-1142])

- Convert meta endpoints to new-style

  (props @rmccue, @rachelbaker, [#960][gh-960])

- Specific error codes for permissions failures

  (props @joehoyle, [#1148][gh-1148])

[View all changes](https://github.com/WP-API/WP-API/compare/1.2.1...2.0-beta1)
[gh-347]: https://github.com/WP-API/WP-API/issues/347
[gh-378]: https://github.com/WP-API/WP-API/issues/378
[gh-401]: https://github.com/WP-API/WP-API/issues/401
[gh-415]: https://github.com/WP-API/WP-API/issues/415
[gh-448]: https://github.com/WP-API/WP-API/issues/448
[gh-474]: https://github.com/WP-API/WP-API/issues/474
[gh-481]: https://github.com/WP-API/WP-API/issues/481
[gh-524]: https://github.com/WP-API/WP-API/issues/524
[gh-528]: https://github.com/WP-API/WP-API/issues/528
[gh-543]: https://github.com/WP-API/WP-API/issues/543
[gh-546]: https://github.com/WP-API/WP-API/issues/546
[gh-548]: https://github.com/WP-API/WP-API/issues/548
[gh-549]: https://github.com/WP-API/WP-API/issues/549
[gh-550]: https://github.com/WP-API/WP-API/issues/550
[gh-556]: https://github.com/WP-API/WP-API/issues/556
[gh-563]: https://github.com/WP-API/WP-API/issues/563
[gh-564]: https://github.com/WP-API/WP-API/issues/564
[gh-565]: https://github.com/WP-API/WP-API/issues/565
[gh-566]: https://github.com/WP-API/WP-API/issues/566
[gh-567]: https://github.com/WP-API/WP-API/issues/567
[gh-570]: https://github.com/WP-API/WP-API/issues/570
[gh-573]: https://github.com/WP-API/WP-API/issues/573
[gh-575]: https://github.com/WP-API/WP-API/issues/575
[gh-579]: https://github.com/WP-API/WP-API/issues/579
[gh-586]: https://github.com/WP-API/WP-API/issues/586
[gh-588]: https://github.com/WP-API/WP-API/issues/588
[gh-589]: https://github.com/WP-API/WP-API/issues/589
[gh-591]: https://github.com/WP-API/WP-API/issues/591
[gh-595]: https://github.com/WP-API/WP-API/issues/595
[gh-602]: https://github.com/WP-API/WP-API/issues/602
[gh-603]: https://github.com/WP-API/WP-API/issues/603
[gh-612]: https://github.com/WP-API/WP-API/issues/612
[gh-619]: https://github.com/WP-API/WP-API/issues/619
[gh-620]: https://github.com/WP-API/WP-API/issues/620
[gh-626]: https://github.com/WP-API/WP-API/issues/626
[gh-628]: https://github.com/WP-API/WP-API/issues/628
[gh-630]: https://github.com/WP-API/WP-API/issues/630
[gh-637]: https://github.com/WP-API/WP-API/issues/637
[gh-638]: https://github.com/WP-API/WP-API/issues/638
[gh-643]: https://github.com/WP-API/WP-API/issues/643
[gh-644]: https://github.com/WP-API/WP-API/issues/644
[gh-645]: https://github.com/WP-API/WP-API/issues/645
[gh-647]: https://github.com/WP-API/WP-API/issues/647
[gh-648]: https://github.com/WP-API/WP-API/issues/648
[gh-649]: https://github.com/WP-API/WP-API/issues/649
[gh-652]: https://github.com/WP-API/WP-API/issues/652
[gh-654]: https://github.com/WP-API/WP-API/issues/654
[gh-656]: https://github.com/WP-API/WP-API/issues/656
[gh-659]: https://github.com/WP-API/WP-API/issues/659
[gh-661]: https://github.com/WP-API/WP-API/issues/661
[gh-664]: https://github.com/WP-API/WP-API/issues/664
[gh-666]: https://github.com/WP-API/WP-API/issues/666
[gh-673]: https://github.com/WP-API/WP-API/issues/673
[gh-675]: https://github.com/WP-API/WP-API/issues/675
[gh-676]: https://github.com/WP-API/WP-API/issues/676
[gh-678]: https://github.com/WP-API/WP-API/issues/678
[gh-681]: https://github.com/WP-API/WP-API/issues/681
[gh-682]: https://github.com/WP-API/WP-API/issues/682
[gh-683]: https://github.com/WP-API/WP-API/issues/683
[gh-684]: https://github.com/WP-API/WP-API/issues/684
[gh-685]: https://github.com/WP-API/WP-API/issues/685
[gh-692]: https://github.com/WP-API/WP-API/issues/692
[gh-693]: https://github.com/WP-API/WP-API/issues/693
[gh-696]: https://github.com/WP-API/WP-API/issues/696
[gh-700]: https://github.com/WP-API/WP-API/issues/700
[gh-701]: https://github.com/WP-API/WP-API/issues/701
[gh-705]: https://github.com/WP-API/WP-API/issues/705
[gh-707]: https://github.com/WP-API/WP-API/issues/707
[gh-712]: https://github.com/WP-API/WP-API/issues/712
[gh-714]: https://github.com/WP-API/WP-API/issues/714
[gh-715]: https://github.com/WP-API/WP-API/issues/715
[gh-722]: https://github.com/WP-API/WP-API/issues/722
[gh-728]: https://github.com/WP-API/WP-API/issues/728
[gh-730]: https://github.com/WP-API/WP-API/issues/730
[gh-731]: https://github.com/WP-API/WP-API/issues/731
[gh-736]: https://github.com/WP-API/WP-API/issues/736
[gh-737]: https://github.com/WP-API/WP-API/issues/737
[gh-741]: https://github.com/WP-API/WP-API/issues/741
[gh-742]: https://github.com/WP-API/WP-API/issues/742
[gh-743]: https://github.com/WP-API/WP-API/issues/743
[gh-744]: https://github.com/WP-API/WP-API/issues/744
[gh-750]: https://github.com/WP-API/WP-API/issues/750
[gh-753]: https://github.com/WP-API/WP-API/issues/753
[gh-758]: https://github.com/WP-API/WP-API/issues/758
[gh-761]: https://github.com/WP-API/WP-API/issues/761
[gh-774]: https://github.com/WP-API/WP-API/issues/774
[gh-786]: https://github.com/WP-API/WP-API/issues/786
[gh-794]: https://github.com/WP-API/WP-API/issues/794
[gh-805]: https://github.com/WP-API/WP-API/issues/805
[gh-807]: https://github.com/WP-API/WP-API/issues/807
[gh-815]: https://github.com/WP-API/WP-API/issues/815
[gh-820]: https://github.com/WP-API/WP-API/issues/820
[gh-823]: https://github.com/WP-API/WP-API/issues/823
[gh-826]: https://github.com/WP-API/WP-API/issues/826
[gh-831]: https://github.com/WP-API/WP-API/issues/831
[gh-832]: https://github.com/WP-API/WP-API/issues/832
[gh-836]: https://github.com/WP-API/WP-API/issues/836
[gh-838]: https://github.com/WP-API/WP-API/issues/838
[gh-841]: https://github.com/WP-API/WP-API/issues/841
[gh-842]: https://github.com/WP-API/WP-API/issues/842
[gh-844]: https://github.com/WP-API/WP-API/issues/844
[gh-845]: https://github.com/WP-API/WP-API/issues/845
[gh-849]: https://github.com/WP-API/WP-API/issues/849
[gh-853]: https://github.com/WP-API/WP-API/issues/853
[gh-854]: https://github.com/WP-API/WP-API/issues/854
[gh-870]: https://github.com/WP-API/WP-API/issues/870
[gh-872]: https://github.com/WP-API/WP-API/issues/872
[gh-874]: https://github.com/WP-API/WP-API/issues/874
[gh-876]: https://github.com/WP-API/WP-API/issues/876
[gh-879]: https://github.com/WP-API/WP-API/issues/879
[gh-883]: https://github.com/WP-API/WP-API/issues/883
[gh-885]: https://github.com/WP-API/WP-API/issues/885
[gh-888]: https://github.com/WP-API/WP-API/issues/888
[gh-891]: https://github.com/WP-API/WP-API/issues/891
[gh-896]: https://github.com/WP-API/WP-API/issues/896
[gh-897]: https://github.com/WP-API/WP-API/issues/897
[gh-902]: https://github.com/WP-API/WP-API/issues/902
[gh-904]: https://github.com/WP-API/WP-API/issues/904
[gh-905]: https://github.com/WP-API/WP-API/issues/905
[gh-906]: https://github.com/WP-API/WP-API/issues/906
[gh-907]: https://github.com/WP-API/WP-API/issues/907
[gh-908]: https://github.com/WP-API/WP-API/issues/908
[gh-909]: https://github.com/WP-API/WP-API/issues/909
[gh-910]: https://github.com/WP-API/WP-API/issues/910
[gh-911]: https://github.com/WP-API/WP-API/issues/911
[gh-913]: https://github.com/WP-API/WP-API/issues/913
[gh-914]: https://github.com/WP-API/WP-API/issues/914
[gh-917]: https://github.com/WP-API/WP-API/issues/917
[gh-919]: https://github.com/WP-API/WP-API/issues/919
[gh-923]: https://github.com/WP-API/WP-API/issues/923
[gh-931]: https://github.com/WP-API/WP-API/issues/931
[gh-933]: https://github.com/WP-API/WP-API/issues/933
[gh-935]: https://github.com/WP-API/WP-API/issues/935
[gh-936]: https://github.com/WP-API/WP-API/issues/936
[gh-937]: https://github.com/WP-API/WP-API/issues/937
[gh-941]: https://github.com/WP-API/WP-API/issues/941
[gh-946]: https://github.com/WP-API/WP-API/issues/946
[gh-947]: https://github.com/WP-API/WP-API/issues/947
[gh-951]: https://github.com/WP-API/WP-API/issues/951
[gh-953]: https://github.com/WP-API/WP-API/issues/953
[gh-955]: https://github.com/WP-API/WP-API/issues/955
[gh-958]: https://github.com/WP-API/WP-API/issues/958
[gh-959]: https://github.com/WP-API/WP-API/issues/959
[gh-960]: https://github.com/WP-API/WP-API/issues/960
[gh-966]: https://github.com/WP-API/WP-API/issues/966
[gh-967]: https://github.com/WP-API/WP-API/issues/967
[gh-968]: https://github.com/WP-API/WP-API/issues/968
[gh-970]: https://github.com/WP-API/WP-API/issues/970
[gh-971]: https://github.com/WP-API/WP-API/issues/971
[gh-973]: https://github.com/WP-API/WP-API/issues/973
[gh-985]: https://github.com/WP-API/WP-API/issues/985
[gh-987]: https://github.com/WP-API/WP-API/issues/987
[gh-989]: https://github.com/WP-API/WP-API/issues/989
[gh-996]: https://github.com/WP-API/WP-API/issues/996
[gh-999]: https://github.com/WP-API/WP-API/issues/999
[gh-1000]: https://github.com/WP-API/WP-API/issues/1000
[gh-1006]: https://github.com/WP-API/WP-API/issues/1006
[gh-1026]: https://github.com/WP-API/WP-API/issues/1026
[gh-1028]: https://github.com/WP-API/WP-API/issues/1028
[gh-1033]: https://github.com/WP-API/WP-API/issues/1033
[gh-1034]: https://github.com/WP-API/WP-API/issues/1034
[gh-1039]: https://github.com/WP-API/WP-API/issues/1039
[gh-1042]: https://github.com/WP-API/WP-API/issues/1042
[gh-1043]: https://github.com/WP-API/WP-API/issues/1043
[gh-1044]: https://github.com/WP-API/WP-API/issues/1044
[gh-1046]: https://github.com/WP-API/WP-API/issues/1046
[gh-1048]: https://github.com/WP-API/WP-API/issues/1048
[gh-1049]: https://github.com/WP-API/WP-API/issues/1049
[gh-1050]: https://github.com/WP-API/WP-API/issues/1050
[gh-1051]: https://github.com/WP-API/WP-API/issues/1051
[gh-1052]: https://github.com/WP-API/WP-API/issues/1052
[gh-1053]: https://github.com/WP-API/WP-API/issues/1053
[gh-1054]: https://github.com/WP-API/WP-API/issues/1054
[gh-1059]: https://github.com/WP-API/WP-API/issues/1059
[gh-1064]: https://github.com/WP-API/WP-API/issues/1064
[gh-1066]: https://github.com/WP-API/WP-API/issues/1066
[gh-1091]: https://github.com/WP-API/WP-API/issues/1091
[gh-1097]: https://github.com/WP-API/WP-API/issues/1097
[gh-1098]: https://github.com/WP-API/WP-API/issues/1098
[gh-1103]: https://github.com/WP-API/WP-API/issues/1103
[gh-1104]: https://github.com/WP-API/WP-API/issues/1104
[gh-1105]: https://github.com/WP-API/WP-API/issues/1105
[gh-1106]: https://github.com/WP-API/WP-API/issues/1106
[gh-1108]: https://github.com/WP-API/WP-API/issues/1108
[gh-1109]: https://github.com/WP-API/WP-API/issues/1109
[gh-1110]: https://github.com/WP-API/WP-API/issues/1110
[gh-1111]: https://github.com/WP-API/WP-API/issues/1111
[gh-1112]: https://github.com/WP-API/WP-API/issues/1112
[gh-1115]: https://github.com/WP-API/WP-API/issues/1115
[gh-1116]: https://github.com/WP-API/WP-API/issues/1116
[gh-1117]: https://github.com/WP-API/WP-API/issues/1117
[gh-1121]: https://github.com/WP-API/WP-API/issues/1121
[gh-1122]: https://github.com/WP-API/WP-API/issues/1122
[gh-1123]: https://github.com/WP-API/WP-API/issues/1123
[gh-1129]: https://github.com/WP-API/WP-API/issues/1129
[gh-1131]: https://github.com/WP-API/WP-API/issues/1131
[gh-1132]: https://github.com/WP-API/WP-API/issues/1132
[gh-1133]: https://github.com/WP-API/WP-API/issues/1133
[gh-1134]: https://github.com/WP-API/WP-API/issues/1134
[gh-1137]: https://github.com/WP-API/WP-API/issues/1137
[gh-1142]: https://github.com/WP-API/WP-API/issues/1142
[gh-1148]: https://github.com/WP-API/WP-API/issues/1148

## 1.2.1

- Fix information disclosure security vulnerability.

  Unauthenticated users could access revisions of published and unpublished posts. Revisions are now only accessible to authenticated users with permission to edit the revision's post.

  Reported by @chredd on 2015-04-09.

## 1.2.0

- Add handling for Cross-Origin Resource Sharing (CORS) OPTIONS requests.

  Preflighted requests (using the OPTIONS method) include the headers
  `Access-Control-Allow-Origin`, `Access-Control-Allow-Methods`, and
  `Access-Control-Allow-Credentials` in the response, if the HTTP origin is
  set.

  (props @rmccue, [#281][gh-281])

- Allow overriding full requests.

  The `json_pre_dispatch` filter allows a request to be hijacked before it is
  dispatched. Hijacked requests can be anything a normal endpoint can return.

  (props @rmccue, [#281][gh-281])

- Check for JSON encoding/decoding errors.

  Returns the last error (if any) occurred during the last JSON encoding or
  decoding operation.

  (props @joshkadis, @rmccue, [#461][gh-461])

- Add filtering to the terms collection endpoint.

  Available filter arguments are based on the `get_terms()` function. Example:
  `/taxonomies/category/terms?filter[number]=10` would limit the response to 10
  category terms.

	(props @mauteri, [#401][gh-401], [#347][gh-347])

- Add handling for the `role` parameter when creating or updating a user.

  Allow users to be created or updated with a provided `role`.

  (props @pippinsplugins, [#392][gh-392], [#335][gh-335])

- Add handling for the `post_id` parameter when creating media.

  Allow passing the `post_id` parameter to associate a new media item with
  a post.

  (props @pkevan, [#294][gh-294])

- Handle route matching for `-` in taxonomy and terms.

  Previously the regular expression used to match taxonomy and term names did
  not support names with dashes.

  (props @EdHurtig, @evansobkowicz, [#410][gh-410])

- Handle JSONP callback matching for `.` in the function name.

  Previously the regular expression used to match JSONP callback functions did
  not support names with periods.

  (props @codonnell822, [#455][gh-455])

- Fix the Content-Type header for JSONP requests.

  Previously JSONP requests sent the incorrect `application/json` Content-Type
  header with the response.  This would result in an error if strict MIME
  checking was enabled. The Content-Type header was corrected to
  `application/javascript` for JSONP responses.

  (props @simonlampen, [#380][gh-380])

- Add `$context` parameter to `json_prepare_term` filter.

  Terms responses can now be modified based on the `context` parameter of the
  request.

  (props @traversal, [#316][gh-316])

- Move the JavaScript client library into the plugin.

  Previously, the `wp-api.js` file was a separate repository. The JavaScript
  client has moved back into the plugin to coordinate code changes.

  (props @tlovett1, [#730][gh-730])

- Always return an object for media sizes

  The media sizes value should always be an object even when empty. Previously,
  if a media item did not have any sizes set, an empty array was returned.

  **Compatibility warning**: Clients should be prepared to accept an empty
  object as a value for media sizes.

  (props @maxcutler, [#300][gh-300])

- Give top-level posts a `null` parent value.

  For date type consistency, post parent property should be `null`. Previously,
  parent-less posts returned `0` for parent.

  **Compatibility warning**: Clients should be prepared to accept `null` as a
  value for post parent.

  (props @maxcutler, [#391][gh-391])

- Move permission checks out of `WP_JSON_Posts`.

  Introduce `json_check_post_permission()` function to allow post object
  capability checks to be used outside the `WP_JSON_Posts` class.

  **Deprecation warning:** Calling `WP_JSON_Posts::check_read_permission` and
  `WP_JSON_Posts::check_edit_permission` is now deprecated.

  (props @rachelbaker, [#486][gh-486], [#378][gh-378])

- Split comment endpoints into separate class.

  All comment handling has moved to the `WP_JSON_Comments` class.

  **Deprecation warning:** Calling `WP_JSON_Posts::get_comments`,
  `WP_JSON_Posts::get_comment`, `WP_JSON_Posts::delete_comment`, and
  `WP_JSON_Posts::prepare_comment` is now deprecated.

  (props @whyisjake, @rmccue, @rachelbaker, [#378][gh-378])

- Split meta endpoints into separate class.

  All post meta handling has moved to the new `WP_JSON_Meta_Posts` class.

  **Deprecation warning:** Calling `WP_JSON_Posts::get_all_meta`,
  `WP_JSON_Posts::get_meta`, `WP_JSON_Posts::update_meta`,
  `WP_JSON_Posts::add_meta`, `WP_JSON_Posts::delete_meta`,
  `WP_JSON_Posts::prepare_meta`, and `WP_JSON_Posts::is_valid_meta_data` is
  now deprecated.

  (props @rmccue, @rachelbaker, [#358][gh-358], [#474][gh-474])

- Rename internal create methods.

  **Deprecation warning:** Calling `WP_JSON_Posts::new_post`,
  `WP_JSON_CustomPostType::new_post` and `WP_JSON_Posts::new_post`
  is now deprecated.

  (props @rachelbaker, @rmccue, [#374][gh-374], [#377][gh-377], [#376][gh-376])

- Fix discrepancies in edit and create posts documentation examples.

  Corrected the edit and create posts code examples in the Getting Started
  section.  The new post example was updated to include the required
  `content_raw` parameter. The new and edit posts examples were updated to use
  a correct date parameter.

  (props @rachelbaker, [#305][gh-305])

- Update the cookie authentication documentation examples.

  With 1.1 the localized JavaScript object for `wp-api.js` changed to
  `WP_API_Settings`. This updates the Authentication section documentation
  nonce example to use the updated object name.

  (props @rachelbaker, [#321][gh-321])

- Add flexibility and multisite support to unit tests.

  Tests can be run from any WordPress install, and are not limited to only as
  a plugin installed within a WordPress.org develop checkout. Unit tests are
  now run against a multisite installation.

  (props @danielbachhuber, [#397][gh-397])

- Add `taxonomy` slug to the term response.

  (props @kalenjohnson, [#481][gh-481])

- Fix error when getting child comment.

  Previously an error occurred when a requested comment had a parent.

  (props @EdHurtig, [#413][gh-413], [#411][gh-411])

- Parse query strings before returning a JSON decode error.

  (props @jtsternberg, [#499][gh-499])

- Typecast the user ID parameter to be an integer for the `/users/{ID}` route.

  (props @dimadin, [#333][gh-333])

- Confirm a given JSONP callback is a string.

  (props @ircrash, @rmccue, [#405][gh-405])

- Register the JavaScript client in the admin.

  (props @tlovett1, [#473][gh-473])

- Remove duplicate error checks on post ids.

  (props @danielbachhuber, [#271][gh-271])

- Update documentation link references to wp-api.org.

  (props @pollyplummer, [#320][gh-320])

- Update documentation to note routes needing authentication.

  (props @kellbot, [#402][gh-402], [#309][gh-309])

- Correct Post route documentation filter parameters.

  (props @modemlooper, @rachelbaker, @rmccue, [#357][gh-357], [#462][gh-462])

- Update taxonomy route documentation with correct paths.

  (props @davidbhayes, [#364][gh-364], [#355][gh-355])

- Remove references to legacy `$fields` parameter.

  (props @JDGrimes, [#326][gh-326])

- Alter readme installation steps to use wp-cli for plugin and permalink setup.

  (props @kadamwhite, [#390][gh-390])

- Add steps to readme for executing tests with `vagrant ssh -c`.

  (props @kadamwhite, [#416][gh-416])

- Update readme to include provision step for testing suite.

  (props @ironpaperweight, [#396][gh-396])

- Update readme Getting Started link.

  (props @NikV, [#519][gh-519])

- Update readme Chassis repository links.

  (props @Japh, [#505][gh-505])

- Clean-up of `docs` folder.

  (props @pollyplummer, [#441][gh-441])

- Documentation audit for plugin.php file.

  (props @DrewAPicture, [#293][gh-293])

- Rename tests to match class file naming.

  (props @danielbachhuber, @rmccue, [#359][gh-359])

- Add license.txt file with license terms.

  (props @rachelbaker, [#393][gh-393], [#384][gh-384])

- Fix test_root when using WordPress.org developer checkout.

  (props @markoheijnen, [#437][gh-437])

[View all changes](https://github.com/rmccue/WP-API/compare/1.1.1...1.2)

[gh-271]: https://github.com/WP-API/WP-API/issues/271
[gh-281]: https://github.com/WP-API/WP-API/issues/281
[gh-293]: https://github.com/WP-API/WP-API/issues/293
[gh-294]: https://github.com/WP-API/WP-API/issues/294
[gh-300]: https://github.com/WP-API/WP-API/issues/300
[gh-305]: https://github.com/WP-API/WP-API/issues/305
[gh-309]: https://github.com/WP-API/WP-API/issues/309
[gh-316]: https://github.com/WP-API/WP-API/issues/316
[gh-320]: https://github.com/WP-API/WP-API/issues/320
[gh-321]: https://github.com/WP-API/WP-API/issues/321
[gh-326]: https://github.com/WP-API/WP-API/issues/326
[gh-333]: https://github.com/WP-API/WP-API/issues/333
[gh-333]: https://github.com/WP-API/WP-API/issues/333
[gh-335]: https://github.com/WP-API/WP-API/issues/335
[gh-347]: https://github.com/WP-API/WP-API/issues/347
[gh-355]: https://github.com/WP-API/WP-API/issues/355
[gh-357]: https://github.com/WP-API/WP-API/issues/357
[gh-358]: https://github.com/WP-API/WP-API/issues/358
[gh-359]: https://github.com/WP-API/WP-API/issues/359
[gh-364]: https://github.com/WP-API/WP-API/issues/364
[gh-374]: https://github.com/WP-API/WP-API/issues/374
[gh-376]: https://github.com/WP-API/WP-API/issues/376
[gh-377]: https://github.com/WP-API/WP-API/issues/377
[gh-378]: https://github.com/WP-API/WP-API/issues/378
[gh-380]: https://github.com/WP-API/WP-API/issues/380
[gh-384]: https://github.com/WP-API/WP-API/issues/384
[gh-390]: https://github.com/WP-API/WP-API/issues/390
[gh-391]: https://github.com/WP-API/WP-API/issues/391
[gh-392]: https://github.com/WP-API/WP-API/issues/392
[gh-393]: https://github.com/WP-API/WP-API/issues/393
[gh-396]: https://github.com/WP-API/WP-API/issues/396
[gh-397]: https://github.com/WP-API/WP-API/issues/397
[gh-401]: https://github.com/WP-API/WP-API/issues/401
[gh-402]: https://github.com/WP-API/WP-API/issues/402
[gh-405]: https://github.com/WP-API/WP-API/issues/405
[gh-410]: https://github.com/WP-API/WP-API/issues/410
[gh-411]: https://github.com/WP-API/WP-API/issues/411
[gh-413]: https://github.com/WP-API/WP-API/issues/413
[gh-416]: https://github.com/WP-API/WP-API/issues/416
[gh-437]: https://github.com/WP-API/WP-API/issues/437
[gh-438]: https://github.com/WP-API/WP-API/issues/438
[gh-441]: https://github.com/WP-API/WP-API/issues/441
[gh-455]: https://github.com/WP-API/WP-API/issues/455
[gh-458]: https://github.com/WP-API/WP-API/issues/458
[gh-461]: https://github.com/WP-API/WP-API/issues/461
[gh-462]: https://github.com/WP-API/WP-API/issues/462
[gh-473]: https://github.com/WP-API/WP-API/issues/473
[gh-474]: https://github.com/WP-API/WP-API/issues/474
[gh-481]: https://github.com/WP-API/WP-API/issues/481
[gh-486]: https://github.com/WP-API/WP-API/issues/486
[gh-499]: https://github.com/WP-API/WP-API/issues/499
[gh-505]: https://github.com/WP-API/WP-API/issues/505
[gh-519]: https://github.com/WP-API/WP-API/issues/519
[gh-524]: https://github.com/WP-API/WP-API/issues/524
[gh-528]: https://github.com/WP-API/WP-API/issues/528
[gh-595]: https://github.com/WP-API/WP-API/issues/595
[gh-730]: https://github.com/WP-API/WP-API/issues/730
[gh-933]: https://github.com/WP-API/WP-API/issues/933
[gh-985]: https://github.com/WP-API/WP-API/issues/985

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
