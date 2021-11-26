<?php

namespace Lt;

use DateTime;


class Users {

	private static $initiated = false;

	public static function init() {
		if ( ! self::$initiated ) {

			self::$initiated = true;
			add_filter( 'manage_users_custom_column', [ 'Lt\Users', 'new_modify_user_table_row' ], 10, 3 );
			add_filter( 'manage_users_columns', [ 'LT\Users', 'new_modify_user_table' ] );
			add_action( 'wp_ajax_change_userdate_admin_ajax', array( 'Lt\Users', 'consult_change_date_admin_ajax' ) );
			add_action( 'admin_enqueue_scripts', array( 'Lt\Users', 'admin_enqueue_scripts' ), 10, 1 );
			add_action( 'user_register', [ self::class, 'user_register' ] );
			add_action( 'Lt\PaidChange', [ self::class, 'paid_change' ], 10, 3 );
			add_action( 'Lt\Notify', [ self::class, 'notify_user' ] );
			add_action( 'af/form/editing/user_created', [ self::class, 'form_sign_in_user' ], 10, 1 );

		}


	}

	public static function form_sign_in_user( $user ) {
		wp_set_auth_cookie( $user->ID );

		clean_user_cache( $user->ID );
		wp_clear_auth_cookie();
		wp_set_current_user( $user->ID );
		wp_set_auth_cookie( $user->ID );
		update_user_caches( $user );

	}


	/**
	 * @param $user \WP_User
	 * @param $subject string
	 * @param $body string
	 */
	private function send_mail( $user, $subject, $body ) {
		if ( ! $subject || ! $body ) {
			return;
		}
		preg_match_all( '/\{([^}]+)\}/', $body, $regs, PREG_PATTERN_ORDER );
		foreach ( $regs[0] as $idx => $pattern ) {
			$body = str_replace( $pattern, $user->$regs[1][ $idx ], $body );
		}
		// отправляем сообщение о скором завершении подписки
		wp_mail( $user->user_email, $subject, $body );

	}

	/**
	 * Action который вызывается из крона для уведомления пользователя
	 * о начале действия срока его подписки
	 *
	 * @param $user_id
	 */
	function notify_user( $user_id ) {

		$from = (int) get_user_meta( $user_id, 'paidfrom', true );
		$to   = (int) get_user_meta( $user_id, 'paidto', true );

		$nowdt = new \DateTimeImmutable();
		/** @var $duedate1 DateTime - Timestamp предупреждения об окончании подписки */
		$options  = get_option( Settings::$option_prefix . '_plugin_options_timing', true );
		$duedate1 = $nowdt->sub( new \DateInterval( Settings::parse_date_time(

			$options['endsoon_timevalue'],
			$options['endsoon_timespan'],
			'P1D' ) ) )->sub( new \DateInterval( 'PT5S' ) );

		$user = get_user_by( 'ID', $user_id );

		if ( ( $nowdt->getTimestamp() < $duedate1->getTimestamp() ) && ( $nowdt->getTimestamp() > $from ) ) {
			// отправляем сообщение о старте
			$options = get_option( Settings::$option_prefix . '_plugin_options_template' );
			self::send_mail( $user, $options['subject_start'], $options['template_start'] );
		} elseif ( ( $nowdt->getTimestamp() > $duedate1->getTimestamp() ) && ( $nowdt->getTimestamp() < $to ) ) {
			// доступ скоро закончится
			$options = get_option( Settings::$option_prefix . '_plugin_options_template' );
			self::send_mail( $user, $options['subject_soon'], $options['template_soon'] );
		} elseif ( $nowdt->getTimestamp() > $to ) {
			// отправляем сообщение о завершившейся подписке
			$options = get_option( Settings::$option_prefix . '_plugin_options_template' );
			self::send_mail( $user, $options['subject_end'], $options['template_end'] );
		}

	}

	/**
	 * Хук вызывается когда прописывается дата начала и окончания платежа у клиента
	 * Устанавливает вызов функции уведомления об окончании доступа за 1 день и в момент окончания
	 *
	 * @param $paidfrom int timestamp
	 * @param $paidto int timestamp
	 * @param $user_id int
	 */
	function paid_change( $user_id, $paidfrom, $paidto ) {
		// нужно убрать старый крон
		while ( $time_schedule = wp_next_scheduled( 'Lt\Notify', $user_id ) ) {
			wp_unschedule_event( $time_schedule, 'Lt\Notify', $user_id );
		}
		// за три дня до окончания доступа оповещаем пользователя
		if ( $paidfrom ) {
			wp_schedule_single_event( $paidfrom, '\Lt\Notify', $user_id );
		}
		if ( $paidto ) {
			$end     = DateTime::createFromFormat( "U", $paidto );
			$options = get_option( Settings::$option_prefix . '_plugin_options_template', true );
			wp_schedule_single_event( $end->sub( new \DateInterval(
				Settings::parse_date_time(
					$options['endsoon_timevalue'],
					$options['endsoon_timespan'],
					'P1D' )
			) )->getTimestamp(), 'Lt\Notify', $user_id );
			// в час окончания доступа
			wp_schedule_single_event( $paidto, '\Lt\Notify', $user_id );
		}
	}


