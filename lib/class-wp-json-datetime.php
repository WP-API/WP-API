<?php
/**
 * DateTime compatibility class
 *
 * @package WordPress
 * @subpackage JSON API
 */
class WP_JSON_DateTime extends DateTime {
	/**
	 * Workaround for DateTime::createFromFormat on PHP > 5.2
	 *
	 * @link http://stackoverflow.com/a/17084893/717643
	 *
	 * @param  string       $format   The format that the passed in string should be in.
	 * @param  string       $string   String representing the time.
	 * @param  DateTimeZone $timezone A DateTimeZone object representing the desired time zone.
	 * @return Datetime
	 */
	public static function createFromFormat( $format, $time, $timezone = null ) {
		if ( is_null( $timezone ) ) {
			$timezone = new DateTimeZone( date_default_timezone_get() );
		}

		if ( method_exists( 'DateTime', 'createFromFormat' ) ) {
			return parent::createFromFormat( $format, $time, $timezone );
		}

		return new DateTime( date( $format, strtotime( $time ) ), $timezone );
	}
}
