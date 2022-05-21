<?php
/**
 * SearchPress library: SP_Sync_Manager class
 *
 * @package SearchPress
 */

/**
 * SearchPress Sync Manager
 *
 * Controls the data sync from WordPress to elasticsearch
 *
 * Reminders and considerations while building this:
 *
 * @todo Trigger massive reindex (potentially) when indexed usermeta is edited
 * @todo Trigger massive reindex when term data is edited
 * @todo Changing permalinks should trigger full reindex?
 *
 * @author Matthew Boynes
 */
class SP_Sync_Manager extends SP_Singleton {

	/**
	 * Stores an array of published posts to iterate over.
	 *
	 * @access public
	 * @var bool
	 */
	public $published_posts = false;

	/**
	 * Action for cron job for syncing terms.
	 */
	const ACTION_INDEX_TERM  = 'sp_index_term';

	/**
	 * Setup the singleton.
	 */
	public function setup() {
		if ( SP_Config()->active() ) {
			// When posts & attachments get_updated, queue up syncs.
			add_action( 'save_post', array( $this, 'sync_post' ) );
			add_action( 'edit_attachment', array( $this, 'sync_post' ) );
			add_action( 'add_attachment', array( $this, 'sync_post' ) );
			add_action( 'deleted_post', array( $this, 'delete_post' ) );
			add_action( 'trashed_post', array( $this, 'delete_post' ) );

			// When terms are deleted, queue up syncs to run via cron.
			add_action( 'delete_term', array( $this, 'delete_term_sync' ), 10, 4 );
			add_action( static::ACTION_INDEX_TERM, array( $this, 'resync_term' ), 10 , 3 );

			// @TODO When terms or term relationships get updated, queue up syncs.
			// @TODO When users get updated, queue up syncs for their posts.
			// @TODO When post parents get updated, queue up syncs for their child posts.
		}
	}

	/**
	 * Enqueue a single post to be indexed.
	 *
	 * @param int $post_id The post ID of the post to be indexed.
	 */
	public function sync_post( $post_id ) {
		/**
		 * Flag if the post should be synced asynchronously.
		 *
		 * @param bool $should_sync_async Flag if the post should be synced asynchronously, defaults to true.
		 * @param int  $post_id Post ID.
		 */
		$should_async = apply_filters( 'sp_should_index_async', true, $post_id );

		// Never async for a CLI request.
		if ( ( defined( 'WP_CLI' ) && WP_CLI && ! wp_doing_cron() ) ) {
			$should_async = false;
		}

		if ( $should_async ) {
			update_post_meta( $post_id, '_sp_index', '1' );
			SP_Cron()->schedule_queue_index();
		} else {
			$post = new SP_Post( get_post( $post_id ) );

			/***
			 * Filter the response when indexing the post.
			 *
			 * @param object|WP_Error $response Object when response is successful, WP_Error otherwise.
			 * @param int             $post_id Post ID.
			 */
			$response = apply_filters( 'sp_index_response', SP_API()->index_post( $post ), $post_id );

			if ( is_wp_error( $response ) && 'unindexable-post' === $response->get_error_code() ) {
				// If the post should not be indexed, ensure it's not in the index already.
				// @todo This is excessive, figure out a better way around it.
				$this->delete_post( $post_id );
				do_action( 'sp_debug', "[SP_Sync_Manager] Post {$post_id} is not indexable", $response );
				return;
			}

			if ( ! $this->parse_error( $response, array( 200, 201 ) ) ) {
				do_action( 'sp_debug', "[SP_Sync_Manager] Indexed Post {$post_id}", $response );
			} else {
				do_action( 'sp_debug', "[SP_Sync_Manager] Error Indexing Post {$post_id}", $response );
			}
		}
	}

