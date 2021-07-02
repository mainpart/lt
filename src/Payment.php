<?php

namespace Lt;

class Payment {

	const POST_TYPE = 'payment';
	private static $initiated = false;

	public static function init() {
		if ( ! self::$initiated ) {
			self::$initiated = true;
			add_action( 'wp_loaded', [ self::class, 'register_post_type' ] );
			add_action( 'wp', [ self::class, 'callback' ] );
			add_filter( 'manage_' . self::POST_TYPE . '_posts_custom_column', [
				self::class,
				'payments_table_row'
			], 10, 2 );
			add_filter( 'the_content', [ self::class, 'the_content' ] );
			add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', [ self::class, 'payments_table' ] );
			//add_filter( 'posts_results', [ self::class, 'posts_results' ] );
		}

	}

	public static function the_content( $content ) {
		$content = str_replace( '#lt_userid#', get_current_user_id(), $content );
		$options = get_option( Settings::$option_prefix . '_payments_options' );
		$content = str_replace( '#lt_account#', $options['wallet'], $content );

		return $content;
	}


	public static function payments_table_row( $column_name, $post_id ) {
		if ( $column_name == 'amount' ) {
			echo get_post_meta( $post_id, 'withdraw_amount', true );
		}
		if ( $column_name == 'role' ) {
			$user_id = get_post_meta( $post_id, 'label', true );
			$user    = get_user_by( 'ID', $user_id );
			if ( ! $user ) {
				return;
			}
			if ( isset( $user->roles[0] ) ) {
				echo $user->roles[0];
			}
		}
	}

	public static function payments_table( $column ) {
		if ( current_user_can( 'manage_options' ) ) {
			$column['amount'] = __( 'Amount', 'lt' );
			$column['role']   = __( 'Role', 'lt' );
		}

		return $column;
	}


	public static function register_post_type() {
		register_post_type( self::POST_TYPE,
			array(
				'labels'             => array(
					'name'          => __( 'Payments', 'lt' ),
					'singular_name' => __( 'Payments', 'lt' )
				),
				//'rewrite'            => array( 'slug' => 'payment' ),
				//'show_in_rest' => true,
				'public'             => true,
				'has_archive'        => true,
				'show_ui'            => true,
				'supports'           => [ 'author', 'title', 'comments', 'editor', 'custom-fields' ],
				'publicly_queryable' => true,
				'capability_type'    => 'post',
				'capabilities'       => array(
					//'create_posts' => 'do_not_allow',
					//'edit_posts' => true,
				),
				'map_meta_cap'       => true//current_user_can( 'activate_plugins' ) ? true : false,

			)
		);

	}

	/**
	 * Обрабатывает пиингбэк от платежного шлюза
	 */
	static function callback() {
		if ( ! isset( $_GET['yoo_callback'] ) ) {
			return;
		}
		//file_put_contents(__DIR__."/debug.txt", var_export($_POST,true), FILE_APPEND);
		        /*
			$user = wp_get_current_user();


			$option = get_option( Settings::$option_prefix . '_payments_options' );
			if ( isset( $option['match'] ) && is_array( $option['match'] ) ) {
				foreach ( $option['match'] as $key => $value ) {
					if ( $value['amount'] == $_GET['withdraw_amount'] ) {
						$dt = Settings::parse_date_time(
							$value['timevalue'],
							$value['timespan'],
							false );
						if ( ! $dt ) {
							continue;
						}

						$start = new \DateTime();
						$paidfrom = $start->getTimestamp();
						update_user_meta( $user->ID, 'paidfrom', $paidfrom );
						$paidto = $start->add( new \DateInterval( $dt ) )->getTimestamp();
						update_user_meta( $user->ID, 'paidto', $paidto );
						do_action( 'Lt\PaidChange', $user->ID, $paidfrom, $paidto );


					}

				}
			}
			die($start->format('c'));
			*/
		// получаем id админа
		$ids = get_users( [ 'role' => 'Administrator' ] );
		if ( count( $ids ) ) {
			$author_id = $ids[0];
		} else {
			return;
		}

		$user = new \WP_User( $_POST['label'] );


		$payment_id = wp_insert_post( [
			'post_author' => $author_id,
			'post_title'  => $user->exists() ? $user->display_name : 'Пользователь не найден',
			'post_type'   => self::POST_TYPE,
			'post_status' => 'publish',

		] );
		foreach ( $_POST as $key => $value ) {
			update_post_meta( $payment_id, $key, $value );
		}

		if ( $user->exists() && $_POST['unaccepted'] != 'true' && $_POST['codepro'] == 'false' ) {
			// ставим пользователю время подписки
			$option = get_option( Settings::$option_prefix . '_payments_options' );
			if ( isset( $option['match'] ) && is_array( $option['match'] ) ) {
				foreach ( $option['match'] as $key => $value ) {
					if ( $value['amount'] == $_POST['withdraw_amount'] ) {
						$dt = Settings::parse_date_time(
							$value['timevalue'],
							$value['timespan'],
							false );
						if ( ! $dt ) {
							continue;
						}

						$start = new \DateTime();
						$paidfrom = $start->getTimestamp();
						update_user_meta( $user->ID, 'paidfrom', $paidfrom );
						$paidto = $start->add( new \DateInterval( $dt ) )->getTimestamp();
						update_user_meta( $user->ID, 'paidto', $paidto );
						do_action( 'Lt\PaidChange', $user->ID, $paidfrom, $paidto );


					}

				}
			}

		}
                header("HTTP/1.1 200 OK");
		die( 200 );

	}

}