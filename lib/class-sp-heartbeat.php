<?php
/**
 * SearchPress library: SP_Heartbeat class
 *
 * @package SearchPress
 */

/**
 * Lub dub. Lub dub.
 */
class SP_Heartbeat extends SP_Singleton {

	/**
	 * What cluster statuses do we consider successful? Default is ['yellow', 'green'].
	 *
	 * @var array<string>
	 */
	public array $healthy_statuses = [ 'yellow', 'green' ];

	/**
	 * Store the intervals at which the heartbeat gets scheduled.
	 *
	 * @var array<string, int>
	 */
	public array $intervals = [];

	/**
	 * Store the thresholds that we compare against our last heartbeat.
	 *
	 * @var array<string, int>
	 */
	public array $thresholds = [];

	/**
	 * The action that the cron fires.
	 *
	 * @access protected
	 * @var string
	 */
	protected string $cron_event = 'sp_heartbeat';

	/**
	 * Cached result of the heartbeat check.
	 *
	 * @var null|'invalid'|'red'|'yellow'|'green'
	 */
	public ?string $beat_result;

	/**
	 * Cached result of the time of last heartbeat.
	 *
	 * @var array<{ queried: int, verified: int }>
	 */
	protected array $last_beat;

	/**
	 * Set up the singleton.
	 *
	 * @codeCoverageIgnore
	 */
	public function setup(): void {
		$this->intervals = [
			'heartbeat' => 5 * MINUTE_IN_SECONDS,
			'increase'  => MINUTE_IN_SECONDS,
			'stale'     => 10 * MINUTE_IN_SECONDS,
		];

		$this->thresholds = [
			'alert'    => 8 * MINUTE_IN_SECONDS,
			'notify'   => 15 * MINUTE_IN_SECONDS,
			'shutdown' => 15 * MINUTE_IN_SECONDS,
		];

		$this->maybe_schedule_cron();
		add_filter( 'sp_ready', array( $this, 'is_ready' ) );
		add_action( $this->cron_event, array( $this, 'check_beat' ) );
	}

	/**
	 * Check the status from Elasticsearch.
	 *
	 * @param bool $force Optional. If true, bypasses local cache and re-checks the heartbeat from ES.
	 * @return bool true on success or false on failure.
	 */
	public function check_beat( bool $force = false ): bool {
		// Ensure we only check the beat once per request.
		$checked = false;
		if ( $force || ! isset( $this->beat_result ) ) {
			$health            = SP_API()->cluster_health();
			$this->beat_result = ! empty( $health->status ) ? $health->status : 'invalid';
			$checked           = true;
		}

		// Verify the beat is healthy.
		$has_healthy_beat = in_array( $this->beat_result, $this->healthy_statuses, true );

		if ( $checked ) {
			if ( $has_healthy_beat ) {
				$this->record_pulse();
			} else {
				$this->record_pulse( $this->last_seen() );
				$this->call_nurse();
			}
		}

		return $has_healthy_beat;
	}

	/**
	 * Get the last recorded beat.
	 *
	 * @param  bool $force Optional. If true, bypass the local cache and get the value from the option.
	 * @return array<{queried: int, verified: int}> The times the last time the beat was queried and verified.
	 */
	public function get_last_beat( bool $force = false ): array {
		if ( $force || ! isset( $this->last_beat ) ) {
			$beat = get_option( 'sp_heartbeat' );
			if ( is_array( $beat ) ) {
				$this->last_beat = $beat;
			} elseif ( is_numeric( $beat ) ) {
				// The heartbeat needs to be migrated from the old format.
				$this->record_pulse( $beat, $beat );
			} else {
				// No heartbeat, zero out the heartbeat.
				$this->last_beat = [
					'queried'  => 0,
					'verified' => 0,
				];
			}
		}
		return $this->last_beat;
	}

	/**
	 * Get the last time the heartbeat was verified.
	 *
	 * @return int
	 */
	public function last_seen(): int {
		return $this->get_last_beat()['verified'];
	}

	/**
	 * Record that we missed a beat. Presently, this means rescheduling the cron
	 * at the 'increase' checkin rate.
	 */
	protected function call_nurse(): void {
		$this->reschedule_cron( 'increase' );
	}