	/**
	 * При регистрации пользователя открывает ему доступ на 3 дня
	 *
	 * @param $user_id
	 *
	 */
	public static function user_register( $user_id ) {
		$user = new \WP_User( $user_id );
		// доступ предоставляется только подписчикам
		if ( ! Users::user_is( 'subscriber', $user ) ) {
			return;
		}
		$start   = new DateTime();
		$options = get_option( Settings::$option_prefix . '_plugin_options_timing', true );
		update_user_meta( $user_id, 'paidfrom', $paidfrom = $start->setTime( 0, 0, 0 )->getTimestamp() );
		update_user_meta( $user_id, 'paidto', $paidto = $start->add( new \DateInterval(

			Settings::parse_date_time(
				$options['registration_timevalue'],
				$options['registration_timespan'],
				'P3D' )

		) )->setTime( 23, 59, 59 )->getTimestamp() );
		// чтобы не присылать уведомления о начале доступа мы передаем null
		do_action( 'Lt\PaidChange', $user_id, null, $paidto );
	}

	/**
	 * Смотрит на unix-timestamp мета-поля  paidfrom / paidto и вычисляет статус активности пользователя
	 *
	 * @param $user_id int
	 *
	 * @return bool
	 */
	public static function is_active( $user_id ) {
		$from_unixtime = get_user_meta( $user_id, 'paidfrom', true );
		$to_unixtime   = get_user_meta( $user_id, 'paidto', true );
		switch ( true ) {
			case ! empty( $from_unixtime ) && time() > $from_unixtime && ( ! $to_unixtime || time() < $to_unixtime ):
				return true;
			default:
				return false;
		}
	}

	public static function is_past( $user_id ) {
		$to_unixtime = get_user_meta( $user_id, 'paidto', true );
		switch ( true ) {
			case ! empty( $to_unixtime ) && time() > $to_unixtime:
				return true;
			default:
				return false;
		}
	}

	public static function is_future( $user_id
	) {
		$from_unixtime = get_user_meta( $user_id, 'paidfrom', true );
		switch ( true ) {
			case ! empty( $from_unixtime ) && time() < $from_unixtime:
				return true;
			default:
				return false;
		}
	}

	public static function is_noset( $user_id ) {
		$from_unixtime = get_user_meta( $user_id, 'paidfrom', true );
		$to_unixtime   = get_user_meta( $user_id, 'paidto', true );
		switch ( true ) {
			case ! $from_unixtime && ! $to_unixtime:
				return true;
			default:
				return false;
		}
	}


	public static function new_modify_user_table( $column ) {

		if ( current_user_can( 'manage_options' ) ) {
			$column['access'] = __( 'Access due date', 'lt' );
		}

		return $column;
	}

	/**
	 * Печатаем иконку календаря рядом с пользователем
	 *
	 * @param $val
	 * @param $column_name
	 * @param $user_id
	 *
	 * @return mixed|string
	 */
	public
	static function new_modify_user_table_row(
		$val, $column_name, $user_id
	) {
		switch ( $column_name ) {
			case 'access' :
				$paidfrom       = get_user_meta( $user_id, 'paidfrom', true );
				$paidto         = get_user_meta( $user_id, 'paidto', true );
				$date_time_from = ( new DateTime() )->setTimestamp( $paidfrom )->format( 'c' );
				$date_time_to   = ( new DateTime() )->setTimestamp( $paidto )->format( 'c' );
				$val            = "<div class='paidtill_{$user_id}' data-user-id='{$user_id}'><span class='paidtill'><input data-to='" . esc_attr( $date_time_to ) . "' data-from='" . esc_attr( $date_time_from ) . "' class='paidtill' name='paidtill[{$user_id}]' type='hidden' /></span></div>";
				break;
			default:
		}

		return $val;
	}

	/**
	 *  Обработчик ajax вызова из админки
	 */
	public
	static function consult_change_date_admin_ajax() {
		$user = wp_get_current_user();
		if ( $user && current_user_can( 'edit_posts' ) ) {
			// меняем статус
			$from = ( new DateTime( $_POST['from'] ) )->getTimestamp();
			$to   = ( new DateTime( $_POST['to'] ) )->getTimestamp();

			update_user_meta( $_POST['userid'], 'paidfrom', $from );
			update_user_meta( $_POST['userid'], 'paidto', $to );
			do_action( 'Lt\PaidChange', $user->ID, $from, $to );
			wp_die( 'ok' );
		}
		wp_die( __( 'Access denied', 'lt' ), 403 );
	}

	public static function user_is( $role, $user = null ) {
		if ( ! ( $user instanceof \WP_User ) ) {
			$user = wp_get_current_user();
			if ( $user->ID == 0 ) {
				return false;
			}
		}

		return in_array( $role, $user->roles );
	}

	public static function admin_enqueue_scripts( $hook ) {
		global $pagenow, $typenow;

		if ( $pagenow == 'users.php' ) {

			$wp_scripts = wp_scripts();
			wp_enqueue_style( 'plugin_name-admin-ui-css',
				'//ajax.googleapis.com/ajax/libs/jqueryui/' . $wp_scripts->registered['jquery-ui-core']->ver . '/themes/smoothness/jquery-ui.css',
				false,
				1,
				false );


			wp_enqueue_style( 'jquerydaterange' );
			//wp_enqueue_script( 'momentjs' );
			wp_enqueue_script( 'admin-datepicker', LT_URL . 'assets/admin-datepicker.js', array( 'jquerydaterange' ) );
			$inline_js = array(
				'nonce'   => wp_create_nonce( 'consultation-ajax-form' ),
				'ajaxurl' => admin_url( 'admin-ajax.php' ),
			);
			wp_localize_script( 'admin-datepicker', 'my_ajax_object', $inline_js );


		}

	}

}