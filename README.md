# SearchPress [<img align="right" src="https://travis-ci.org/alleyinteractive/searchpress.svg?branch=master" />](https://travis-ci.org/alleyinteractive/searchpress)

![searchpress](https://user-images.githubusercontent.com/465154/116134994-887eff80-a69e-11eb-8e6a-cd7c51a9a5eb.png)

Elasticsearch integration for WordPress.


Currently in Beta
-----------------

SearchPress is currently in beta. The most significant impact of this is backwards compatibility. Until SearchPress reaches its first full release (1.0), backwards compatibility will not be guaranteed. In most cases, this will only impact you if you're using SearchPress in an advanced or custom manner. That said, even if you aren't, it's best to perform a reindex after updating SearchPress.

Each stable release will be tagged and you'll be able to [download that release indefinitely](https://github.com/alleyinteractive/searchpress/releases).

`master` should be considered a nightly build and should be tested carefully before deploying to production environments. `master` will never contain code known to not be stable, and SearchPress has a thorough battery of unit tests to help maintain that. Furthermore, SearchPress uses Travis CI to test against a range of versions of PHP, WordPress, and Elasticsearch to help maximize stability and minimize surprises.


Prerequisites
-------------

* [elasticsearch](https://www.elastic.co/products/elasticsearch) 1.7+; 5.0+ recommended.
* PHP 5.3+; PHP 7 recommended.


Setup
-----

1. Upload to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. You'll be prompted to add your elasticsearch endpoint and to index your posts
4. Once indexing is complete, you're good to go!


Indexing Post Meta
------------------

In early versions of SearchPress, SearchPress would index almost all post meta with the post. Starting in the 0.4 release, SearchPress only indexes the post meta that it is explicitly told to index. Further, it only indexes post meta in the _data types_ that a site's developer plans to use. The principal reason behind this change is performance, and to prevent ["mappings explosion"](https://www.elastic.co/guide/en/elasticsearch/reference/master/mapping.html#mapping-limit-settings).

Data type casting will only be attempted for a key if the opt-in callback specifies that type for the key in question (see example below for the full list of possible types). However, the data type will still only be indexed if the type casting is successful. For example, attempting to index the meta value `"WordPress"` as a `long` would fail, since it is not a numeric value. This failure is silent, for better or worse, but type casting is overall quite forgiving.

If a meta key is allowed to be indexed, the meta value will _always_ be indexed as an unanalyzed string (`post_meta.*.raw`) and that type need not be specified. This is primarily for compatibility with [ES_WP_Query](https://github.com/alleyinteractive/es-wp-query), which depends on that key in `EXISTS` queries, among others.

### How to index post meta

```php
add_filter(
    'sp_post_allowed_meta',
    function( $allowed_meta ) {
        // Tell SearchPress to index 'some_meta_key' post meta when encountered.
        $allowed_meta['some_meta_key'] = [
            'value',    // Index as an analyzed string.
            'boolean',  // Index as a boolean value.
            'long',     // Index as a "long" (integer).
            'double',   // Index as a "double" (floating point number).
            'date',     // Index as a GMT date-only value in the format Y-m-d.
            'datetime', // Index as a GMT datetime value in the format Y-m-d H:i:s.
            'time',     // Index as a GMT time-only value in the format H:i:s.
        ];
        return $allowed_meta;
    }
);
```

Changelog
---------

### 0.4.1

* Updates grunt packages to latest versions
* Documents deprecated/removed filters in 0.4.0
* Improves handling of indexing batch with no indexable posts
* Adds filter `sp_post_index_path` for single post paths
* Adds filter `sp_bulk_index_path` for bulk index paths

### 0.4

* **CRITICAL BREAKING CHANGE:** Post meta indexing is now opt-in. See README for more information.
* **POTENTIAL BREAKING CHANGE:** Removes `sp_post_indexable_meta` filter
* Removes `sp_post_ignored_postmeta` filter
* Adds support for ES 5.x, 6.x, 7.x
* Fixes indexing bug with parentless attachments
* Fixes a bug with bulk syncing attachments
* Improves flexibility for custom indexing of posts
* Improves facet lists to exclude current selections
* Adds option in the admin to index content without flushing
* Fixes bug with cached list of post types to sync
* Fixes conflicts with Advanced Post Cache and CLI-based cron runners
* Adds completion suggester API for search-as-you-type functionality
* Fixes bug with SSL cert verification
* Overhaul of phpunit testing environment for performance
* General coding standards cleanup


### 0.3

* Adds heartbeat to monitor Elasticsearch
* Improves capabilities handling for admin settings
* Adds a status tab to admin page
* Improves test coverage for heartbeat and admin settings
* Fixes bug with post type facet field
* Allows multiple post IDs to be passed to cli index command
* Locally cache API host to improve external referencing to it
* Fixes edge case bugs with indexing, e.g. with long meta strings
* Improves indexed/searched post types and statuses handling
* Tests across a wider range of ES versions using CI
* Stores/checks mapping version
* General code improvements


### 0.2

* Adds unit testing
* Significant updates to mapping *(breaking change)*
* Enforce data types when indexing
* Adds helper functions
* Adds support for ES 1.0+ *(breaking change)*
* Refactors search *(breaking change)*
* Removes SP_Config::unserialize_meta()
* Adds Heartbeat to automatically disable the integration if ES goes away
* Update to latest WP Coding Standards
* Assorted bug fixes


### 0.1

* First release!
