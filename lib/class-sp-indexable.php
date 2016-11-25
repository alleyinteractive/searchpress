<?php

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
	 * @param  string $value Meta value.
	 * @return array
	 */
	public static function cast_meta_types( $value ) {
		$return = array(
			'value'   => $value,
			'raw'     => $value,
			'boolean' => (bool) $value,
		);

		$time = false;
		$double = floatval( $value );
		if ( is_numeric( $value ) && is_finite( $double ) ) {
			$int = intval( $value );
			$return['long']   = $int;
			$return['double'] = $double;

			// If this is an integer (represented as a string), check to see if
			// it is a valid timestamp
			if ( (string) $int === (string) $value ) {
				$year = intval( date( 'Y', $int ) );
				// Ensure that the year is between 1-2038. Technically, the year
				// range ES allows is 1-292278993, but PHP ints limit us to 2038.
				if ( $year > 0 && $year < 2039 ) {
					$time = $int;
				}
			}
		} elseif ( is_string( $value ) ) {
			$return['value'] = self::limit_word_length( $value );
			$return['raw']   = self::limit_string( $value );

			// correct boolean values
			if ( 'false' === strtolower( $value ) ) {
				$return['boolean'] = false;
			} elseif ( 'true' === strtolower( $value ) ) {
				$return['boolean'] = true;
			}

			// add date/time if we have it.
			$time = strtotime( $value );
		}

		if ( false !== $time ) {
			$return['date']     = date( 'Y-m-d', $time );
			$return['datetime'] = date( 'Y-m-d H:i:s', $time );
			$return['time']     = date( 'H:i:s', $time );
		}

		return $return;
	}

	/**
	 * Parse out the properties of a date.
	 *
	 * @param  string $date  A date, expected to be in mysql format.
	 * @return array The parsed date.
	 */
	public function get_date( $date ) {
		if ( empty( $date ) || '0000-00-00 00:00:00' == $date ) {
			return null;
		}

		$ts = strtotime( $date );
		return array(
			'date'              => strval( $date ),
			'year'              => intval( date( 'Y', $ts ) ),
			'month'             => intval( date( 'm', $ts ) ),
			'day'               => intval( date( 'd', $ts ) ),
			'hour'              => intval( date( 'H', $ts ) ),
			'minute'            => intval( date( 'i', $ts ) ),
			'second'            => intval( date( 's', $ts ) ),
			'week'              => intval( date( 'W', $ts ) ),
			'day_of_week'       => intval( date( 'N', $ts ) ),
			'day_of_year'       => intval( date( 'z', $ts ) ),
			'seconds_from_day'  => intval( mktime( date( 'H', $ts ), date( 'i', $ts ), date( 's', $ts ), 1, 1, 1970 ) ),
			'seconds_from_hour' => intval( mktime( 0, date( 'i', $ts ), date( 's', $ts ), 1, 1, 1970 ) ),
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
