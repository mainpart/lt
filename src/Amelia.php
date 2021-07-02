<?php

namespace Lt;


class Amelia {

	private static $initiated = false;

	public static function init() {
		if ( ! self::$initiated ) {
			self::$initiated = true;
			add_action( 'AmeliabookingStatusUpdated', [ self::class, 'process' ] );
			//add_action( 'AmeliabookingAdded', [ self::class, 'added' ] );

		}

	}

	/**
	 * При вызове AmeliabookingAdded забирает права на открытие консультации
	 *
	 * @param $reservation
	 * @param $bookings
	 */
	public static function added( $reservation ) {
		global $wpdb;
		if (Users::user_is('subscriber')) {
			if ( $reservation['status'] == 'pending' ) {
				foreach ( $reservation['bookings'] as $booking ) {
					$user = get_user_by_email( $wpdb->get_var( $wpdb->prepare( "SELECT email from `{$wpdb->prefix}amelia_users` where id = %d ", $booking['customerId'] ) ) );
					if ( ! $user ) {
						continue;
					}
					delete_user_meta( $user->ID, 'paidfrom' );
					delete_user_meta( $user->ID, 'paidto' );
					do_action( 'Lt\PaidChange', $user->ID, null, null );
				}
			}
		}
	}
	/**
	 * При вызове AmeliabookingStatusUpdated если статус букинга перешел в approved
	 * добавляет пользователю 3 дня доступа с момента консультации
	 *
	 * @param $reservation
	 * @param $bookings
	 */
	public static function process( $reservation ) {
		global $wpdb;
		if ( $reservation['status'] == 'approved' ) {
			foreach ( $reservation['bookings'] as $booking ) {
				$user = get_user_by_email( $wpdb->get_var( $wpdb->prepare( "SELECT email from `{$wpdb->prefix}amelia_users` where id = %d ", $booking['customerId'] ) ) );
				if ( ! $user ) {
					continue;
				}
				$start = \DateTime::createFromFormat( 'Y-m-d H:i:s', $booking['bookingStart'] );
				$options = get_option(Settings::$option_prefix . '_plugin_options_timing',true);
				update_user_meta( $user->ID, 'paidfrom', $paidfrom = $start->setTime(0,0,0)->getTimestamp() );
				update_user_meta( $user->ID, 'paidto', $paidto = $start->add( new \DateInterval(

						Settings::parse_date_time(
							$options['approve_timevalue'],
							$options['approve_timespan'],
							'P3D' )

					)


				 )->setTime( 23, 59, 59 )->getTimestamp());
				do_action( 'Lt\PaidChange', $user->ID, $paidfrom, $paidto );
			}
		}
	}
}