	/**
	 * Check if the heartbeat is below the given threshold.
	 *
	 * @param string   $threshold   One of SP_Heartbeat::thresholds.
	 * @param int|null $compared_to Optional. Time to which to compare. Defaults to now.
	 * @return bool
	 */
	protected function is_heartbeat_below_threshold( string $threshold, ?int $compared_to = null ): bool {
		return ( $compared_to ?? time() ) - $this->last_beat['verified'] < $this->thresholds[ $threshold ];
	}

	/**
	 * Check if the heartbeat is stale (has not been checked recently).
	 *
	 * @return bool
	 */
	protected function is_heartbeat_stale(): bool {
		return time() - $this->last_beat['queried'] > $this->intervals['stale'];
	}

	/**
	 * Check if SearchPress has a pulse within the provided threshold.
	 *
	 * @param string $threshold Optional. One of SP_Heartbeat::thresholds. Defaults to 'shutdown'.
	 * @return bool
	 */
	public function has_pulse( string $threshold = 'shutdown' ): bool {
		if ( $this->is_heartbeat_below_threshold( $threshold ) ) {
//			var_dump(wp_debug_backtrace_summary());
//			die('here');
			return true;
		} elseif ( is_admin() ) {
			// There's no heartbeat, but this is an admin request, so query it now.
			$this->check_beat();
			return $this->is_heartbeat_below_threshold( $threshold );
		} elseif ( $this->is_heartbeat_stale() ) {
			// If the heartbeat is stale, check the last known status.
			return $this->is_heartbeat_below_threshold( $threshold, $this->get_last_beat()['queried'] );
		}

		return false;
	}

	/**
	 * Is SearchPress ready for requests? This is added to the `sp_ready`
	 * filter.
	 *
	 * @param bool|null $ready Value passed from other methods.
	 * @return bool
	 */
	public function is_ready( ?bool $ready ): bool {
		if ( false === $ready ) {
			return false;
		}

		return SP_Config()->active() && $this->has_pulse();
	}

	/**
	 * Record a successful heartbeat.
	 *
	 * @param int|null $verified Optional. The time of the last successful heartbeat response.
	 * @param int|null $queried Optional. The time of the last heartbeat request.
	 */
	public function record_pulse( ?int $verified = null, ?int $queried = null ): void {
		$this->last_beat = [
			'queried'  => $queried ?? time(),
			'verified' => $verified ?? time(),
		];
		update_option( 'sp_heartbeat', $this->last_beat );
		$this->reschedule_cron();
	}

	/**
	 * If no heartbeat is scheduled, schedule the default one.
	 *
	 * @codeCoverageIgnore
	 */
	protected function maybe_schedule_cron(): void {
		if ( ! wp_next_scheduled( $this->cron_event ) ) {
			wp_schedule_single_event( time() + $this->intervals['heartbeat'], $this->cron_event );
		}
	}

	/**
	 * Reschedules the cron event at the given interval from now.
	 *
	 * @param string $interval Time from now to schedule the heartbeat.
	 *                          possible values are in SP_Heartbeat::intervals.
	 */
	protected function reschedule_cron( string $interval = 'heartbeat' ): void {
		wp_clear_scheduled_hook( $this->cron_event );
		wp_schedule_single_event( time() + $this->intervals[ $interval ], $this->cron_event );
	}

	/**
	 * Get the current heartbeat status.
	 *
	 * @return 'never'|'alert'|'shutdown'|'ok'|'stale'
	 */
	public function get_status(): string {
		if ( ! $this->last_seen() ) {
			return 'never';
		} elseif ( $this->is_heartbeat_stale() ) {
			return 'stale';
		} elseif ( $this->is_heartbeat_below_threshold( 'alert' ) ) {
			return 'ok';
		} elseif ( $this->is_heartbeat_below_threshold( 'shutdown' ) ) {
			return 'alert';
		} else {
			return 'shutdown';
		}
	}
}

/**
 * Initializes and returns the instance of the SP_Heartbeat class.
 *
 * @return SP_Heartbeat The initialized instance of the SP_Heartbeat class.
 */
function SP_Heartbeat(): SP_Heartbeat { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.FunctionNameInvalid
	return SP_Heartbeat::instance();
}
add_action( 'after_setup_theme', 'SP_Heartbeat', 20 );
