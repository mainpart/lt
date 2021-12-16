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
			add_action( 'admin_enqueue_scripts', [self::class, 'admin_enqueue_scripts' ]);
			add_action( 'user_register', [ self::class, 'set_initial_access' ] );
			add_action( 'Lt\PaidChange', [ self::class, 'schedule_notification' ], 10, 3 );
			add_action( 'Lt\PaidChange', [ self::class, 'change_subscribe_status' ], 100, 1);
			add_action( 'Lt\Notify', [ self::class, 'notify_user' ] );
			add_action( 'af/form/editing/user_created', [ self::class, 'form_sign_in_user' ], 10, 1 );
			add_action('af/form/validate', [self::class,'validate']);
		}


	}
	public static function validate(){
		$email = af_get_field( 'email' );
		if (!($email && !get_user_by('email',$email))){
			af_add_error('email','Пользователь с таким email уже есть');
		}

	}
	public static function form_sign_in_user( $user ) {
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
	private static function send_mail( $user, $subject, $body ) {
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
	static function notify_user( $user_id ) {

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
		// в дополнение меняем статус подписки пользователя когда заканчивается время подписки
		self::change_subscribe_status($user_id);

	}
	static function change_subscribe_status($user_id) {
		global $wpdb;

		if (Users::is_active($user_id)) {
			$wpdb->update($wpdb->prefix.'comment_mail_subs', ['status'=>'subscribed'], ['user_id'=>$user_id]);
		} elseif (Users::is_past($user_id) || Users::is_future($user_id)) {
			$wpdb->update($wpdb->prefix.'comment_mail_subs', ['status'=>'suspended'], ['user_id'=>$user_id]);
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
	static function schedule_notification( $user_id, $paidfrom, $paidto ) {
		// нужно убрать старый крон
		while ( $time_schedule = wp_next_scheduled( 'Lt\Notify', $user_id ) ) {
			wp_unschedule_event( $time_schedule, 'Lt\Notify', $user_id );
		}
		// за три дня до начала доступа оповещаем пользователя
		if ( $paidfrom ) {
			wp_schedule_single_event( $paidfrom, 'Lt\Notify', $user_id );
		}
		// за определенную дату до окончания подписки предупреждаем
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
			wp_schedule_single_event( $paidto, 'Lt\Notify', $user_id );
		}
	}


	/**
	 * При регистрации пользователя открывает ему доступ на 3 дня
	 *
	 * @param $user_id
	 *
	 */
	public static function set_initial_access( $user_id ) {
		$start   = new DateTime();
		$options = get_option( Settings::$option_prefix . '_plugin_options_timing', true );
		update_user_meta( $user_id, 'paidfrom', $paidfrom = $start->setTime( 0, 0, 0 )->getTimestamp() );
		update_user_meta( $user_id, 'paidto', $paidto = $start->add( new \DateInterval(

			Settings::parse_date_time(
				$options['registration_timevalue'],
				$options['registration_timespan'],
				'P3D' )

		) )/*->setTime( 23, 59, 59 )*/->getTimestamp() );
		// чтобы не присылать уведомления о начале доступа мы передаем null
		do_action( 'Lt\PaidChange', $user_id, null, $paidto );
	}


	public static function has_post($user){
		remove_filter( 'posts_results', [ PostType::class, 'posts_results' ] );
		$query = new \WP_Query( [
			'author'      => $user->ID,
			'post_type'   => PostType::POST_TYPE,
			'post_status' => [ 'publish' ],
			'posts_per_page'=>1,
		] );
		add_filter( 'posts_results', [ PostType::class, 'posts_results' ] );
		return $query->post_count != 0;
	}
	/**
	 * Смотрит на unix-timestamp мета-поля  paidfrom / paidto и вычисляет статус активности пользователя
	 *
	 * @param $user_id int
	 *
	 * @return bool
	 */
	public static function is_active( $user_id ) {

		if ($user = get_user_by('id', $user_id)) {
			if (Users::user_is('subscriber', $user) && ! Users::has_post($user)) {
				return true;
			}
		}
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
				$date_time_from = $paidfrom ? ( new DateTime() )->setTimestamp( $paidfrom )->format( 'c' ) : '';
				$date_time_to   = $paidto ? ( new DateTime() )->setTimestamp( $paidto )->format( 'c' ) :'';
				$val            = empty($paidfrom) || empty($paidto) ? "Не установлено" : "<div class='paidtill_{$user_id}' data-user-id='{$user_id}'><span class='paidtill'><input data-to='" . esc_attr( $date_time_to ) . "' data-from='" . esc_attr( $date_time_from ) . "' class='paidtill' name='paidtill[{$user_id}]' type='hidden' /></span></div>";
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
			do_action( 'Lt\PaidChange', $_POST['userid'], $from, $to );
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
		global $pagenow;

		if ( $pagenow == 'users.php' ) {

			$wp_scripts = wp_scripts();

			wp_register_script( 'jquerydaterange', LT_URL . 'vendor/jquery-ui-daterangepicker/jquery.comiseo.daterangepicker.js', [
				'jquery',
				'jquery-ui-button',
				'jquery-ui-menu',
				'jquery-ui-datepicker',
				'moment'
			] );
			wp_register_style( 'jquerydaterange', LT_URL . 'vendor/jquery-ui-daterangepicker/jquery.comiseo.daterangepicker.css' );


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