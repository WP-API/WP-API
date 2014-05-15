(function ($) {
	window.wp = window.wp || {};
	var api = wp.api = wp.api || {};

	// Date parsing code
	// From: https://github.com/csnover/js-iso8601
	// Copyright 2011 Colin Snover, MIT Licensed
	var origParse = Date.parse, numericKeys = [ 1, 4, 5, 6, 7, 10, 11 ];
	api.parseISO8601 = function (date) {
		var timestamp, struct, minutesOffset = 0;

		// ES5 §15.9.4.2 states that the string should attempt to be parsed as a Date Time String Format string
		// before falling back to any implementation-specific date parsing, so that’s what we do, even if native
		// implementations could be faster
		//              1 YYYY                2 MM       3 DD           4 HH    5 mm       6 ss        7 msec        8 Z 9 ±    10 tzHH    11 tzmm
		if ((struct = /^(\d{4}|[+\-]\d{6})(?:-(\d{2})(?:-(\d{2}))?)?(?:T(\d{2}):(\d{2})(?::(\d{2})(?:\.(\d{3}))?)?(?:(Z)|([+\-])(\d{2})(?::(\d{2}))?)?)?$/.exec(date))) {
			// avoid NaN timestamps caused by “undefined” values being passed to Date.UTC
			for (var i = 0, k; (k = numericKeys[i]); ++i) {
				struct[k] = +struct[k] || 0;
			}

			// allow undefined days and months
			struct[2] = (+struct[2] || 1) - 1;
			struct[3] = +struct[3] || 1;

			if (struct[8] !== 'Z' && struct[9] !== undefined) {
				minutesOffset = struct[10] * 60 + struct[11];

				if (struct[9] === '+') {
					minutesOffset = 0 - minutesOffset;
				}
			}

			timestamp = Date.UTC(struct[1], struct[2], struct[3], struct[4], struct[5] + minutesOffset, struct[6], struct[7]);
		}
		else {
			timestamp = origParse ? origParse(date) : NaN;
		}

		return timestamp;
	};

	// ECMAScript 5 shim, from MDN
	// https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Global_Objects/Date/toISOString
	if ( !Date.prototype.toISOString ) {
		var pad = function (number) {
			var r = String(number);
			if ( r.length === 1 ) {
				r = '0' + r;
			}
			return r;
		};

		Date.prototype.toISOString = function() {
			return this.getUTCFullYear()
				+ '-' + pad( this.getUTCMonth() + 1 )
				+ '-' + pad( this.getUTCDate() )
				+ 'T' + pad( this.getUTCHours() )
				+ ':' + pad( this.getUTCMinutes() )
				+ ':' + pad( this.getUTCSeconds() )
				+ '.' + String( (this.getUTCMilliseconds()/1000).toFixed(3) ).slice( 2, 5 )
				+ 'Z';
		};
	}

	// Set the root URL from the global variable, if available
	api.root = "";
	if (wpApiOptions) {
		api.root = wpApiOptions.base;
		api.nonce = wpApiOptions.nonce;
	}

	/**
	 * wp.api.models
	 *
	 * Model container
	 */
	api.models = {};

	/**
	 * wp.api.models.Base
	 *
	 * Base model for API objects
	 */
	api.models.Base = Backbone.Model.extend({
		sync: function (method, model, options) {
			var options = options || {};

			var beforeSend = options.beforeSend;
			options.beforeSend = function(xhr) {
				xhr.setRequestHeader('X-WP-Nonce', api.nonce);

				if (beforeSend) {
					return beforeSend.apply(this, arguments);
				}
			};

			return Backbone.sync(method, model, options);
		}
	});

	/**
	 * wp.api.models.User
	 *
	 * User Entity model
	 */
	api.models.User = api.models.Base.extend({
		urlRoot: api.root + "/users",

		defaults: {
			ID: 0,
			name: "",
			slug: "",
			URL: "",
			avatar: "",
			meta: {
				links: {}
			}
		},

		avatar: function (size) {
			return this.get('avatar') + '&s=' + size;
		}
	});

	var parseable_dates = [ 'date', 'modified' ];

	/**
	 * wp.api.models.Post
	 *
	 * Post Entity model
	 */
	api.models.Post = api.models.Base.extend({
		// Technically, these are probably not needed
		defaults: function () {
			return {
				ID:             0,
				title:          "",
				status:         "draft",
				type:           "post",
				author:         new api.models.User(),
				content:        "",
				parent:         0,
				link:           "",
				date:           new Date(),
				// date_gmt:       new Date(),
				modified:       new Date(),
				// modified_gmt:   new Date(),
				format:         "standard",
				slug:           "",
				guid:           "",
				excerpt:        "",
				menu_order:     0,
				comment_status: "open",
				ping_status:    "open",
				sticky:         false,
				date_tz:        "Etc/UTC",
				modified_tz:    "Etc/UTC",
				terms:          {},
				post_meta:      {},
				meta: {
					links: {}
				}
			};
		},

		idAttribute: "ID",

		urlRoot: api.root + "/posts",

		/**
		 * Serialize the entity
		 *
		 * Overriden for correct date handling
		 * @return {!Object} Serializable attributes
		 */
		toJSON: function () {
			var attributes = _.clone( this.attributes );

			// Remove GMT dates in favour of our native Date objects
			// The API only requires one of `date` and `date_gmt`, so this is
			// safe for use.
			delete attributes.date_gmt;
			delete attributes.modified_gmt;

			// Serialize Date objects back into 8601 strings
			_.each( parseable_dates, function ( key ) {
				attributes[ key ] = attributes[ key ].toISOString();
			});

			return attributes;
		},

		/**
		 * Unserialize the entity
		 *
		 * Overriden for correct date handling
		 * @param {!Object} response Attributes parsed from JSON
		 * @param {!Object} options Request options
		 * @return {!Object} Fully parsed attributes
		 */
		parse: function ( response, options ) {
			// Parse dates into native Date objects
			_.each( parseable_dates, function ( key ) {
				if ( ! ( key in response ) )
					return;

				var timestamp = api.parseISO8601( response[ key ] );
				response[ key ] = new Date( timestamp );
			});

			// Remove GMT dates in favour of our native Date objects
			delete response.date_gmt;
			delete response.modified_gmt;

			// Parse the author into a User object
			response.author = new api.models.User(response.author);

			return response;
		},

		/**
		 * Get parent post
		 *
		 * @return {wp.api.models.Post} Parent post, null if not found
		 */
		parent: function () {
			var parent = this.get('parent');

			// Return null if we don't have a parent
			if (parent === 0) {
				return null;
			}

			// Can we get this from its collection?
			if (this.collection) {
				return this.collection.get(parent);
			}
			else {
				// Otherwise, get the post directly
				var post = new api.models.Post({
					id: parent
				});

				// Note that this acts asynchronously
				api.models.post.fetch();
				return post;
			}
		}
	});

	/**
	 * wp.api.collections
	 */
	api.collections = {};

	/**
	 * wp.api.collections.Posts
	 */
	api.collections.Posts = Backbone.Collection.extend({
		url: api.root + "/posts",

		model: api.models.Post
	});
})(jQuery);