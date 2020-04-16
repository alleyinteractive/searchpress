<?php
/**
 * SearchPress library: SP_Indexable abstract class
 *
 * @package SearchPress
 */

/**
 * An abstract class for objects which will be indexed in ES.
 */
abstract class SP_Indexable {
	/**
	 * Hold the ID for this object for reference.
	 *
	 * @var int
	 */
	protected $id;

	/**
	 * Set the token length limit, used by string limiting functions. Defaults
	 * to 1k, but you can alter this to your preference by calling e.g.
	 * `SP_Indexable::$token_size_limit = 255;`
	 *
	 * @var integer
	 */
	public static $token_size_limit = 1024;

	/**
	 * Convert the object to JSON.
	 *
	 * @return string JSON data.
	 */
	abstract public function to_json();

	/**
	 * Should this object be indexed in ES?
	 *
	 * @return bool True for yes, false for no.
	 */
	abstract public function should_be_indexed();

	/**
	 * Split the meta values into different types for meta query casting.
	 *
	 * @param string $value Meta value.
	 * @param array  $types Data types to cast to, e.g. ['value', 'long'].
	 * @return array
	 */
	public static function cast_meta_types( $value, $types = array() ) {
		// Ensure value is scalar before attempting to type cast it.
		if ( isset( $value ) && ! is_scalar( $value ) ) {
			return array();
		}

		$types  = array_fill_keys( $types, true );
		$return = array(
			'raw' => isset( $value ) ? self::limit_string( (string) $value ) : null,
		);

		if ( isset( $types['value'] ) ) {
			$return['value'] = isset( $value )
				? self::limit_word_length( (string) $value )
				: null;
		}
		if ( isset( $types['long'] ) && is_numeric( $value ) && $value <= PHP_INT_MAX ) {
			$return['long'] = (int) $value;
			if ( ! is_finite( $return['long'] ) ) {
				unset( $return['long'] );
			}
		}
		if ( isset( $types['double'] ) && is_numeric( $value ) ) {
			$return['double'] = (float) $value;
			if ( ! is_finite( $return['double'] ) ) {
				unset( $return['double'] );
			}
		}
		if ( isset( $types['boolean'] ) ) {
			// Correct boolean values.
			if ( is_string( $value ) && 'false' === strtolower( $value ) ) {
				$return['boolean'] = false;
			} else {
				$return['boolean'] = (bool) $value;
			}
		}
		if (
			( isset( $types['date'] ) || isset( $types['datetime'] ) || isset( $types['time'] ) )
			&& strlen( $value ) <= 255 // Limit date/time strings to 255 chars for performance.
		) {
			$time = false;
			$int  = (int) $value;

			// Check to see if this is a timestamp.
			if ( (string) $int === (string) $value ) {
				$time = $int;
			} elseif ( ! is_numeric( $value ) ) {
				$time = strtotime( $value );
			}

			if ( false !== $time ) {
				if ( isset( $types['date'] ) ) {
					$return['date'] = gmdate( 'Y-m-d', $time );
				}
				if ( isset( $types['datetime'] ) ) {
					$return['datetime'] = gmdate( 'Y-m-d H:i:s', $time );
				}
				if ( isset( $types['time'] ) ) {
					$return['time'] = gmdate( 'H:i:s', $time );
				}
			}
		}

		return $return;
	}

	/**
	 * Parse out the properties of a date.
	 *
	 * @param  string $date  A date, expected to be in mysql format.
	 * @return array The parsed date.
	 */
	public static function get_date( $date ) {
		if ( empty( $date ) || '0000-00-00 00:00:00' === $date ) {
			return null;
		}

		$ts = strtotime( $date );
		return array(
			'date'              => strval( $date ),
			'year'              => intval( gmdate( 'Y', $ts ) ),
			'month'             => intval( gmdate( 'm', $ts ) ),
			'day'               => intval( gmdate( 'd', $ts ) ),
			'hour'              => intval( gmdate( 'H', $ts ) ),
			'minute'            => intval( gmdate( 'i', $ts ) ),
			'second'            => intval( gmdate( 's', $ts ) ),
			'week'              => intval( gmdate( 'W', $ts ) ),
			'day_of_week'       => intval( gmdate( 'N', $ts ) ),
			'day_of_year'       => intval( gmdate( 'z', $ts ) ),
			'seconds_from_day'  => intval( mktime( gmdate( 'H', $ts ), gmdate( 'i', $ts ), gmdate( 's', $ts ), 1, 1, 1970 ) ),
			'seconds_from_hour' => intval( mktime( 0, gmdate( 'i', $ts ), gmdate( 's', $ts ), 1, 1, 1970 ) ),
		);
	}

	/**
	 * Limit a string in length.
	 *
	 * It's important to keep some strings limited in length, because ES will
	 * choke on tokens greater than 32k.
	 *
	 * @static
	 *
	 * @param  string  $string String to (maybe) truncate.
	 * @param  integer $length Length to which to truncate. Defaults to
	 *                         SP_Indexable::$token_size_limit.
	 * @return string
	 */
	public static function limit_string( $string, $length = null ) {
		if ( is_null( $length ) ) {
			$length = self::$token_size_limit;
		}

		return mb_substr( $string, 0, $length );
	}

	/**
	 * Limit word length in a string.
	 *
	 * As noted in {@see limit_string()}, ES chokes on tokens longer than 32k.
	 * This method helps keep words under that limit, which should rarely come
	 * up. If a string does have a word that is great than {$length}, then the
	 * entire string is modified to insert a space between words every {$length}
	 * characters (or sooner, to only insert at word boundaries). Modifying the
	 * string is not ideal, but this is a significant edge case.
	 *
	 * @static
	 *
	 * @param  string  $string String to limit.
	 * @param  integer $length Max token length of tokens. Defaults to
	 *                         SP_Indexable::$token_size_limit.
	 * @return string
	 */
	public static function limit_word_length( $string, $length = null ) {
		if ( is_null( $length ) ) {
			$length = self::$token_size_limit;
		}

		// Only modify the string if it's going to cause issues.
		if ( preg_match( '/[^\s]{' . absint( $length ) . '}/', $string ) ) {
			return wordwrap( $string, $length, ' ', true );
		}

		return $string;
	}
}
