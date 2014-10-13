<?php

/**
 * Lub dub. Lub dub.
 */

if ( !class_exists( 'SP_Heartbeat' ) ) :

class SP_Heartbeat {

	/**
	 * Singleton instance
	 * @var object
	 */
	private static $instance;

	/**
	 * What cluster status do we consider successful? Default is 'yellow'.
	 * @var string
	 */
	public $healthy_status = 'yellow';

	/**
	 * Store the intervals at which the heartbeat gets scheduled.
	 * @var array
	 */
	public $intervals = array();

	/**
	 * Store the threshholds that we compare against our last heartbeat.
	 * @var array
	 */
	public $thresholds = array();

	/**
	 * The action that the cron fires.
	 * @access protected
	 * @var string
	 */
	protected $cron_event = 'sp_heartbeat';

	/**
	 * Cached result of the heartbeat check.
	 * @access protected
	 * @var boolean
	 */
	protected $beat_result;

	/**
	 * Cached result of the time of last heartbeat.
	 * @var int
	 */
	protected $last_beat;

	private function __construct() {
		/* Don't do anything, needs to be initialized via instance() method */
	}

	public function __clone() { wp_die( "Please don't __clone SP_Heartbeat" ); }

	public function __wakeup() { wp_die( "Please don't __wakeup SP_Heartbeat" ); }

	public static function instance() {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new SP_Heartbeat;
			self::$instance->setup();
		}
		return self::$instance;
	}

	/**
	 * Setup the singleton.
	 */
	public function setup() {
		$this->intervals = array(
			'heartbeat' => 5 * MINUTE_IN_SECONDS,
			'increase'  => MINUTE_IN_SECONDS,
		);

		$this->thresholds = array(
			'alert'     => 8 * MINUTE_IN_SECONDS,
			'notify'    => 15 * MINUTE_IN_SECONDS,
			'shutdown'  => 15 * MINUTE_IN_SECONDS,
		);

		$this->maybe_schedule_cron();
		add_filter( 'sp_ready', array( $this, 'is_ready' ) );
		add_action( $this->cron_event, array( $this, 'check_beat' ) );
	}

	/**
	 * Check the status from Elasticsearch.
	 *
	 * @param  boolean $force Optional. If true, bypasses local cache and
	 *                        re-checks the heartbeat from ES.
	 * @return bool true on success or false on failure.
	 */
	public function check_beat( $force = false ) {
		// Ensure we only check the beat once per request
		if ( $force || ! isset( $this->beat_result ) ) {
			$health = SP_API()->cluster_health();
			$this->beat_result = ( ! empty( $health->status ) && $health->status == $this->healthy_status );
			if ( $this->beat_result ) {
				$this->record_pulse();
			} else {
				$this->call_nurse();
			}
		}

		return $this->beat_result;
	}

	/**
	 * Get the last recorded beat.
	 *
	 * @param  boolean $force Optional. If true, bypass the local cache and
	 *                        get the value from the option.
	 * @return int The time of the last beat, or 0 if one has not been recorded.
	 */
	public function get_last_beat( $force = false ) {
		if ( $force || ! isset( $this->last_beat ) ) {
			$this->last_beat = intval( get_option( 'sp_heartbeat' ) );
		}
		return $this->last_beat;
	}

	/**
	 * Record that we missed a beat. Presently, this means rescheduling the cron
	 * at the 'increase' checkin rate.
	 */
	protected function call_nurse() {
		$this->reschedule_cron( 'increase' );
	}

	/**
	 * Check if SearchPress has a pulse within the provided threshold.
	 *
	 * @param  string  $threshold Optional. One of SP_Heartbeat::thresholds.
	 *                            Defaults to 'shutdown'.
	 * @return boolean
	 */
	public function has_pulse( $threshold = 'shutdown' ) {
		$last_beat = $this->get_last_beat();
		if ( time() - $last_beat < $this->thresholds[ $threshold ] ) {
			return true;
		} else {
			$result = $this->check_beat();
			// If we updated the pulse, run this again
			if ( $last_beat != $this->get_last_beat() ) {
				return $this->has_pulse( $threshold );
			}
		}
		return false;
	}

	/**
	 * Is SearchPress ready for requests? This is added to the `sp_ready`
	 * filter.
	 *
	 * @param  bool|null $ready Value passed from other methods.
	 * @return boolean
	 */
	public function is_ready( $ready ) {
		if ( false === $ready ) {
			return $ready;
		}

		return SP_Config()->active() && $this->has_pulse();
	}

	/**
	 * Record a successful heartbeat.
	 */
	public function record_pulse() {
		$this->last_beat = time();
		update_option( 'sp_heartbeat', $this->last_beat );
		$this->reschedule_cron();
	}

	/**
	 * If no heartbeat is scheduled, schedule the default one.
	 */
	protected function maybe_schedule_cron() {
		if ( ! wp_next_scheduled( $this->cron_event ) ) {
			wp_schedule_single_event( time() + $this->intervals['heartbeat'], $this->cron_event );
		}
	}

	/**
	 * Reschedules the cron event at the given interval from now.
	 * @param  string $interval Time from now to schedule the heartbeat.
	 *                          possible values are in SP_Heartbeat::intervals.
	 */
	protected function reschedule_cron( $interval = 'heartbeat' ) {
		wp_clear_scheduled_hook( $this->cron_event );
		wp_schedule_single_event( time() + $this->intervals[ $interval ], $this->cron_event );
	}

	/**
	 * Get the current heartbeat status.
	 *
	 * @return string Possible values are 'never', 'alert', 'shutdown', and 'ok'.
	 */
	public function get_status() {
		$last_beat = $this->get_last_beat();
		if ( ! $last_beat ) {
			return 'never';
		}
		$diff = time() - $last_beat;
		if ( $diff < $this->thresholds['alert'] ) {
			return 'ok';
		} elseif ( $diff < $this->thresholds['shutdown'] ) {
			return 'alert';
		} else {
			return 'shutdown';
		}
	}
}

function SP_Heartbeat() {
	return SP_Heartbeat::instance();
}
add_action( 'after_setup_theme', 'SP_Heartbeat' );

endif;