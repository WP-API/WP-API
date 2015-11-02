( function( WP_API_Settings, Backbone, _, window, undefined ) {
	'use strict';

	window.wp = window.wp || {};

	wp.api = {
		models: {},
		collections: {},
		utils: {}
	};

	/**
	 * ECMAScript 5 shim, from MDN.
	 * @link https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Global_Objects/Date/toISOString
	 */
	if ( ! Date.prototype.toISOString ) {
		var pad = function( number ) {
			var r = String( number );
			if ( r.length === 1 ) {
				r = '0' + r;
			}

			return r;
		};

		Date.prototype.toISOString = function() {
			return this.getUTCFullYear() +
				'-' + pad( this.getUTCMonth() + 1 ) +
				'-' + pad( this.getUTCDate() ) +
				'T' + pad( this.getUTCHours() ) +
				':' + pad( this.getUTCMinutes() ) +
				':' + pad( this.getUTCSeconds() ) +
				'.' + String( ( this.getUTCMilliseconds() / 1000 ).toFixed( 3 ) ).slice( 2, 5 ) +
				'Z';
		};
	}

	/**
	 * Parse date into ISO8601 format.
	 *
	 * @param {Date} date.
	 */
	wp.api.utils.parseISO8601 = function( date ) {
		var timestamp, struct, i, k,
			minutesOffset = 0,
			numericKeys = [ 1, 4, 5, 6, 7, 10, 11 ];

		// ES5 §15.9.4.2 states that the string should attempt to be parsed as a Date Time String Format string
		// before falling back to any implementation-specific date parsing, so that’s what we do, even if native
		// implementations could be faster.
		//              1 YYYY                2 MM       3 DD           4 HH    5 mm       6 ss        7 msec        8 Z 9 ±    10 tzHH    11 tzmm
		if ( ( struct = /^(\d{4}|[+\-]\d{6})(?:-(\d{2})(?:-(\d{2}))?)?(?:T(\d{2}):(\d{2})(?::(\d{2})(?:\.(\d{3}))?)?(?:(Z)|([+\-])(\d{2})(?::(\d{2}))?)?)?$/.exec( date ) ) ) {
			// Avoid NaN timestamps caused by “undefined” values being passed to Date.UTC.
			for ( i = 0; ( k = numericKeys[i] ); ++i ) {
				struct[k] = +struct[k] || 0;
			}

			// Allow undefined days and months.
			struct[2] = ( +struct[2] || 1 ) - 1;
			struct[3] = +struct[3] || 1;

			if ( struct[8] !== 'Z' && struct[9] !== undefined ) {
				minutesOffset = struct[10] * 60 + struct[11];

				if ( struct[9] === '+' ) {
					minutesOffset = 0 - minutesOffset;
				}
			}

			timestamp = Date.UTC( struct[1], struct[2], struct[3], struct[4], struct[5] + minutesOffset, struct[6], struct[7] );
		} else {
			timestamp = Date.parse ? Date.parse( date ) : NaN;
		}

		return timestamp;
	};

	/**
	 * Array of parseable dates.
	 *
	 * @type {string[]}.
	 */
	var parseable_dates = [ 'date', 'modified', 'date_gmt', 'modified_gmt' ];

	/**
	 * Mixin for all content that is time stamped.
	 *
	 * @type {{toJSON: toJSON, parse: parse}}.
	 */
	var TimeStampedMixin = {
		/**
		 * Serialize the entity pre-sync.
		 *
		 * @returns {*}.
		 */
		toJSON: function() {
			var attributes = _.clone( this.attributes );

			// Serialize Date objects back into 8601 strings.
			_.each( parseable_dates, function( key ) {
				if ( key in attributes ) {
					attributes[key] = attributes[key].toISOString();
				}
			});

			return attributes;
		},

		/**
		 * Unserialize the fetched response.
		 *
		 * @param {*} response.
		 * @returns {*}.
		 */
		parse: function( response ) {

			// Parse dates into native Date objects.
			_.each( parseable_dates, function ( key ) {
				if ( ! ( key in response ) ) {
					return;
				}

				var timestamp = wp.api.utils.parseISO8601( response[key] );
				response[key] = new Date( timestamp );
			});

			// Parse the author into a User object.
			if ( 'undefined' !== typeof response.author ) {
				response.author = new wp.api.models.User( response.author );
			}

			return response;
		}
	};

	/**
	 * Mixin for all hierarchical content types such as posts.
	 *
	 * @type {{parent: parent}}.
	 */
	var HierarchicalMixin = {
		/**
		 * Get parent object.
		 *
		 * @returns {Backbone.Model}
		 */
		parent: function() {

			var object, parent = this.get( 'parent' );

			// Return null if we don't have a parent.
			if ( parent === 0 ) {
				return null;
			}

			var parentModel = this;

			if ( 'undefined' !== typeof this.parentModel ) {
				/**
				 * Probably a better way to do this. Perhaps grab a cached version of the
				 * instantiated model?
				 */
				parentModel = new this.parentModel();
			}

			// Can we get this from its collection?
			if ( parentModel.collection ) {
				return parentModel.collection.get( parent );
			} else {

				// Otherwise, get the object directly.
				object = new parentModel.constructor( {
					id: parent
				});

				// Note that this acts asynchronously.
				object.fetch();

				return object;
			}
		}
	};

	/**
	 * Private Backbone base model for all models.
	 */
	var BaseModel = Backbone.Model.extend(
		/** @lends BaseModel.prototype  */
		{
			/**
			 * Set nonce header before every Backbone sync.
			 *
			 * @param {string} method.
			 * @param {Backbone.Model} model.
			 * @param {{beforeSend}, *} options.
			 * @returns {*}.
			 */
			sync: function( method, model, options ) {
				options = options || {};

				if ( 'undefined' !== typeof WP_API_Settings.nonce ) {
					var beforeSend = options.beforeSend;

					options.beforeSend = function( xhr ) {
						xhr.setRequestHeader( 'X-WP-Nonce', WP_API_Settings.nonce );

						if ( beforeSend ) {
							return beforeSend.apply( this, arguments );
						}
					};
				}

				return Backbone.sync( method, model, options );
			}
		}
	);

	/**
	 * Backbone model for single users.
	 */
	wp.api.models.User = BaseModel.extend(
		/** @lends User.prototype  */
		{
			idAttribute: 'id',

			urlRoot: WP_API_Settings.root + 'wp/v2/users',

			defaults: {
				id: null,
				avatar_url: {},
				capabilities: {},
				description: '',
				email: '',
				extra_capabilities: {},
				first_name: '',
				last_name: '',
				link: '',
				name: '',
				nickname: '',
				registered_date: new Date(),
				roles: [],
				slug: '',
				url: '',
				username: '',
				_links: {}
			}
		}
	);

	/**
	 * Model for Taxonomy.
	 */
	wp.api.models.Taxonomy = BaseModel.extend(
		/** @lends Taxonomy.prototype  */
		{
			idAttribute: 'slug',

			urlRoot: WP_API_Settings.root + 'wp/v2/taxonomies',

			defaults: {
				name: '',
				slug: null,
				description: '',
				labels: {},
				types: [],
				show_cloud: false,
				hierarchical: false
			}
		}
	);

	/**
	 * Backbone model for term.
	 */
	wp.api.models.Term = BaseModel.extend(
		/** @lends Term.prototype */
		{
			idAttribute: 'id',

			/**
			 * Return URL for the model.
			 *
			 * @returns {string}
			 */
			url: function() {
				var id = this.get( 'id' );
				id = id || '';

				return WP_API_Settings.root + 'wp/v2/taxonomies/' + this.get( 'taxonomy' ) + '/terms/' + id;
			},

			defaults: {
				id: null,
				name: '',
				slug: '',
				description: '',
				parent: null,
				count: 0,
				link: '',
				taxonomy: '',
				_links: {}
			}

		}
	);

	/**
	 * Backbone model for single posts.
	 */
	wp.api.models.Post = BaseModel.extend( _.extend(
		/** @lends Post.prototype  */
		{
			idAttribute: 'id',

			urlRoot: WP_API_Settings.root + 'wp/v2/posts',

			defaults: {
				id: null,
				date: new Date(),
				date_gmt: new Date(),
				guid: {},
				link: '',
				modified: new Date(),
				modified_gmt: new Date(),
				password: '',
				slug: '',
				status: 'draft',
				type: 'post',
				title: {},
				content: {},
				author: null,
				excerpt: {},
				featured_image: null,
				comment_status: 'open',
				ping_status: 'open',
				sticky: false,
				format: 'standard',
				_links: {}
			}
		}, TimeStampedMixin, HierarchicalMixin )
	);

	/**
	 * Backbone model for pages.
	 */
	wp.api.models.Page = BaseModel.extend( _.extend(
		/** @lends Page.prototype  */
		{
			idAttribute: 'id',

			urlRoot: WP_API_Settings.root + 'wp/v2/pages',

			defaults: {
				id: null,
				date: new Date(),
				date_gmt: new Date(),
				guid: {},
				link: '',
				modified: new Date(),
				modified_gmt: new Date(),
				password: '',
				slug: '',
				status: 'draft',
				type: 'page',
				title: {},
				content: {},
				author: null,
				excerpt: {},
				featured_image: null,
				comment_status: 'closed',
				ping_status: 'closed',
				menu_order: null,
				template: '',
				_links: {}
			}
		}, TimeStampedMixin, HierarchicalMixin )
	);

	/**
	 * Backbone model for revisions.
	 */
	wp.api.models.Revision = BaseModel.extend( _.extend(
		/** @lends Revision.prototype */
		{
			idAttribute: 'id',

			/**
			 * Return URL for the model.
			 *
			 * @returns {string}.
			 */
			url: function() {
				var id = this.get( 'id' ) || '';

				return WP_API_Settings.root + 'wp/v2/posts/' + id + '/revisions';
			},

			defaults: {
				id: null,
				author: null,
				date: new Date(),
				date_gmt: new Date(),
				guid: {},
				modified: new Date(),
				modified_gmt: new Date(),
				parent: 0,
				slug: '',
				title: {},
				content: {},
				excerpt: {},
				_links: {}
			}

		}, TimeStampedMixin, HierarchicalMixin )
	);

	/**
	 * Backbone model for media items.
	 */
	wp.api.models.Media = BaseModel.extend( _.extend(
		/** @lends Media.prototype */
		{
			idAttribute: 'id',

			urlRoot: WP_API_Settings.root + 'wp/v2/media',

			defaults: {
				id: null,
				date: new Date(),
				date_gmt: new Date(),
				guid: {},
				link: '',
				modified: new Date(),
				modified_gmt: new Date(),
				password: '',
				slug: '',
				status: 'draft',
				type: 'attachment',
				title: {},
				author: null,
				comment_status: 'open',
				ping_status: 'open',
				alt_text: '',
				caption: '',
				description: '',
				media_type: '',
				media_details: {},
				post: null,
				source_url: '',
				_links: {}
			},

			/**
			 * @class Represent a media item.
			 * @augments Backbone.Model.
			 * @constructs
			 */
			initialize: function() {

				// Todo: what of the parent model is a page?
				this.parentModel = wp.api.models.Post;
			}
		}, TimeStampedMixin, HierarchicalMixin )
	);

	/**
	 * Backbone model for comments.
	 */
	wp.api.models.Comment = BaseModel.extend( _.extend(
		/** @lends Comment.prototype */
		{
			idAttribute: 'id',

			defaults: {
				id: null,
				author: null,
				author_email: '',
				author_ip: '',
				author_name: '',
				author_url: '',
				author_user_agent: '',
				content: {},
				date: new Date(),
				date_gmt: new Date(),
				karma: 0,
				link: '',
				parent: 0,
				post: null,
				status: 'hold',
				type: '',
				_links: {}
			},

			/**
			 * Return URL for model.
			 *
			 * @returns {string}.
			 */
			url: function() {
				var post_id = this.get( 'post' );
				post_id = post_id || '';

				var id = this.get( 'id' );
				id = id || '';

				return WP_API_Settings.root + 'wp/v2/posts/' + post_id + '/comments/' + id;
			}
		}, TimeStampedMixin, HierarchicalMixin )
	);

	/**
	 * Backbone model for single post types.
	 */
	wp.api.models.PostType = BaseModel.extend(
		/** @lends PostType.prototype */
		{
			idAttribute: 'slug',

			urlRoot: WP_API_Settings.root + 'wp/v2/posts/types',

			defaults: {
				slug: null,
				name: '',
				description: '',
				labels: {},
				hierarchical: false
			},

			/**
			 * Prevent model from being saved.
			 *
			 * @returns {boolean}.
			 */
			save: function() {
				return false;
			},

			/**
			 * Prevent model from being deleted.
			 *
			 * @returns {boolean}.
			 */
			'delete': function() {
				return false;
			}
		}
	);

	/**
	 * Backbone model for a post status.
	 */
	wp.api.models.PostStatus = BaseModel.extend(
		/** @lends PostStatus.prototype */
		{
			idAttribute: 'slug',

			urlRoot: WP_API_Settings.root + 'wp/v2/posts/statuses',

			defaults: {
				slug: null,
				name: '',
				'public': true,
				'protected': false,
				'private': false,
				queryable: true,
				show_in_list: true,
				_links: {}
			},

			/**
			 * Prevent model from being saved.
			 *
			 * @returns {boolean}.
			 */
			save: function() {
				return false;
			},

			/**
			 * Prevent model from being deleted.
			 *
			 * @returns {boolean}.
			 */
			'delete': function() {
				return false;
			}
		}
	);

	/**
	 * Contains basic collection functionality such as pagination.
	 */
	var BaseCollection = Backbone.Collection.extend(
		/** @lends BaseCollection.prototype  */
		{

			/**
			 * Setup default state.
			 */
			initialize: function() {
				this.state = {
					data: {},
					currentPage: null,
					totalPages: null,
					totalObjects: null
				};
			},

			/**
			 * Overwrite Backbone.Collection.sync to pagination state based on response headers.
			 *
			 * Set nonce header before every Backbone sync.
			 *
			 * @param {string} method.
			 * @param {Backbone.Model} model.
			 * @param {{success}, *} options.
			 * @returns {*}.
			 */
			sync: function( method, model, options ) {
				options = options || {};
				var beforeSend = options.beforeSend,
					self = this;

				if ( 'undefined' !== typeof WP_API_Settings.nonce ) {
					options.beforeSend = function( xhr ) {
						xhr.setRequestHeader( 'X-WP-Nonce', WP_API_Settings.nonce );

						if ( beforeSend ) {
							return beforeSend.apply( self, arguments );
						}
					};
				}

				if ( 'read' === method ) {
					if ( options.data ) {
						self.state.data = _.clone( options.data );

						delete self.state.data.page;
					} else {
						self.state.data = options.data = {};
					}

					if ( 'undefined' === typeof options.data.page ) {
						self.state.currentPage = null;
						self.state.totalPages = null;
						self.state.totalObjects = null;
					} else {
						self.state.currentPage = options.data.page - 1;
					}

					var success = options.success;
					options.success = function( data, textStatus, request ) {
						self.state.totalPages = parseInt( request.getResponseHeader( 'x-wp-totalpages' ), 10 );
						self.state.totalObjects = parseInt( request.getResponseHeader( 'x-wp-total' ), 10 );

						if ( self.state.currentPage === null ) {
							self.state.currentPage = 1;
						} else {
							self.state.currentPage++;
						}

						if ( success ) {
							return success.apply( this, arguments );
						}
					};
				}

				return Backbone.sync( method, model, options );
			},

			/**
			 * Fetches the next page of objects if a new page exists.
			 *
			 * @param {data: {page}} options.
			 * @returns {*}.
			 */
			more: function( options ) {
				options = options || {};
				options.data = options.data || {};

				_.extend( options.data, this.state.data );

				if ( 'undefined' === typeof options.data.page ) {
					if ( ! this.hasMore() ) {
						return false;
					}

					if ( this.state.currentPage === null || this.state.currentPage <= 1 ) {
						options.data.page = 2;
					} else {
						options.data.page = this.state.currentPage + 1;
					}
				}

				return this.fetch( options );
			},

			/**
			 * Returns true if there are more pages of objects available.
			 *
			 * @returns null|boolean.
			 */
			hasMore: function() {
				if ( this.state.totalPages === null ||
					 this.state.totalObjects === null ||
					 this.state.currentPage === null ) {
					return null;
				} else {
					return ( this.state.currentPage < this.state.totalPages );
				}
			}
		}
	);

	/**
	 * Backbone collection for posts.
	 */
	wp.api.collections.Posts = BaseCollection.extend(
		/** @lends Posts.prototype */
		{
			url: WP_API_Settings.root + 'wp/v2/posts',

			model: wp.api.models.Post
		}
	);

	/**
	 * Backbone collection for pages.
	 */
	wp.api.collections.Pages = BaseCollection.extend(
		/** @lends Pages.prototype */
		{
			url: WP_API_Settings.root + 'wp/v2/pages',

			model: wp.api.models.Page
		}
	);

	/**
	 * Backbone users collection.
	 */
	wp.api.collections.Users = BaseCollection.extend(
		/** @lends Users.prototype */
		{
			url: WP_API_Settings.root + 'wp/v2/users',

			model: wp.api.models.User
		}
	);

	/**
	 * Backbone post statuses collection.
	 */
	wp.api.collections.PostStatuses = BaseCollection.extend(
		/** @lends PostStatuses.prototype */
		{
			url: WP_API_Settings.root + 'wp/v2/statuses',

			model: wp.api.models.PostStatus,

			parse: function( response ) {
				var responseArray = [];

				for ( var property in response ) {
					if ( response.hasOwnProperty( property ) ) {
						responseArray.push( response[property] );
					}
				}

				return this.constructor.__super__.parse.call( this, responseArray );
			}
		}
	);

	/**
	 * Backbone media library collection.
	 */
	wp.api.collections.MediaLibrary = BaseCollection.extend(
		/** @lends MediaLibrary.prototype */
		{
			url: WP_API_Settings.root + 'wp/v2/media',

			model: wp.api.models.Media
		}
	);

	/**
	 * Backbone taxonomy collection.
	 */
	wp.api.collections.Taxonomies = BaseCollection.extend(
		/** @lends Taxonomies.prototype */
		{
			model: wp.api.models.Taxonomy,

			url: WP_API_Settings.root + 'wp/v2/taxonomies'
		}
	);

	/**
	 * Backbone comment collection.
	 */
	wp.api.collections.Comments = BaseCollection.extend(
		/** @lends Comments.prototype */
		{
			model: wp.api.models.Comment,

			/**
			 * Return URL for collection.
			 *
			 * @returns {string}.
			 */
			url: WP_API_Settings.root + 'wp/v2/comments'
		}
	);

	/**
	 * Backbone post type collection.
	 */
	wp.api.collections.PostTypes = BaseCollection.extend(
		/** @lends PostTypes.prototype */
		{
			model: wp.api.models.PostType,

			url: WP_API_Settings.root + 'wp/v2/types',

			parse: function( response ) {
				var responseArray = [];

				for ( var property in response ) {
					if ( response.hasOwnProperty( property ) ) {
						responseArray.push( response[property] );
					}
				}

				return this.constructor.__super__.parse.call( this, responseArray );
			}
		}
	);

	/**
	 * Backbone terms collection.
	 *
	 * Usage: new wp.api.collections.Terms( {}, { taxonomy: 'taxonomy-slug' } )
	 */
	wp.api.collections.Terms = BaseCollection.extend(
		/** @lends Terms.prototype */
		{
			model: wp.api.models.Term,

			taxonomy: 'category',

			/**
			 * @class Represent an array of terms.
			 * @augments Backbone.Collection.
			 * @constructs
			 */
			initialize: function( models, options ) {
				if ( 'undefined' !== typeof options && options.taxonomy ) {
					this.taxonomy = options.taxonomy;
				}

				BaseCollection.prototype.initialize.apply( this, arguments );
			},

			/**
			 * Return URL for collection.
			 *
			 * @returns {string}.
			 */
			url: function() {
				return WP_API_Settings.root + 'wp/v2/terms/' + this.taxonomy;
			}
		}
	);

	/**
	 * Backbone revisions collection.
	 *
	 * Usage: new wp.api.collections.Revisions( {}, { parent: POST_ID } ).
	 */
	wp.api.collections.Revisions = BaseCollection.extend(
		/** @lends Revisions.prototype */
		{
			model: wp.api.models.Revision,

			parent: null,

			/**
			 * @class Represent an array of revisions.
			 * @augments Backbone.Collection.
			 * @constructs
			 */
			initialize: function( models, options ) {
				BaseCollection.prototype.initialize.apply( this, arguments );

				if ( options && options.parent ) {
					this.parent = options.parent;
				}
			},

			/**
			 * return URL for collection.
			 *
			 * @returns {string}.
			 */
			url: function() {
				return WP_API_Settings.root + 'wp/v2/posts/' + this.parent + '/revisions';
			}
		}
	);

	/**
	 * Todo: Handle schema endpoints.
	 */

	/**
	 * Todo: Handle post meta.
	 */

})( WP_API_Settings, Backbone, _, window, ( void 0 ) );