	/**
	 * Callback for when a term is deleted.
	 * Handles syncing all content in that term.
	 *
	 * @param int $term Term ID.
	 * @param int $tt_id Taxonomy Term ID.
	 * @param string $taxonomy Taxonomy name.
	 * @param WP_Term $deleted_term Copy of the deleted term.
	 */
	public function delete_term_sync( $term, $tt_id, $taxonomy, $deleted_term ) {

		// Never sync during a CLI request, because CLI commands should handle ES syncing themselves.
		// Todo: Consider having the CLI script remove this action instead.
		if ( ( defined( 'WP_CLI' ) && WP_CLI && ! wp_doing_cron() ) ) {
		 return;
		}

		wp_schedule_single_event( time(), static::ACTION_INDEX_TERM, [
			$term,
			$taxonomy,
			$deleted_term->slug
		] );

	}

	/**
	 * Resyncs all content in a term.
	 * Note: This can be used for term deletions and term changes,
	 * but not for an initial content index.
	 * Content has to already be in Elasticsearch index.
	 *
	 * @param $term_id
	 * @param $taxonomy
	 * @param $term_slug
	 *
	 * @return void
	 */
	public function resync_term( $term_id, $taxonomy, $term_slug ) {
		$per_page  = 200;
		$page = 0;
		$processed = 0;

		/**
		 * Fires when a resync is starting.
		 * @param int $term_id Term ID.
		 * @param string $taxonomy Taxonomy.
		 * @param string $term_slug Term Slug.
		 */
		do_action( 'sp_before_resync_term', $term_id, $taxonomy, $term_slug );

		// Debug code -- TO DO remove
		trigger_error( "sp-debug: Starting Term Sync: term ID: $term_id, taxonomy: $taxonomy, slug: $term_slug ",
			E_USER_WARNING );

		/**
		 * Run a query using Elasticsearch.
		 */
		$post_args = array(
			'post_type'           => 'any',
			'posts_per_page'      => $per_page,
			'post_status'         => 'any',
			'suppress_filters'    => false,
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
			'fields'              => 'ids',
			'es'                  => true, // Elasticsearch query.
			'tax_query'           => array( //phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				array(
					'field'    => 'slug',
					'terms'    => $term_slug,
					'taxonomy' => $taxonomy,
				),
			),
		);

		do {
			$post_args['offset'] = $per_page * $page;
			$ids               = get_posts( $post_args );

			if ( ! empty( $ids ) ) {
				// Give us plenty of time to process each of the shards.
				set_time_limit( 15 );
				$sp_posts = array_map( function ( $post_id ) {
					return new \SP_Post( absint( trim( $post_id ) ) );
				}, $ids );
				\SP_API()->index_posts( $sp_posts );
			}
			sp_contain_memory_leaks();

			$page ++;
			$processed = + count( $ids );
		} while ( $per_page === count( $ids ) );

		/**
		 * Fires when a resync has completed.
		 *
		 * @param int $term_id Term ID.
		 * @param string $taxonomy Taxonomy.
		 * @param string $term_slug Term Slug.
		 * @param int $processed Number of posts indexed.
		 */
		do_action( 'sp_after_resync_term', $term_id, $taxonomy, $term_slug, $processed );

