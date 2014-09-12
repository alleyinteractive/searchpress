# SearchPress [<img align="right" src="https://travis-ci.org/alleyinteractive/searchpress.svg?branch=master" />](https://travis-ci.org/alleyinteractive/searchpress)

Elasticsearch integration for WordPress.


Currently in Beta
-----------------

SearchPress is currently in beta. The most significant impact of this is backwards compatibility. Until SearchPress reaches its first full release (1.0), backwards compatibility will not be guaranteed. In most cases, this will only impact you if you're using SearchPress in an advanced or custom manner. That said, even if you aren't, it's best to perform a reindex after updating SearchPress.

Each stable release will be tagged and you'll be able to [download that release indefinitely](https://github.com/alleyinteractive/searchpress/releases).

`master` should be considered alpha/pre-release and should not be used in production environments. `master` will only ever contain stable code (as illustrated by Travis CI), but it's likely that things in master will change.



Pre-requisites
--------------

* [elasticsearch](http://elasticsearch.org/)


Setup
-----

1. Upload to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. You'll be prompted to add your elasticsearch endpoint and to index your posts
4. Once indexing is complete, you're good to go!


Changelog
---------

### 0.2-alpha

* Adds unit testing
* Significant updates to mapping *(breaking change)*
* Enforce data types when indexing
* Adds helper functions
* Adds support for ES 1.0+ *(breaking change)*
* Refactors search *(breaking change)*
* Removes SP_Config::unserialize_meta()


### 0.1

* First release!
