<?php

/**
 * SearchPress Sync Manager
 *
 * Controls the data sync from WordPress to elasticsearch
 *
 * Reminders and considerations while building this:
 * @todo Trigger massive reindex (potentially) when indexed usermeta is edited
 * @todo Trigger massive reindex when term data is edited
 * @todo Changing permalinks should trigger full reindex?
 *
 * @author Matthew Boynes
 */

class SP_Sync_Manager extends SP_Singleton {

	public $published_posts = false;

	/**
	 * Setup the singleton.
	 */
	public function setup() {
		if ( SP_Config()->active() ) {
			// When posts & attachments get_updated, queue up syncs.
			add_action( 'save_post',       array( $this, 'sync_post' ) );
			add_action( 'edit_attachment', array( $this, 'sync_post' ) );
			add_action( 'add_attachment',  array( $this, 'sync_post' ) );
			add_action( 'deleted_post',    array( $this, 'delete_post' ) );
			add_action( 'trashed_post',    array( $this, 'delete_post' ) );

			// When terms or term relationships get updated, queue up syncs.
			// add_action( 'added_term_relationship',    array( $this, 'sync_post' ) );
			// add_action( 'deleted_term_relationships', array( $this, 'sync_post' ) );

			// When users get updated, queue up syncs for their posts.
			// @TODO
		}
	}

	/**
	 * Prepare a single post to be synced.
	 *
	 * @param int $post_id
	 * @return void
	 */
	public function sync_post( $post_id ) {
		update_post_meta( $post_id, '_sp_index', '1' );
		SP_Cron()->schedule_queue_index();
	}

	/**
	 * Delete a post from the ES index.
	 *
	 * @todo move the update_option to shutdown? if 100 posts are deleted in one
	 *       request, that would reduce the writes from 100 to 1.
	 *
	 * @param  int $post_id
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
	 * @param  object $response SP_API response
	 * @param  array  $allowed_codes Allowed HTTP status codes. Default is array( 200 )
	 * @return bool   True if errors are found, false if successful.
	 */
	protected function parse_error( $response, $allowed_codes = array( 200 ) ) {
		if ( is_wp_error( $response ) ) {
			SP_Sync_Meta()->log( new WP_Error( 'error', date( '[Y-m-d H:i:s] ' ) . $response->get_error_message(), $response->get_error_data() ) );
		} elseif ( ! empty( $response->error ) ) {
			if ( isset( $response->error->message, $response->error->data ) ) {
				SP_Sync_Meta()->log( new WP_Error( 'error', date( '[Y-m-d H:i:s] ' ) . $response->error->message, $response->error->data ) );
			} elseif ( isset( $response->error->reason ) ) {
				SP_Sync_Meta()->log( new WP_Error( 'error', date( '[Y-m-d H:i:s] ' ) . $response->error->reason ) );
			} else {
				SP_Sync_Meta()->log( new WP_Error( 'error', date( '[Y-m-d H:i:s] ' ) . wp_json_encode( $response->error ) ) );
			}
		} elseif ( ! in_array( SP_API()->last_request['response_code'], $allowed_codes ) ) {
			SP_Sync_Meta()->log( new WP_Error( 'error', sprintf( __( '[%1$s] Elasticsearch response failed! Status code %2$d; %3$s', 'searchpress' ), date( 'Y-m-d H:i:s' ), SP_API()->last_request['response_code'], wp_json_encode( SP_API()->last_request ) ) ) );
		} elseif ( ! is_object( $response ) ) {
			SP_Sync_Meta()->log( new WP_Error( 'error', sprintf( __( '[%1$s] Unexpected response from Elasticsearch: %2$s', 'searchpress' ), date( 'Y-m-d H:i:s' ), wp_json_encode( $response ) ) ) );
		} else {
			return false;
		}
		return true;
	}

	/**
	 * Get all the posts in a given range
	 *
	 * @param int $start
	 * @param int $limit
	 * @return string JSON array
	 */
	public function get_range( $start, $limit ) {
		return $this->get_posts( array(
			'offset'         => $start,
			'posts_per_page' => $limit,
		) );
	}

