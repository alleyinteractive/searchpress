<?php

/**
 * Lub dub. Lub dub.
 */

if ( !class_exists( 'SP_Heartbeat' ) ) :

class SP_Heartbeat {

	private static $instance;

	public $healthy_status = 'yellow';

	public $intervals = array();

	public $thresholds = array();

	protected $cron_event = 'sp_heartbeat';

	protected $beat_result;

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

	public function check_beat() {
		// Ensure we only check the beat once per request
		if ( ! isset( $this->beat_result ) ) {
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

	public function get_last_beat() {
		if ( ! isset( $this->last_beat ) ) {
			$this->last_beat = intval( get_option( 'sp_heartbeat' ) );
		}
		return $this->last_beat;
	}

	protected function call_nurse() {
		$this->reschedule_cron( 'increase' );
	}

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
	}

	public function is_ready( $ready ) {
		if ( false === $ready ) {
			return $ready;
		}

		return SP_Config()->active() && $this->has_pulse( 'shutdown' );
	}

	public function record_pulse() {
		$this->last_beat = time();
		update_option( 'sp_heartbeat', $this->last_beat );
		$this->reschedule_cron();
	}

	protected function maybe_schedule_cron() {
		if ( ! wp_next_scheduled( $this->cron_event ) ) {
			wp_schedule_single_event( time() + $this->intervals['heartbeat'], $this->cron_event );
		}
	}

	protected function reschedule_cron( $interval = 'heartbeat' ) {
		wp_clear_scheduled_hook( $this->cron_event );
		wp_schedule_single_event( time() + $this->intervals[ $interval ], $this->cron_event );
	}

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