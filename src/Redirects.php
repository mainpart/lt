<?php


namespace Lt;


class Redirects {
	private static $initiated = false;

	public static function init() {

		if ( ! self::$initiated ) {

			self::$initiated = true;
			add_filter( 'login_redirect', [ self::class, 'login' ], 10, 3 );
			add_filter( 'allowed_redirect_hosts', [ self::class, 'fix_allowed' ], 9999 );
			add_action( 'user_register', [self::class, 'auto_login_new_user'] );
		}

	}

	/**
	 * Добавляет в разрешенные к переадресации хосты указанный в настройках плагина
	 *
	 * @param $arr string[] - массив доменных имен разрешенных к переадресации
	 *
	 * @return mixed
	 */
	public static function fix_allowed( $arr ) {
		$options = get_option( 'lt_plugin_options_redirects', true );

		foreach ( (array) $options as $value ) {
			if ( $value ) {
				$wpp = parse_url( $value );
				if ( ! empty( $wpp['host'] ) ) {
					$arr[] = $wpp['host'];
				}
			}
		}

		return $arr;

	}

	public static function auto_login_new_user( $user_id ) {
		/** @var \WP_User $user */
		$user  = get_user_by('id', $user_id);
		if (!empty($user->user_activation_key)) return;
		wp_set_current_user($user_id);
		wp_set_auth_cookie($user_id);
		wp_redirect( apply_filters('login_redirect', home_url('/'), home_url('/'), $user) ); // You can change home_url() to the specific URL,such as "wp_redirect( 'http://www.wpcoke.com' )";
		exit();
	}

	/**
	 * Редирект после логина
	 *
	 * @param $redirect_to
	 * @param $request
	 * @param $user
	 *
	 * @return mixed
	 */
	public static function login( $redirect_to, $request, $user ) {

		//is there a user to check?

		if ( ! isset( $user->user_login ) ) {
			return $redirect_to;
		}

		if ( isset( $user->roles ) && is_array( $user->roles ) && in_array( 'administrator', $user->roles ) ) {
			return $redirect_to;
		}

		// если это подписчик то пробуем закинуть его на его консультацию
		if ( Users::user_is( 'subscriber', $user ) ) {
			remove_filter( 'posts_results', [ PostType::class, 'posts_results' ] );
			$query = new \WP_Query( [
				'author'      => $user->ID,
				'post_type'   => PostType::POST_TYPE,
				'post_status' => [ 'publish' ],
			] );

			if ( $query->post_count ) {
				$redirect    = $query->post->ID;
				$redirect_to = get_post_permalink( $redirect );
			}
		} elseif ( Users::user_is( 'homeopath', $user ) ) {
			// гомеопата переадресовываем на список консультаций
			$redirect_to = get_site_url( null, '/?post_type=' . PostType::POST_TYPE );
		}


		$options = get_option( 'lt_plugin_options_redirects', true );
		switch ( true ) {
			case Users::is_active( $user->ID ) && ! empty( $options['login_active'] ) :
				if ( ! empty( $options['login_active'] ) ) {
					$redirect_to = $options['login_active'];
				}
				break;
			case Users::is_future( $user->ID ) && ! empty( $options['login_future'] ) :
				if ( ! empty( $options['login_future'] ) ) {
					$redirect_to = $options['login_future'];
				}
				break;
			case Users::is_past( $user->ID ) && ! empty( $options['login_past'] ):
				if ( ! empty( $options['login_past'] ) ) {
					$redirect_to = $options['login_past'];
				}
				break;
			case Users::is_noset( $user->ID ) && ! empty( $options['login_noinfo'] ):
				$redirect_to = $options['login_noinfo'];
				break;
		}


		return $redirect_to;
	}


}