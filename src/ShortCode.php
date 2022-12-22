<?php


namespace Lt;


class ShortCode {
	/**
	 * Обрабатывает шоткод [userrole role="homeopath,subscriber,..." status="active,past,future,noset"] содержимое [/userrole]
	 * @param $atts
	 * @param null $content
	 *
	 * @return string
	 */
	function shortcode( $atts, $content = null ) {
		$user = wp_get_current_user();
		if ( $atts['role'] && is_array( $user->roles ) && count( array_intersect( preg_split( '/[\ \n\,]+/', $atts['role'], - 1, PREG_SPLIT_NO_EMPTY ), $user->roles ) ) > 0 ) {
			$status_arr = preg_split( '/[\ \n\,]+/', $atts['status'], - 1, PREG_SPLIT_NO_EMPTY );
			switch ( true ) {
				case in_array( "active", $status_arr ) && Users::is_active( $user->ID ):
				case in_array( "past", $status_arr ) && Users::is_past( $user->ID ):
				case in_array( "future", $status_arr ) && Users::is_future( $user->ID ):
				case in_array( "noset", $status_arr ) && Users::is_noset( $user->ID ):
				case count( $status_arr ) == 0:
					return $content;
				default:
					return "";
			}
		}

		return "";
	}
	function convertation( $atts, $content = null ) {
		$options = get_option( Settings::$option_prefix . '_payments_options' );
		return $atts['amount'] * $options['convertation'];
	}

	private static $initiated = false;

	function paybutton($atts, $content = null) {
		$options = get_option( Settings::$option_prefix . '_payments_options' );
		return '<iframe class="paybutton" src="https://yoomoney.ru/quickpay/button-widget?
				targets=Абонемент гомеопата&default-sum='.$atts['amount'] * $options['convertation'] .'
				&button-text=11&yoomoney-payment-type=on
				&button-size=m&button-color=black
				&successURL='.urlencode($options['payment_return_url']). '
				&quickpay=small&account='.$options['wallet'].'&label='.get_current_user_id().'
				" scrolling="no" width="184" height="36" frameborder="0"></iframe>';
	}

	public static function init() {
		if ( ! self::$initiated ) {
			self::$initiated = true;
			add_shortcode( 'userrole', [ self::class, 'shortcode' ] );
			add_shortcode( 'convertation', [ self::class, 'convertation' ] );
			add_shortcode('paybutton', [self::class, 'paybutton']);
			add_action( 'admin_bar_menu', [ self::class, 'toolbar_link_to_mypage' ], 999 );
		}

	}

	public static function toolbar_link_to_mypage( $wp_admin_bar ) {
		$user = wp_get_current_user();
		if ( ! $user->ID ) {
			return;
		}
		if ( Users::is_active( $user->ID ) ) {
			$args = array(
				'id'    => 'my_page',
				'title' => __( 'Ваш доступ активен еще: &nbsp;&nbsp;&nbsp;&nbsp;' . do_shortcode( '[tminus usermetafield="paidto" style="lt" weeks="нед." days="дн." hours="ч." minutes="м." seconds="с."]неактивен[/tminus]' ), 'textdomain' ),
				//'href'  => 'http://mysite.com/my-page/',
				'meta'  => array(
					'class' => 'my-toolbar-page'
				)
			);
		}
		if ( Users::is_future( $user->ID ) ) {
			$args = array(
				'id'    => 'my_page',
				'title' => __( 'До начала вашего доступа: &nbsp;&nbsp;&nbsp;&nbsp;' . do_shortcode( '[tminus usermetafield="paidfrom" style="lt" weeks="нед." days="дн." hours="ч." minutes="м." seconds="с."]неактивен[/tminus]' ), 'textdomain' ),
				//'href'  => 'http://mysite.com/my-page/',
				'meta'  => array(
					'class' => 'my-toolbar-page'
				)
			);
		}
		if ( Users::is_past( $user->ID ) ) {
			$args = array(
				'id'    => 'my_page',
				'title' => __( 'Доступ окончен', 'textdomain' ),
				'meta'  => array(
					'class' => 'my-toolbar-page'
				)
			);
		}


		$wp_admin_bar->add_node( $args );
	}


}