		// Debug code -- TO DO remove
		trigger_error( "sp-debug: Finished Term Sync: term ID: $term_id, taxonomy: $taxonomy, slug: $term_slug, Processed $processed",
			E_USER_WARNING );
	}

	/**
	 * Delete a post from the ES index.
	 *
	 * @param int $post_id The post ID of the post to delete from Elasticsearch.
	 */
	public function delete_post( $post_id ) {
		$to_delete = get_option( 'sp_delete', array() );
		if ( ! is_array( $to_delete ) ) {
			$to_delete = array();
		}
		$to_delete[] = absint( $post_id );

		update_option( 'sp_delete', array_unique( $to_delete ), 'no' );
		SP_Cron()->schedule_queue_index();
	}

	/**
	 * Parse any errors found in a single-post ES response.
	 *
	 * @param object $response      SP_API response.
	 * @param array  $allowed_codes Allowed HTTP status codes. Default is array( 200 ).
	 * @return bool True if errors are found, false if successful.
	 */
	protected function parse_error( $response, $allowed_codes = array( 200 ) ) {
		if ( is_wp_error( $response ) ) {
			SP_Sync_Meta()->log( new WP_Error( 'error', gmdate( '[Y-m-d H:i:s] ' ) . $response->get_error_message(), $response->get_error_data() ) );
		} elseif ( ! empty( $response->error ) ) {
			if ( isset( $response->error->message, $response->error->data ) ) {
				SP_Sync_Meta()->log( new WP_Error( 'error', gmdate( '[Y-m-d H:i:s] ' ) . $response->error->message, $response->error->data ) );
			} elseif ( isset( $response->error->reason ) ) {
				SP_Sync_Meta()->log( new WP_Error( 'error', gmdate( '[Y-m-d H:i:s] ' ) . $response->error->reason ) );
			} else {
				SP_Sync_Meta()->log( new WP_Error( 'error', gmdate( '[Y-m-d H:i:s] ' ) . wp_json_encode( $response->error ) ) );
			}
		} elseif ( ! in_array( intval( SP_API()->last_request['response_code'] ), $allowed_codes, true ) ) {
			// translators: date, status code, JSON-encoded last request object.
			SP_Sync_Meta()->log( new WP_Error( 'error', sprintf( __( '[%1$s] Elasticsearch response failed! Status code %2$d; %3$s', 'searchpress' ), gmdate( 'Y-m-d H:i:s' ), SP_API()->last_request['response_code'], wp_json_encode( SP_API()->last_request ) ) ) );
		} elseif ( ! is_object( $response ) ) {
			// translators: date, JSON-encoded API response.
			SP_Sync_Meta()->log( new WP_Error( 'error', sprintf( __( '[%1$s] Unexpected response from Elasticsearch: %2$s', 'searchpress' ), gmdate( 'Y-m-d H:i:s' ), wp_json_encode( $response ) ) ) );
		} else {
			return false;
		}
		return true;
	}

	/**
	 * Get all the posts in a given range.
	 *
	 * @param int $start Starting value to use for 'offset' parameter in query.
	 * @param int $limit Limit value to use for 'posts_per_page' parameter in query.
	 * @return array Array of found posts.
	 */
	public function get_range( $start, $limit ) {
		return $this->get_posts(
			array(
				'offset'         => $start,
				'posts_per_page' => $limit,
			)
		);
	}

	/**
	 * Get posts to loop through
	 *
	 * @param array $args Arguments passed to get_posts.
	 * @access public
	 * @return array
	 */
	public function get_posts( $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'post_status'         => null,
				'post_type'           => null,
				'orderby'             => 'ID',
				'order'               => 'ASC',
				'suppress_filters'    => true, // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.SuppressFiltersTrue
				'ignore_sticky_posts' => true,
			)
		);

		if ( empty( $args['post_type'] ) ) {
			$args['post_type'] = SP_Config()->sync_post_types();
		}

		if ( empty( $args['post_status'] ) ) {
			$args['post_status'] = SP_Config()->sync_statuses();
		}

		$args = apply_filters( 'searchpress_index_loop_args', $args );

		$query = new WP_Query();
		$posts = $query->query( $args );

		do_action( 'sp_debug', '[SP_Sync_Manager] Queried Posts', $args );

		$this->published_posts = $query->found_posts;

		$indexed_posts = array();
		foreach ( $posts as $post ) {
			$indexed_posts[ $post->ID ] = new SP_Post( $post );
		}
		do_action( 'sp_debug', '[SP_Sync_Manager] Converted Posts' );

		return $indexed_posts;
	}

	/**
	 * Do an indexing loop. This is the meat of the process.
	 *
	 * @return bool
	 */
	public function do_index_loop() {
		/**
		 * Action hook that fires before the index loop starts.
		 *
		 * Provides an opportunity to unhook actions that are incompatible with
		 * the index loop.
		 *
		 * @since 0.3.0
		 */
		do_action( 'sp_pre_index_loop' );

		$sync_meta = SP_Sync_Meta();

		$start = $sync_meta->page * $sync_meta->bulk;
		do_action( 'sp_debug', '[SP_Sync_Manager] Getting Range' );
		$posts = $this->get_range( $start, $sync_meta->bulk );
		// Reload the sync meta to ensure it hasn't been canceled while we were getting those posts.
		$sync_meta->reload();

		if ( ! $posts || is_wp_error( $posts ) || ! $sync_meta->running ) {
			return false;
		}

		$response = SP_API()->index_posts( $posts );
		do_action( 'sp_debug', sprintf( '[SP_Sync_Manager] Indexed %d Posts', count( $posts ) ), $response );

		$sync_meta->reload();
		if ( ! $sync_meta->running ) {
			return false;
		}

		$sync_meta->processed += count( $posts );

		if ( 200 !== intval( SP_API()->last_request['response_code'] ) ) {
			// Should probably throw an error here or something.
			$sync_meta->log( new WP_Error( 'error', __( 'ES response failed', 'searchpress' ), SP_API()->last_request ) );
			$sync_meta->save();
			$this->cancel_reindex();
			return false;
		} elseif ( ! is_object( $response ) || ! isset( $response->items ) || ! is_array( $response->items ) ) {
			$sync_meta->log( new WP_Error( 'error', __( 'Error indexing data', 'searchpress' ), $response ) );
			$sync_meta->save();
			$this->cancel_reindex();
			return false;
		} else {
			foreach ( $response->items as $post ) {
				// Status should be 200 or 201, depending on if we're updating or creating respectively.
				if ( ! isset( $post->index->status ) ) {
					// translators: post ID, JSON-encoded API response.
					$sync_meta->log( new WP_Error( 'warning', sprintf( __( 'Error indexing post %1$s; Response: %2$s', 'searchpress' ), $post->index->_id, wp_json_encode( $post ) ), $post ) );
				} elseif ( ! in_array( intval( $post->index->status ), array( 200, 201 ), true ) ) {
					// translators: post ID, HTTP response code.
					$sync_meta->log( new WP_Error( 'warning', sprintf( __( 'Error indexing post %1$s; HTTP response code: %2$s', 'searchpress' ), $post->index->_id, $post->index->status ), $post ) );
				} else {
					$sync_meta->success++;
				}
			}
		}
		$total_pages = ceil( $this->published_posts / $sync_meta->bulk );
		$sync_meta->page++;

		if ( wp_doing_cron() || ! defined( 'WP_CLI' ) || ! WP_CLI ) {
			if ( $sync_meta->processed >= $sync_meta->total || $sync_meta->page > $total_pages ) {
				SP_Config()->update_settings( array( 'active' => true ) );
				$this->cancel_reindex();
			} else {
				$sync_meta->save();
			}
		}
		return true;
	}

	/**
	 * Initialize a cron reindexing.
	 */
	public function do_cron_reindex() {
		SP_Sync_Meta()->start();
		SP_Sync_Meta()->total = $this->count_posts();
		SP_Sync_Meta()->save();
		SP_Cron()->schedule_reindex();
	}

	/**
	 * Cancel reindexing.
	 */
	public function cancel_reindex() {
		SP_Cron()->cancel_reindex();
	}

	/**
	 * Count the posts to index.
	 *
	 * @param  array $args WP_Query args used for counting.
	 * @return int Total number of posts to index.
	 */
	public function count_posts( $args = array() ) {
		if ( false === $this->published_posts ) {
			$args = wp_parse_args(
				$args,
				array(
					'post_type'      => null,
					'post_status'    => null,
					'posts_per_page' => 1,
				)
			);
			if ( empty( $args['post_type'] ) ) {
				$args['post_type'] = SP_Config()->sync_post_types();
			}
			if ( empty( $args['post_status'] ) ) {
				$args['post_status'] = SP_Config()->sync_statuses();
			}

			$args = apply_filters( 'searchpress_index_count_args', $args );

			$query                 = new WP_Query( $args );
			$this->published_posts = intval( $query->found_posts );
		}
		return $this->published_posts;
	}

	/**
	 * Get the number of posts indexed in Elasticsearch.
	 *
	 * @return int
	 */
	public function count_posts_indexed() {
		$count = SP_API()->get( SP_API()->get_doc_type() . '/_count' );
		return ! empty( $count->count ) ? intval( $count->count ) : 0;
	}

	/**
	 * Update the index from the queue.
	 *
	 * @todo  if post should not be added, it's deleted (to account for unpublishing, etc). Make that more elegant.
	 * @todo  abort if we're currently indexing?
	 * @todo  try again on errors, perhaps up to 3 times?
	 * @todo  store sp index version and date in post meta?
	 */
	public function update_index_from_queue() {
		global $wpdb;

		$sync_meta = SP_Sync_Meta();

		// phpcs:disable WordPress.DB.DirectDatabaseQuery
		$post_ids = $wpdb->get_col( "SELECT SQL_CALC_FOUND_ROWS `post_id` FROM {$wpdb->postmeta} WHERE `meta_key`='_sp_index' LIMIT 500" ); // WPCS: cache ok.
		$total    = $wpdb->get_var( 'SELECT FOUND_ROWS()' );
		// phpcs:enable

		if ( ! empty( $post_ids ) ) {
			$posts    = $this->get_posts(
				array(
					'post__in'       => $post_ids,
					'posts_per_page' => count( $post_ids ),
				)
			);
			$response = SP_API()->index_posts( $posts );

			do_action( 'sp_debug', sprintf( '[SP_Sync_Manager] Indexed %d Posts', count( $posts ) ), $response );

			if ( 200 !== (int) SP_API()->last_request['response_code'] ) {
				$sync_meta->log( new WP_Error( 'error', __( 'ES response failed', 'searchpress' ), SP_API()->last_request ) );
				$sync_meta->save();
			} elseif ( ! is_object( $response ) || ! isset( $response->items ) || ! is_array( $response->items ) ) {
				$sync_meta->log( new WP_Error( 'error', __( 'Error indexing data', 'searchpress' ), $response ) );
				$sync_meta->save();
			} else {
				foreach ( $response->items as $post ) {
					// Status should be 200 or 201, depending on if we're updating or creating respectively.
					if ( ! isset( $post->index->status ) ) {
						$sync_meta->log(
							new WP_Error(
								'warning',
								sprintf(
									// translators: 1: Post ID, 2: API response.
									__( 'Error indexing post %1$s; Response: %2$s', 'searchpress' ),
									$post->index->_id,
									wp_json_encode( $post )
								),
								$post
							)
						);
					} elseif ( ! in_array( $post->index->status, array( 200, 201 ), true ) ) {
						$sync_meta->log(
							new WP_Error(
								'warning',
								sprintf(
									// translators: 1: Post ID, 2: response cpde.
									__( 'Error indexing post %1$s; HTTP response code: %2$s', 'searchpress' ),
									$post->index->_id,
									$post->index->status
								),
								$post
							)
						);
					} else { // Success!
						delete_post_meta( $post->index->_id, '_sp_index', '1' );
					}
				}
			}

			if ( $total > count( $post_ids ) ) {
				SP_Cron()->schedule_queue_index();
			}
		}

		// Get the posts to delete.
		$delete_post_ids = get_option( 'sp_delete' );
		if ( ! empty( $delete_post_ids ) && is_array( $delete_post_ids ) ) {

			foreach ( $delete_post_ids as $delete_post_id ) {
				// This is excessive, figure out a better way around it.
				$response = SP_API()->delete_post( $delete_post_id );

				// We're OK with 404 responses here because a post might not be in the index.
				if ( ! $this->parse_error( $response, array( 200, 404 ) ) ) {
					do_action( 'sp_debug', '[SP_Sync_Manager] Deleted Post', $response );
				} else {
					do_action( 'sp_debug', '[SP_Sync_Manager] Error Deleting Post', $response );
				}
			}
		}

		delete_option( 'sp_delete' );
	}
}

/**
 * Returns an initialized instance of the SP_Sync_Manager class.
 *
 * @return SP_Sync_Manager An initialized instance of the SP_Sync_Manager class.
 */
function SP_Sync_Manager() { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.FunctionNameInvalid
	return SP_Sync_Manager::instance();
}
add_action( 'after_setup_theme', 'SP_Sync_Manager' );
