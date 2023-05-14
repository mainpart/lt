<?php

namespace Lt;


class Schedule {

	private static $initiated = false;

	public static function init() {
		if ( ! self::$initiated ) {
			self::$initiated = true;
			if ( ! wp_get_schedule( 'lt_daily_cron' ) ) {
				wp_schedule_event( time(), 'daily', 'lt_daily_cron' );
			}
			// Register actions that should happen on that hook.
			add_action( 'lt_daily_cron', [ self::class, 'process' ] );
		}

	}

	public static function process() {

		$options = get_option( Settings::$option_prefix . '_payments_options' );
		if ( isset( $options['autoupdate'] ) && $options['autoupdate'] == 'yes' ) {

			$response      = wp_remote_get( 'https://www.cbr.ru/scripts/XML_daily.asp', [ 'timeout' => 10 ] );
			$response_body = wp_remote_retrieve_body( $response );

			if ( ! is_wp_error( $response ) ) {
				if ( preg_match( '/<Valute\s*ID="R01239">.*?<Value>([0-9,]+)<\/Value>/sim', $response_body, $matches ) ) {
					$options['convertation'] = (int) ( (int) $matches[1] * 1.13 );
				} else {
					$options['convertation'] = 100;
				}
				update_option(Settings::$option_prefix . '_payments_options', $options);
				//  x   wp_die($options['convertation']);
			}
		}
	}

}
