# SearchPress [<img align="right" src="https://travis-ci.org/alleyinteractive/searchpress.svg?branch=master" />](https://travis-ci.org/alleyinteractive/searchpress)

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


Changelog
---------

### 0.4

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
