<?php


namespace Lt;


class ShortCode {

	function shortcode( $atts, $content = null ) {
		$user = wp_get_current_user();
		if ( $atts['role'] && is_array( $user->roles ) && count( array_intersect( preg_split( '/[\ \n\,]+/', $atts['role'], - 1, PREG_SPLIT_NO_EMPTY ), $user->roles ) ) > 0 ) {
			return $content;
		}

		return "";
	}

	private static $initiated = false;


	public static function init() {
		if ( ! self::$initiated ) {
			self::$initiated = true;
			add_shortcode( 'userrole', [ self::class, 'shortcode' ] );
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