	/**
	 * Get posts to loop through
	 *
	 * @param array $args arguments passed to get_posts
	 * @return array
	 */
	public function get_posts( $args = array() ) {
		$args = wp_parse_args( $args, array(
			'post_status'         => null,
			'post_type'           => null,
			'orderby'             => 'ID',
			'order'               => 'ASC',
			'suppress_filters'    => true,
			'ignore_sticky_posts' => true,
		) );

		if ( empty( $args['post_type'] ) ) {
			$args['post_type'] = SP_Config()->sync_post_types();
		}

		if ( empty( $args['post_status'] ) ) {
			$args['post_status'] = SP_Config()->sync_statuses();
		}

		$args = apply_filters( 'searchpress_index_loop_args', $args );

		$query = new WP_Query;
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
		$sync_meta = SP_Sync_Meta();

		$start = $sync_meta->page * $sync_meta->bulk;
		do_action( 'sp_debug', '[SP_Sync_Manager] Getting Range' );
		$posts = $this->get_range( $start, $sync_meta->bulk );
		// Reload the sync meta to ensure it hasn't been canceled while we were getting those posts
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

		if ( '200' != SP_API()->last_request['response_code'] ) {
			// Should probably throw an error here or something
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
				// Status should be 200 or 201, depending on if we're updating or creating respectively
				if ( ! isset( $post->index->status ) ) {
					$sync_meta->log( new WP_Error( 'warning', sprintf( __( 'Error indexing post %1$s; Response: %2$s', 'searchpress' ), $post->index->_id, wp_json_encode( $post ) ), $post ) );
				} elseif ( ! in_array( $post->index->status, array( 200, 201 ) ) ) {
					$sync_meta->log( new WP_Error( 'warning', sprintf( __( 'Error indexing post %1$s; HTTP response code: %2$s', 'searchpress' ), $post->index->_id, $post->index->status ), $post ) );
				} else {
					$sync_meta->success++;
				}
			}
		}
		$total_pages = ceil( $this->published_posts / $sync_meta->bulk );
		$sync_meta->page++;

		if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
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
	 * @param  array  $args WP_Query args used for counting.
	 * @return int Total number of posts to index.
	 */
	public function count_posts( $args = array() ) {
		if ( false === $this->published_posts ) {
			$args = wp_parse_args( $args, array(
				'post_type' => null,
				'post_status' => null,
				'posts_per_page' => 1,
			) );
			if ( empty( $args['post_type'] ) ) {
				$args['post_type'] = SP_Config()->sync_post_types();
			}
			if ( empty( $args['post_status'] ) ) {
				$args['post_status'] = SP_Config()->sync_statuses();
			}

			$args = apply_filters( 'searchpress_index_count_args', $args );

			$query = new WP_Query( $args );
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
		$count = SP_API()->get( 'post/_count' );
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
		$post_ids = $wpdb->get_col( "SELECT SQL_CALC_FOUND_ROWS `post_id` FROM {$wpdb->postmeta} WHERE `meta_key`='_sp_index' LIMIT 500" );
		$total = $wpdb->get_var( 'SELECT FOUND_ROWS()' );
		if ( ! empty( $post_ids ) ) {
			$posts = $this->get_posts( array(
				'post__in' => $post_ids,
				'posts_per_page' => count( $post_ids ),
			) );
			$response = SP_API()->index_posts( $posts );

			do_action( 'sp_debug', sprintf( '[SP_Sync_Manager] Indexed %d Posts', count( $posts ) ), $response );

			if ( '200' != SP_API()->last_request['response_code'] ) {
				$sync_meta->log( new WP_Error( 'error', __( 'ES response failed', 'searchpress' ), SP_API()->last_request ) );
				$sync_meta->save();
			} elseif ( ! is_object( $response ) || ! isset( $response->items ) || ! is_array( $response->items ) ) {
				$sync_meta->log( new WP_Error( 'error', __( 'Error indexing data', 'searchpress' ), $response ) );
				$sync_meta->save();
			} else {
				foreach ( $response->items as $post ) {
					// Status should be 200 or 201, depending on if we're updating or creating respectively
					if ( ! isset( $post->index->status ) ) {
						$sync_meta->log( new WP_Error( 'warning', sprintf( __( 'Error indexing post %1$s; Response: %2$s', 'searchpress' ), $post->index->_id, json_encode( $post ) ), $post ) );
					} elseif ( ! in_array( $post->index->status, array( 200, 201 ) ) ) {
						$sync_meta->log( new WP_Error( 'warning', sprintf( __( 'Error indexing post %1$s; HTTP response code: %2$s', 'searchpress' ), $post->index->_id, $post->index->status ), $post ) );
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

				// We're OK with 404 responses here because a post might not be in the index
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
 * Get the singleton instance for the SP_Sync_Manager class.
 *
 * @return \SP_Sync_Manager The singleton instance.
 */
function SP_Sync_Manager() {
	return SP_Sync_Manager::instance();
}

// Get the ball rolling!
SP_Sync_Manager();
